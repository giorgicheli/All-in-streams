<?php

namespace Tests\Feature;

use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use App\Models\DeliverySubmission;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProviderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private CampaignWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->workflow = app(CampaignWorkflow::class);
    }

    private function brief(array $overrides = []): Brief
    {
        return Brief::create(array_replace([
            'user_id' => User::factory()->create(['role' => 'business'])->id,
            'title' => 'Private launch campaign', 'brand' => 'Private company', 'vertical' => 'Gaming',
            'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch',
            'budget' => 5000, 'currency' => 'EUR', 'commercial_model' => 'Fixed + CPA',
            'deliverables' => 'Private client instructions', 'start_date' => today()->addDay(), 'end_date' => today()->addDays(20),
            'status' => 'matching', 'provider_type' => 'either',
        ], $overrides));
    }

    private function creator(array $overrides = []): CreatorProfile
    {
        $owner = User::factory()->create(['role' => 'creator']);
        return CreatorProfile::create(array_replace([
            'user_id' => $owner->id, 'public_name' => 'Streamer '.$owner->id, 'country' => 'Germany',
            'language' => 'German', 'platform' => 'Twitch', 'channel_url' => 'https://www.twitch.tv/streamer'.$owner->id,
            'twitch_url' => 'https://www.twitch.tv/streamer'.$owner->id, 'audience_geos' => ['Germany'],
            'vertical' => 'Gaming', 'rate' => 750, 'commercial_model' => 'Fixed', 'status' => 'approved',
        ], $overrides));
    }

    private function agency(): array
    {
        $agency = User::factory()->create(['role' => 'agency', 'account_type' => 'agency']);
        AgencyProfile::create(['user_id' => $agency->id, 'services' => ['supply', 'request'], 'status' => 'approved', 'country' => 'Germany', 'languages' => ['German'], 'audience_geos' => ['Germany'], 'platforms' => ['Twitch']]);
        $talent = AgencyTalent::create(['agency_user_id' => $agency->id, 'public_name' => 'Agency streamer', 'country' => 'Germany', 'language' => 'German', 'platform' => 'Twitch', 'twitch_url' => 'https://www.twitch.tv/agencytalent'.$agency->id, 'audience_geos' => ['Germany'], 'status' => 'approved']);
        return [$agency, $talent];
    }

    private function invalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Expected validation to reject the workflow operation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function accept(Invitation $invitation, User $actor, array $talentIds = []): Invitation
    {
        return $this->workflow->respond($invitation, $actor, 'interested', offerVersion: $invitation->offer_version, talentIds: $talentIds);
    }

    public function test_alternative_quotes_are_not_summed_and_only_one_creator_or_agency_can_proceed(): void
    {
        $brief = $this->brief(['budget' => 1000]);
        $creator = $this->creator();
        [$agency, $talent] = $this->agency();
        $creatorOffer = $this->workflow->invite($brief, $creator, $this->admin, ['compensation' => 800]);
        $agencyOffer = $this->workflow->inviteAgency($brief, $agency, $this->admin, ['compensation' => 800]);
        $this->accept($creatorOffer, $creator->user);
        $this->workflow->respond($agencyOffer, $agency, 'changes_requested', 'We can provide one roster member for this fee.', 900, 25, 1, [$talent->id]);
        $accepted = $this->workflow->acceptCounter($agencyOffer, $this->admin);
        $this->assertSame('900.00', $accepted->compensation);
        $this->assertSame($accepted->offer_version, $accepted->accepted_offer_version);
        $this->workflow->transition($brief, 'proposal', $this->admin);
        $this->invalid(fn () => $this->workflow->transition($brief->fresh(), 'contracts', $this->admin), 'status');

        $selected = $this->workflow->selectProvider($accepted, $this->admin);
        $this->assertSame('selected', $selected->selection_status);
        $this->assertSame('not_selected', $creatorOffer->fresh()->selection_status);
        $this->assertSame(1, $brief->selectedInvitations()->count());
        $this->assertSame('contracts', $this->workflow->transition($brief->fresh(), 'contracts', $this->admin)->status);
        $this->invalid(fn () => $this->workflow->selectProvider($creatorOffer->fresh(), $this->admin), 'status');

        $brief->refresh()->update(['payment_ready' => true]);
        Storage::disk('local')->put('business.pdf', 'Signed business agreement');
        Storage::disk('local')->put('agency.pdf', 'Signed agency agreement');
        CampaignDocument::create(['brief_id' => $brief->id, 'title' => 'Business terms', 'audience' => 'business', 'signature_status' => 'signed', 'file_path' => 'business.pdf']);
        CampaignDocument::create(['brief_id' => $brief->id, 'agency_user_id' => $agency->id, 'title' => 'Agency terms', 'audience' => 'agency', 'signature_status' => 'signed', 'file_path' => 'agency.pdf']);
        $this->assertSame('live', $this->workflow->transition($brief, 'live', $this->admin)->status);
        $this->invalid(fn () => $this->workflow->transition($brief->fresh(), 'completed', $this->admin), 'status');
        DeliverySubmission::create(['invitation_id' => $selected->id, 'user_id' => $agency->id, 'title' => 'Delivered stream', 'content_url' => 'https://www.twitch.tv/videos/1234', 'delivered_on' => today(), 'status' => 'approved']);
        $this->assertSame('completed', $this->workflow->transition($brief->fresh(), 'completed', $this->admin)->status);
    }

    public function test_revised_scope_and_schedule_require_a_fresh_acceptance_and_reject_stale_responses(): void
    {
        $brief = $this->brief();
        $creator = $this->creator();
        $offer = $this->workflow->invite($brief, $creator, $this->admin, ['start_date' => null, 'end_date' => null]);
        $this->assertSame($brief->start_date->toDateString(), $offer->offer_details['start_date']);
        $this->accept($offer, $creator->user);
        $newEnd = today()->addDays(25)->toDateString();
        $revised = $this->workflow->reviseOffer($offer->fresh(), $this->admin, ['deliverables' => '5 streams', 'compensation' => 1500, 'end_date' => $newEnd]);
        $this->assertSame('pending', $revised->status);
        $this->assertSame(2, $revised->offer_version);
        $this->assertNull($revised->accepted_offer_version);
        $this->assertSame($newEnd, $revised->offer_details['end_date']);
        $this->assertNotSame($newEnd, $brief->fresh()->end_date->toDateString());
        $this->invalid(fn () => $this->workflow->selectProvider($revised, $this->admin), 'status');
        $this->invalid(fn () => $this->workflow->respond($revised, $creator->user, 'interested', offerVersion: 1), 'offer_version');
        $accepted = $this->accept($revised, $creator->user);
        $this->workflow->selectProvider($accepted, $this->admin);
        $this->assertSame(['offered', 'interested', 'revised', 'interested', 'selected'], $offer->events()->oldest('id')->pluck('kind')->all());
    }

    public function test_counter_requests_need_notes_and_staff_cannot_accept_above_the_private_campaign_budget(): void
    {
        $brief = $this->brief(['budget' => 1000]);
        $creator = $this->creator();
        $offer = $this->workflow->invite($brief, $creator, $this->admin);
        $this->invalid(fn () => $this->workflow->respond($offer, $creator->user, 'changes_requested', counterAmount: 1200, offerVersion: 1), 'notes');
        $counter = $this->workflow->respond($offer, $creator->user, 'changes_requested', 'Please increase the fee for the extra stream.', 1200, offerVersion: 1);
        $this->assertSame('1200.00', $counter->counter_amount);
        $this->invalid(fn () => $this->workflow->acceptCounter($counter, $this->admin), 'compensation');
        $this->assertSame('750.00', $offer->fresh()->compensation);
        $revised = $this->workflow->reviseOffer($counter, $this->admin, ['compensation' => 1000]);
        $this->assertNull($revised->counter_amount);
        $this->assertSame('pending', $revised->status);
        $this->assertSame('1000.00', $revised->compensation);
    }

    public function test_agency_acceptance_requires_its_own_approved_active_roster(): void
    {
        [$agency, $talent] = $this->agency();
        [$otherAgency, $otherTalent] = $this->agency();
        $offer = $this->workflow->inviteAgency($this->brief(), $agency, $this->admin, ['compensation' => 1200]);
        $this->invalid(fn () => $this->accept($offer, $agency), 'talent_ids');
        $this->invalid(fn () => $this->accept($offer, $agency, [$otherTalent->id]), 'talent_ids');
        $talent->update(['status' => 'pending']);
        $this->invalid(fn () => $this->accept($offer, $agency, [$talent->id]), 'status');
        $talent->update(['status' => 'approved']);
        $accepted = $this->accept($offer, $agency, [$talent->id]);
        $this->assertSame([$talent->id], $accepted->proposed_talent_ids);
        $talent->delete();
        $this->invalid(fn () => $this->workflow->selectProvider($accepted, $this->admin), 'status');
    }

    public function test_supply_approval_provider_preference_and_self_dealing_are_checked_before_inviting(): void
    {
        [$agency] = $this->agency();
        $this->invalid(fn () => $this->workflow->inviteAgency($this->brief(['user_id' => $agency->id]), $agency, $this->admin), 'agency_user_id');
        $this->invalid(fn () => $this->workflow->inviteAgency($this->brief(['provider_type' => 'creator']), $agency, $this->admin), 'brief_id');
        $this->invalid(fn () => $this->workflow->invite($this->brief(['provider_type' => 'agency']), $this->creator(), $this->admin), 'brief_id');
        $agency->agencyProfile->update(['services' => ['request']]);
        $this->invalid(fn () => $this->workflow->inviteAgency($this->brief(), $agency->fresh(), $this->admin), 'agency_user_id');
        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_final_selection_closes_other_pending_and_counter_offers_and_cannot_be_reopened_implicitly(): void
    {
        $brief = $this->brief();
        $first = $this->creator(); $second = $this->creator();
        $accepted = $this->accept($this->workflow->invite($brief, $first, $this->admin), $first->user);
        $counter = $this->workflow->respond($this->workflow->invite($brief, $second, $this->admin), $second->user, 'changes_requested', 'Please increase the fixed fee.', 900, offerVersion: 1);
        $this->workflow->selectProvider($accepted, $this->admin);
        $this->invalid(fn () => $this->workflow->acceptCounter($counter->fresh(), $this->admin), 'status');
        $this->invalid(fn () => $this->workflow->reviseOffer($counter->fresh(), $this->admin, ['compensation' => 900]), 'status');
        $this->invalid(fn () => $this->workflow->invite($brief, $this->creator(), $this->admin), 'brief_id');
        $this->assertSame(1, $brief->selectedInvitations()->count());
    }

    public function test_a_campaign_currency_change_requires_revised_terms_and_new_provider_acceptance(): void
    {
        $brief = $this->brief();
        $creator = $this->creator();
        $offer = $this->accept($this->workflow->invite($brief, $creator, $this->admin), $creator->user);
        $brief->update(['currency' => 'USD']);
        $this->invalid(fn () => $this->workflow->selectProvider($offer, $this->admin), 'currency');
        $revised = $this->workflow->reviseOffer($offer, $this->admin, ['compensation' => 800]);
        $this->assertSame('USD', $revised->currency);
        $this->assertNull($revised->accepted_offer_version);
        $this->workflow->selectProvider($this->accept($revised, $creator->user), $this->admin);
        $this->assertSame(1, $brief->selectedInvitations()->count());
    }

    public function test_archival_preserves_completed_campaign_history_but_blocks_active_commitments(): void
    {
        $creator = $this->creator();
        $offer = $this->accept($this->workflow->invite($this->brief(), $creator, $this->admin), $creator->user);
        $this->workflow->selectProvider($offer, $this->admin);
        $offer->brief->update(['status' => 'contracts']);
        $this->invalid(fn () => $this->workflow->deleteCreator($creator, $this->admin), 'creator_profile_id');
        $offer->brief->update(['status' => 'live']);
        $this->invalid(fn () => $this->workflow->deleteCreator($creator, $this->admin), 'creator_profile_id');
        $offer->brief->update(['status' => 'completed']);
        $this->workflow->deleteCreator($creator, $this->admin);
        $this->assertSoftDeleted($creator);
        $historical = Invitation::eligibleForBusiness()->sole();
        $this->assertNull($historical->forBusiness()['public_name']);
        $this->assertSame($creator->id, $historical->creatorProfile->id);
        $this->invalid(fn () => $this->workflow->invite($this->brief(), $creator, $this->admin), 'creator_profile_id');
    }

    public function test_archiving_a_pending_creator_closes_its_offers_without_deleting_accounts_or_negotiation(): void
    {
        $creator = $this->creator();
        $offer = $this->workflow->invite($this->brief(), $creator, $this->admin);
        $this->workflow->deleteCreator($creator, $this->admin);
        $this->assertDatabaseHas('users', ['id' => $creator->user_id]);
        $this->assertSame('not_selected', $offer->fresh()->selection_status);
        $this->assertFalse($offer->fresh()->forRecipient($creator->user)['can_respond']);
        $this->assertSame(1, $offer->events()->count());
        $this->invalid(fn () => $this->accept($offer, $creator->user), 'status');
    }

    public function test_only_staff_can_send_or_select_and_other_recipients_cannot_reply(): void
    {
        $creator = $this->creator();
        $brief = $this->brief();
        try { $this->workflow->invite($brief, $creator, $brief->user); $this->fail('Clients cannot send provider offers.'); }
        catch (HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        $offer = $this->workflow->invite($brief, $creator, $this->admin);
        try { $this->accept($offer, $this->creator()->user); $this->fail('Other creators cannot answer an offer.'); }
        catch (HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        try { $this->workflow->selectProvider($offer, $creator->user); $this->fail('Recipients cannot select themselves.'); }
        catch (HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        $this->actingAs($creator->user)->post(route('creator.respond', $offer), ['status' => 'interested'])->assertSessionHasErrors('offer_version');
        $this->assertSame('pending', $offer->fresh()->status);
    }

    public function test_an_offer_cannot_have_both_a_creator_and_agency_recipient(): void
    {
        $creator = $this->creator(); [$agency] = $this->agency();
        $this->invalid(fn () => Invitation::create(['brief_id' => $this->brief()->id, 'creator_profile_id' => $creator->id, 'agency_user_id' => $agency->id, 'deliverables' => '4 streams']), 'creator_profile_id');
        $this->assertDatabaseCount('invitations', 0);
    }
}
