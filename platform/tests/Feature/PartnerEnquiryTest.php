<?php

namespace Tests\Feature;

use App\Filament\Resources\PartnerEnquiryResource;
use App\Filament\Resources\PartnerEnquiryResource\Pages\ManagePartnerEnquiries;
use App\Models\PartnerEnquiry;
use App\Models\User;
use App\Services\CreatorProofVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakeCreatorProofVideo;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerEnquiryTest extends TestCase
{
    use RefreshDatabase, FakeCreatorProofVideo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('i', 32))]);
        $this->withoutVite();
        $this->fakeCreatorProofVideos();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function brand(array $changes = []): array
    {
        return array_replace([
            'submission_key' => (string) Str::uuid(), 'kind' => 'brand', 'name' => 'Brand Contact',
            'email' => 'brand@example.test', 'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!', 'telegram' => '@brand_contact', 'website' => 'https://example.test',
            'audience_geos' => ['Canada', 'Portugal'], 'requirements' => 'Twitch streams in June.', 'age_confirmed' => '1',
        ], $changes);
    }

    private function creator(string $type = 'both', array $changes = []): array
    {
        $data = [
            'submission_key' => (string) Str::uuid(), 'kind' => 'creator', 'creator_type' => $type,
            'email' => 'creator-'.$type.'@example.test', 'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!', 'verification_video' => $this->creatorProofVideo(), 'name' => 'Creator Contact', 'telegram' => 'https://t.me/creator_contact', 'age_confirmed' => '1',
            'audience_geos' => ['Canada', 'Kyrgyzstan'], 'currency' => 'EUR',
        ];
        if (in_array($type, ['streamer', 'both'], true)) $data += ['twitch_url' => 'https://twitch.tv/Creator_Test', 'stream_rate' => '500'];
        if (in_array($type, ['influencer', 'both'], true)) $data += ['instagram_url' => 'https://instagram.com/Creator.Test', 'story_rate' => '100.50', 'reel_rate' => '300'];
        return array_replace($data, $changes);
    }

    public function test_brand_signup_creates_a_private_pending_account_without_campaign_commitments(): void
    {
        $data = $this->brand(['status' => 'closed', 'staff_notes' => 'Forged staff notes', 'creator_type' => 'both', 'stream_rate' => '1', 'audience_geos' => 'canada, Portugal', 'role' => 'admin', 'partner_verification_status' => 'verified']);
        $this->post('/talk-to-us', $data)->assertSessionHasNoErrors()->assertRedirect('/account/pending');
        $record = PartnerEnquiry::sole();
        $this->assertSame('new', $record->status);
        $this->assertNull($record->staff_notes);
        $this->assertNull($record->creator_type);
        $this->assertNull($record->stream_rate);
        $this->assertNull($record->budget);
        $this->assertSame(['Canada', 'Portugal'], $record->audience_geos);
        $this->assertNotNull($record->age_confirmed_at);
        $this->assertArrayNotHasKey('submission_key', $record->toArray());
        $this->assertDatabaseCount('users', 1);
        $user = User::sole();
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame('business', $user->role);
        $this->assertSame('business', $user->account_type);
        $this->assertSame('pending', $user->partner_verification_status);
        $this->assertNull($user->partner_verified_at);
        $this->assertNull($user->partner_verified_by);
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertStringNotContainsString('StrongPassword123!', json_encode($record->getAttributes()));
        $this->assertDatabaseCount('creator_profiles', 0);
        $this->assertDatabaseCount('briefs', 0);
        foreach (['/', '/white'] as $url) $this->get($url)->assertOk()->assertDontSee('brand@example.test')->assertDontSee('@brand_contact');
    }

    public function test_agency_budget_and_scope_are_stored_without_inventing_campaign_terms(): void
    {
        $this->post('/talk-to-us', $this->brand(['kind' => 'agency', 'budget' => '2500.50', 'currency' => 'eur', 'streams' => 5, 'budget_scope' => 'per_stream']))
            ->assertSessionHasNoErrors()->assertRedirect('/account/pending');
        $record = PartnerEnquiry::sole();
        $this->assertSame('agency', $record->kind);
        $this->assertSame('agency', $record->user->role);
        $this->assertSame('pending', $record->user->partner_verification_status);
        $this->assertSame('pending', $record->user->agencyProfile->status);
        $this->assertSame(['Canada', 'Portugal'], $record->user->agencyProfile->audience_geos);
        $this->assertSame('2500.50', $record->budget);
        $this->assertSame('EUR', $record->currency);
        $this->assertSame(5, $record->streams);
        $this->assertSame('per_stream', $record->budget_scope);
        $this->assertDatabaseCount('briefs', 0);
    }

    public function test_all_creator_types_apply_their_own_profile_links_and_rates(): void
    {
        foreach (['streamer', 'influencer', 'both'] as $type) {
            Auth::logout();
            $this->post('/talk-to-us', $this->creator($type))->assertSessionHasNoErrors()->assertRedirect('/account/pending');
            $record = PartnerEnquiry::latest('id')->firstOrFail();
            $this->assertSame($type, $record->creator_type);
            $this->assertSame('@creator_contact', $record->telegram);
            $this->assertSame('creator-'.$type.'@example.test', $record->email);
            $this->assertSame('pending', $record->user->partner_verification_status);
            $this->assertSame('creator', $record->user->role);
            $profile = $record->user->creatorProfile;
            $this->assertSame($type, $profile->creator_type);
            $this->assertSame('pending', $profile->status);
            $this->assertSame('0.00', $profile->rate);
            Storage::disk('local')->assertExists($profile->verification_video_path);
            $this->assertSame($type === 'influencer' ? 'Instagram' : 'Twitch', $profile->platform);
            $this->assertSame(['Canada', 'Kyrgyzstan'], $record->audience_geos);
            $this->assertSame($type === 'influencer' ? null : 'https://www.twitch.tv/creator_test', $record->twitch_url);
            $this->assertSame($type === 'streamer' ? null : 'https://www.instagram.com/creator.test', $record->instagram_url);
            $this->assertSame($type === 'influencer' ? null : '500.00', $record->stream_rate);
            $this->assertSame($type === 'streamer' ? null : '100.50', $record->story_rate);
        }
        $this->assertDatabaseCount('partner_enquiries', 3);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_server_rejects_missing_conditional_fields_and_invalid_links_countries_money_or_age(): void
    {
        $invalid = [
            [$this->brand(['age_confirmed' => false]), 'age_confirmed'],
            [$this->brand(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters']), 'password'],
            [$this->brand(['password_confirmation' => 'different']), 'password'],
            [$this->creator('both', ['email' => null]), 'email'],
            [$this->creator('both', ['verification_video' => null]), 'verification_video'],
            [$this->creator('streamer', ['verification_video' => UploadedFile::fake()->create('not-video.pdf', 20, 'application/pdf')]), 'verification_video'],
            [$this->brand(['website' => 'javascript:alert(1)']), 'website'],
            [$this->brand(['website' => 'https://user:password@example.test']), 'website'],
            [$this->brand(['email' => null]), 'email'],
            [$this->brand(['telegram' => 'not valid']), 'telegram'],
            [$this->brand(['audience_geos' => ['Private company name']]), 'audience_geos.0'],
            [$this->brand(['budget' => 500, 'currency' => null]), 'currency'],
            [$this->brand(['kind' => 'agency']), 'streams'],
            [$this->brand(['kind' => 'agency', 'budget' => 500, 'currency' => 'EUR', 'streams' => 5, 'budget_scope' => 'other']), 'budget_scope'],
            [$this->creator('streamer', ['twitch_url' => null]), 'twitch_url'],
            [$this->creator('both', ['instagram_url' => 'https://instagram.com/p/private']), 'instagram_url'],
            [$this->creator('influencer', ['story_rate' => null]), 'story_rate'],
            [$this->creator('both', ['stream_rate' => -1]), 'stream_rate'],
            [$this->creator('both', ['reel_rate' => '10.001']), 'reel_rate'],
            [$this->creator('both', ['currency' => 'BTC']), 'currency'],
            [$this->creator('both', ['audience_geos' => ['Canada']]), 'audience_geos'],
            [$this->creator('both', ['audience_geos' => ['Canada', 'Canada']]), 'audience_geos.0'],
            [$this->brand(['requirements' => '<script>alert(1)</script>']), 'requirements'],
        ];
        foreach ($invalid as [$data, $field]) $this->postJson('/talk-to-us', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('partner_enquiries', 0);
    }

    public function test_duplicate_submissions_do_not_create_duplicates_or_overwrite_the_original(): void
    {
        $data = $this->brand();
        $this->post('/talk-to-us', $data)->assertRedirect();
        Auth::logout();
        $this->postJson('/talk-to-us', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->post('/talk-to-us', array_replace($data, ['name' => 'A different submission', 'email' => 'different@example.test']))->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('partner_enquiries', 1);
        $this->assertSame('Brand Contact', PartnerEnquiry::sole()->name);
    }

    public function test_failed_registration_does_not_flash_passwords_or_store_proof_files(): void
    {
        $data = $this->creator('both', ['password_confirmation' => 'DifferentPassword123!']);
        $this->from('/talk-to-us?type=creator')->post('/talk-to-us', $data)->assertRedirect('/talk-to-us?type=creator')->assertSessionHasErrors('password');
        $old = session('_old_input', []);
        $this->assertArrayNotHasKey('password', $old);
        $this->assertArrayNotHasKey('password_confirmation', $old);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('partner_enquiries', 0);
    }

    public function test_transaction_failure_removes_the_private_video_and_rolls_back_all_account_records(): void
    {
        PartnerEnquiry::creating(fn () => throw new \RuntimeException('Simulated transaction failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post('/talk-to-us', $this->creator('both'));
            $this->fail('Transaction failure did not propagate');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated transaction failure', $exception->getMessage());
        } finally {
            PartnerEnquiry::flushEventListeners();
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
        $this->assertDatabaseCount('partner_enquiries', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertGuest();
    }

    public function test_only_admins_can_review_or_delete_an_enquiry_and_submitted_details_stay_immutable(): void
    {
        $this->post('/talk-to-us', $this->brand())->assertRedirect();
        $record = PartnerEnquiry::sole();
        Auth::logout();
        $this->flushSession();
        foreach (['business', 'creator', 'agency'] as $role) $this->actingAs(User::factory()->create(['role' => $role]))->get(PartnerEnquiryResource::getUrl())->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get(PartnerEnquiryResource::getUrl())->assertOk()->assertSee('Brand Contact');
        Livewire::test(ManagePartnerEnquiries::class)->callTableAction('edit', $record, data: ['status' => 'in_review', 'staff_notes' => 'Contact privately.', 'name' => 'Forged replacement'])
            ->assertHasNoActionErrors();
        $this->assertSame('in_review', $record->fresh()->status);
        $this->assertSame('Contact privately.', $record->fresh()->staff_notes);
        $this->assertSame('Brand Contact', $record->fresh()->name);
        Livewire::test(ManagePartnerEnquiries::class)->callTableAction('delete', $record)->assertHasNoActionErrors();
        $this->assertDatabaseCount('partner_enquiries', 0);
    }
}
