<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliverySubmissionResource;
use App\Filament\Resources\DeliverySubmissionResource\Pages\ManageDeliveries;
use App\Filament\Resources\PaymentMilestoneResource;
use App\Filament\Resources\PaymentMilestoneResource\Pages\ManagePayments;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\DeliverySubmission;
use App\Models\Invitation;
use App\Models\PaymentMilestone;
use App\Models\User;
use App\Services\ProviderDelivery;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProviderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        Storage::fake('local');
    }

    private function opportunity(string $recipient = 'creator', string $phase = 'live'): array
    {
        $business = User::factory()->create(['role' => 'business', 'company' => 'Private Brand Sentinel']);
        $provider = User::factory()->create(['role' => $recipient, 'account_type' => $recipient]);
        $brief = Brief::create([
            'user_id' => $business->id, 'title' => 'Private campaign title sentinel', 'brand' => 'Private Brand Sentinel',
            'vertical' => 'Gaming', 'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch',
            'budget' => 18000, 'currency' => 'EUR', 'commercial_model' => 'Fixed + CPA',
            'deliverables' => 'Private full business requirements', 'start_date' => today()->subDays(2), 'end_date' => today()->addDays(5),
            'notes' => 'Private company notes sentinel', 'status' => $phase,
        ]);
        $profile = $recipient === 'creator' ? CreatorProfile::create([
            'user_id' => $provider->id, 'public_name' => 'Test streamer', 'country' => 'Germany', 'language' => 'German',
            'platform' => 'Twitch', 'channel_url' => 'https://www.twitch.tv/teststreamer', 'twitch_url' => 'https://www.twitch.tv/teststreamer',
            'audience_geos' => ['Germany'], 'vertical' => 'Gaming', 'rate' => 750, 'commercial_model' => 'Fixed', 'status' => 'approved',
        ]) : null;
        $invitation = Invitation::create([
            'brief_id' => $brief->id, 'creator_profile_id' => $profile?->id, 'agency_user_id' => $recipient === 'agency' ? $provider->id : null,
            'status' => 'interested', 'selection_status' => 'selected', 'compensation' => 2400, 'currency' => 'EUR',
            'deliverables' => '4 live streams, 90 minutes each', 'offer_version' => 1, 'accepted_offer_version' => 1,
        ]);

        return [$provider, $invitation, $business];
    }

    private function evidence(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Completed stream 1', 'content_url' => 'https://www.twitch.tv/videos/12345',
            'delivered_on' => today()->toDateString(), 'notes' => 'Stream and tracking link completed.',
            'reported_impressions' => 5000, 'reported_clicks' => 250, 'reported_conversions' => 8,
        ], $overrides);
    }

    public function test_selected_provider_submits_private_evidence_without_self_approving_or_altering_terms(): void
    {
        [$provider, $invitation] = $this->opportunity();
        $this->actingAs($provider)->post(route('provider.deliveries.store', $invitation), $this->evidence([
            'evidence' => UploadedFile::fake()->create('report.pdf', 40, 'application/pdf'),
            'status' => 'approved', 'reviewed_by' => $provider->id, 'review_notes' => 'Self-approved', 'compensation' => 99999,
        ]))->assertRedirect(route('provider.deliveries'));

        $record = DeliverySubmission::sole();
        $this->assertSame('submitted', $record->status);
        $this->assertNull($record->reviewed_by);
        $this->assertNull($record->review_notes);
        $this->assertSame($provider->id, $record->user_id);
        Storage::disk('local')->assertExists($record->file_path);
        $this->assertStringStartsWith('provider-deliveries/', $record->file_path);
        $this->assertSame('2400.00', $invitation->fresh()->compensation);
        $this->assertDatabaseCount('campaign_metrics', 0);
    }

    public function test_only_the_selected_provider_with_a_current_accepted_live_offer_can_submit(): void
    {
        [$provider, $invitation, $business] = $this->opportunity();
        $other = User::factory()->create(['role' => 'creator']);
        foreach ([$business, $other] as $actor) {
            $this->actingAs($actor)->post(route('provider.deliveries.store', $invitation), $this->evidence())->assertForbidden();
        }
        $this->actingAs($provider);
        foreach ([['selection_status' => 'not_selected'], ['status' => 'pending'], ['accepted_offer_version' => null]] as $invalid) {
            $original = $invitation->only(array_keys($invalid));
            $invitation->update($invalid);
            $this->post(route('provider.deliveries.store', $invitation), $this->evidence())->assertSessionHasErrors('invitation');
            $invitation->update($original);
        }
        $invitation->brief->update(['status' => 'contracts']);
        $this->post(route('provider.deliveries.store', $invitation), $this->evidence())->assertSessionHasErrors('invitation');
        $this->assertDatabaseCount('delivery_submissions', 0);
    }

    public function test_evidence_rejects_unsafe_urls_invalid_counts_future_dates_and_executable_files(): void
    {
        [$provider, $invitation] = $this->opportunity();
        $this->actingAs($provider);
        foreach ([
            ['content_url' => 'javascript:alert(1)'], ['reported_clicks' => -1], ['reported_conversions' => 1.5],
            ['delivered_on' => today()->addDay()->toDateString()], ['evidence' => UploadedFile::fake()->create('script.php', 1, 'application/x-php')],
            ['evidence' => UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf')],
        ] as $invalid) {
            $this->post(route('provider.deliveries.store', $invitation), $this->evidence($invalid))->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->assertDatabaseCount('delivery_submissions', 0);
    }

    public function test_creator_and_agency_views_show_only_their_records_and_safe_opportunity_details(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $service = app(ProviderDelivery::class);
        [$creator, $creatorInvitation] = $this->opportunity();
        [$agency, $agencyInvitation] = $this->opportunity('agency');
        foreach ([[$creator, $creatorInvitation], [$agency, $agencyInvitation]] as [$provider, $invitation]) {
            $service->submit($invitation, $provider, $this->evidence(['title' => 'Own evidence '.$provider->id]));
            $service->createMilestone($invitation, $staff, ['label' => 'Own fee '.$provider->id, 'kind' => 'fixed_fee', 'amount' => 1200]);
        }
        foreach ([[$creator, $agency], [$agency, $creator]] as [$provider, $other]) {
            $deliveryPage = $this->actingAs($provider)->get(route('provider.deliveries'))->assertOk()->assertSee('Own evidence '.$provider->id)->assertDontSee('Own evidence '.$other->id);
            $paymentPage = $this->get(route('provider.payments'))->assertOk()->assertSee('Own fee '.$provider->id)->assertDontSee('Own fee '.$other->id);
            foreach ([$deliveryPage, $paymentPage] as $page) {
                $page->assertDontSee('Private Brand Sentinel')->assertDontSee('Private campaign title sentinel')->assertDontSee('Private company notes sentinel')->assertDontSee('18,000')->assertDontSee('18000');
            }
        }
    }

    public function test_evidence_download_is_private_to_recipient_and_staff_even_against_business_owner(): void
    {
        [$provider, $invitation, $business] = $this->opportunity('agency');
        $record = app(ProviderDelivery::class)->submit($invitation, $provider, $this->evidence(), UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'));
        foreach ([$business, User::factory()->create(['role' => 'creator']), User::factory()->create(['role' => 'agency'])] as $other) {
            $this->actingAs($other)->get(route('provider.deliveries.download', $record))->assertForbidden();
        }
        $this->actingAs($provider)->get(route('provider.deliveries.download', $record))->assertDownload();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('provider.deliveries.download', $record))->assertDownload();
    }

    public function test_provider_review_notes_and_payment_labels_redact_known_client_identifiers(): void
    {
        [$provider, $invitation, $business] = $this->opportunity();
        $service = app(ProviderDelivery::class);
        $staff = User::factory()->create(['role' => 'admin']);
        $record = $service->submit($invitation, $provider, $this->evidence());
        $service->review($record, $staff, 'approved', 'Checked for Private Brand Sentinel. Contact '.$business->email);
        $milestone = $service->createMilestone($invitation, $staff, ['label' => 'Private Brand Sentinel completion fee', 'kind' => 'fixed_fee', 'amount' => 1200]);
        $service->approveMilestone($milestone, $staff);
        $service->markPaid($milestone->fresh(), $staff, ['paid_at' => now(), 'reference' => $business->email]);
        $this->actingAs($provider)->get(route('provider.deliveries'))->assertOk()->assertDontSee('Private Brand Sentinel')->assertDontSee($business->email);
        $this->get(route('provider.payments'))->assertOk()->assertDontSee('Private Brand Sentinel')->assertDontSee($business->email);
        $this->assertStringContainsString('Private Brand Sentinel', $record->fresh()->review_notes);
        $this->assertSame('Private Brand Sentinel completion fee', $milestone->fresh()->label);
    }

    public function test_only_staff_reviews_and_only_returned_evidence_can_be_resubmitted(): void
    {
        [$provider, $invitation] = $this->opportunity();
        $service = app(ProviderDelivery::class);
        $staff = User::factory()->create(['role' => 'admin']);
        $record = $service->submit($invitation, $provider, $this->evidence());
        try {
            $service->review($record, $provider, 'approved');
            $this->fail('Providers cannot review their own evidence.');
        } catch (HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        $service->review($record, $staff, 'approved', 'Evidence checked.');
        $this->actingAs($provider)->post(route('provider.deliveries.store', $invitation), $this->evidence(['submission_id' => $record->id]))->assertSessionHasErrors('submission_id');
        $service->review($record->fresh(), $staff, 'changes_requested', 'Please attach the stream analytics.');
        $this->post(route('provider.deliveries.store', $invitation), $this->evidence(['submission_id' => $record->id, 'notes' => 'Corrected evidence']))->assertRedirect();
        $this->assertSame('submitted', $record->fresh()->status);
        $this->assertNull($record->fresh()->reviewed_by);
        $this->assertDatabaseCount('delivery_submissions', 1);
        $service->review($record->fresh(), $staff, 'approved');
        $invitation->brief->update(['status' => 'completed']);
        $this->expectException(ValidationException::class);
        $service->review($record->fresh(), $staff, 'changes_requested', 'Too late to reopen.');
    }

    public function test_manual_payment_requires_staff_and_approval_before_recording_a_payment(): void
    {
        [$provider, $invitation] = $this->opportunity();
        $service = app(ProviderDelivery::class);
        $staff = User::factory()->create(['role' => 'admin']);
        try {
            $service->createMilestone($invitation, $provider, ['label' => 'My fee', 'kind' => 'fixed_fee', 'amount' => 1200]);
            $this->fail('Providers cannot create payments.');
        } catch (HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        $milestone = $service->createMilestone($invitation, $staff, ['label' => '50% completion fee', 'kind' => 'fixed_fee', 'amount' => 1200, 'currency' => 'USD', 'status' => 'paid']);
        $this->assertSame('pending', $milestone->status);
        $this->assertSame('EUR', $milestone->currency);
        try {
            $service->markPaid($milestone, $staff, ['paid_at' => now()]);
            $this->fail('A pending milestone cannot be marked paid.');
        } catch (ValidationException $exception) { $this->assertArrayHasKey('status', $exception->errors()); }
        $service->approveMilestone($milestone, $staff);
        $service->markPaid($milestone->fresh(), $staff, ['paid_at' => now(), 'reference' => 'TRANSFER-123', 'amount' => 999999]);
        $this->assertSame('paid', $milestone->fresh()->status);
        $this->assertSame('1200.00', $milestone->fresh()->amount);
        $this->assertNotNull($milestone->fresh()->paid_at);
        $this->assertSame($staff->id, $milestone->fresh()->approved_by);
        $this->expectException(ValidationException::class);
        $service->markPaid($milestone->fresh(), $staff, ['paid_at' => now()]);
    }

    public function test_payment_records_require_a_selected_accepted_provider_in_an_allowed_phase(): void
    {
        [$provider, $invitation] = $this->opportunity();
        $service = app(ProviderDelivery::class);
        $staff = User::factory()->create(['role' => 'admin']);
        $data = ['label' => '50% fee', 'kind' => 'fixed_fee', 'amount' => 1200];
        foreach ([['selection_status' => 'not_selected'], ['status' => 'pending'], ['accepted_offer_version' => null]] as $invalid) {
            $original = $invitation->only(array_keys($invalid));
            $invitation->update($invalid);
            try {
                $service->createMilestone($invitation, $staff, $data);
                $this->fail('An unselected or unaccepted offer cannot receive a milestone.');
            } catch (ValidationException $exception) { $this->assertArrayHasKey('invitation', $exception->errors()); }
            $invitation->update($original);
        }
        $invitation->brief->update(['status' => 'matching']);
        try {
            $service->createMilestone($invitation, $staff, $data);
            $this->fail('Matching cannot receive payment milestones.');
        } catch (ValidationException $exception) { $this->assertArrayHasKey('invitation_id', $exception->errors()); }
        $this->assertDatabaseCount('payment_milestones', 0);
    }

    public function test_staff_resources_review_evidence_and_record_manual_milestones(): void
    {
        [$provider, $invitation] = $this->opportunity();
        $record = app(ProviderDelivery::class)->submit($invitation, $provider, $this->evidence());
        $staff = User::factory()->create(['role' => 'admin']);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get(DeliverySubmissionResource::getUrl())->assertOk();
        $this->get(PaymentMilestoneResource::getUrl())->assertOk();
        Livewire::test(ManageDeliveries::class)->callTableAction('approve', $record, data: ['review_notes' => 'Stream checked.'])->assertHasNoActionErrors();
        $this->assertSame('approved', $record->fresh()->status);
        Livewire::test(ManagePayments::class)->callAction('addMilestone', data: ['invitation_id' => $invitation->id, 'label' => 'Validated CPA settlement', 'kind' => 'validated_cpa', 'amount' => 200])->assertHasNoActionErrors();
        $milestone = PaymentMilestone::sole();
        Livewire::test(ManagePayments::class)->callTableAction('approve', $milestone)->assertHasNoActionErrors();
        Livewire::test(ManagePayments::class)->callTableAction('markPaid', $milestone->fresh(), data: ['paid_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'reference' => 'BANK-123'])->assertHasNoActionErrors();
        $this->assertSame('paid', $milestone->fresh()->status);
        foreach (['creator', 'agency', 'business'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(DeliverySubmissionResource::getUrl())->assertForbidden();
            $this->get(PaymentMilestoneResource::getUrl())->assertForbidden();
        }
    }
}
