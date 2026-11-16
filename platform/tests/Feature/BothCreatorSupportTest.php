<?php

namespace Tests\Feature;

use App\Filament\Resources\CreatorProfileResource;
use App\Filament\Resources\CreatorProfileResource\Pages\ManageCreators;
use App\Filament\Resources\InfluencerProfileResource;
use App\Filament\Resources\InfluencerProfileResource\Pages\ManageInfluencers;
use App\Livewire\CreatorApplication;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\FakeCreatorProofVideo;
use Tests\TestCase;

class BothCreatorSupportTest extends TestCase
{
    use RefreshDatabase, FakeCreatorProofVideo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        $this->fakeCreatorProofVideos();
    }

    private function profile(array $overrides = [], ?User $owner = null): CreatorProfile
    {
        $owner ??= User::factory()->create(['role' => 'creator', 'telegram' => '@combined_creator']);
        return CreatorProfile::create(array_replace([
            'user_id' => $owner->id, 'creator_type' => 'both', 'public_name' => 'Combined creator '.$owner->id,
            'country' => 'Canada', 'language' => 'English', 'platform' => 'Twitch',
            'channel_url' => 'https://www.twitch.tv/combined_creator',
            'twitch_url' => 'https://www.twitch.tv/combined_creator', 'kick_url' => null,
            'instagram_url' => 'https://www.instagram.com/combined_creator',
            'followers' => 20000, 'average_viewers' => 800, 'audience_geos' => ['Canada', 'Germany'],
            'vertical' => 'Gaming', 'rate' => 750, 'commercial_model' => 'Fixed', 'availability' => 'Open',
            'status' => 'approved', 'bio' => 'Stream and social content.',
        ], $overrides));
    }

    private function staff(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        return $admin;
    }

    private function brief(string $providerType): Brief
    {
        return Brief::create([
            'user_id' => User::factory()->create(['role' => 'business'])->id,
            'title' => 'Private matching brief', 'brand' => 'Private client', 'vertical' => 'Gaming',
            'geos' => ['Canada'], 'language' => 'English', 'platform' => $providerType === 'influencer' ? 'Instagram' : 'Twitch',
            'budget' => 5000, 'currency' => 'EUR', 'commercial_model' => 'Fixed', 'deliverables' => 'Approved private scope',
            'start_date' => today()->addDay(), 'end_date' => today()->addDays(10),
            'status' => 'matching', 'provider_type' => $providerType,
        ]);
    }

    private function application(User $owner)
    {
        return Livewire::actingAs($owner)->test(CreatorApplication::class)
            ->set('creator_type', 'both')->set('country', 'Canada')->set('language', 'English')
            ->set('kick_url', 'https://kick.com/Combined_Creator/')
            ->set('instagram_url', 'https://instagram.com/Combined.Creator/')
            ->set('followers', '20000')->set('average_viewers', '800')
            ->set('audience_geos', ['Canada', 'Germany'])->set('rate', '750');
    }

    public function test_both_profile_is_available_in_both_directories_without_mixing_single_type_profiles(): void
    {
        $both = $this->profile();
        $streamer = $this->profile(['creator_type' => 'streamer', 'instagram_url' => null]);
        $influencer = $this->profile(['creator_type' => 'influencer', 'platform' => 'Instagram', 'twitch_url' => null, 'channel_url' => 'https://www.instagram.com/social_only']);
        $this->staff();

        $this->assertEqualsCanonicalizing([$both->id, $streamer->id], CreatorProfileResource::getEloquentQuery()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$both->id, $influencer->id], InfluencerProfileResource::getEloquentQuery()->pluck('id')->all());
        Livewire::test(ManageCreators::class)->assertCanSeeTableRecords([$both, $streamer])->assertCanNotSeeTableRecords([$influencer]);
        Livewire::test(ManageInfluencers::class)->assertCanSeeTableRecords([$both, $influencer])->assertCanNotSeeTableRecords([$streamer]);
    }

    public function test_editing_a_combined_profile_from_either_directory_preserves_its_type_and_channels(): void
    {
        $both = $this->profile();
        $this->staff();
        foreach ([ManageCreators::class, ManageInfluencers::class] as $index => $page) {
            Livewire::test($page)->callTableAction('edit', $both, data: ['public_name' => 'Edited combined creator '.$index])->assertHasNoActionErrors();
            $both->refresh();
            $this->assertSame('Edited combined creator '.$index, $both->public_name);
            $this->assertSame('both', $both->creator_type);
            $this->assertSame('https://www.twitch.tv/combined_creator', $both->twitch_url);
            $this->assertSame('https://www.instagram.com/combined_creator', $both->instagram_url);
        }
    }

    public function test_combined_creator_account_uses_the_combined_label(): void
    {
        $both = $this->profile();
        $owner = $both->user->fresh();
        $this->assertSame('Streamer & influencer', $owner->account_type_label);
        $this->actingAs($owner)->get('/account/settings')->assertOk()->assertSee('Streamer &amp; influencer account', false);
    }

    public function test_combined_application_exposes_stream_and_social_fields_and_requires_both_channel_kinds(): void
    {
        $both = $this->profile();
        $application = Livewire::actingAs($both->user)->test(CreatorApplication::class)
            ->assertSet('creator_type', 'both')->call('next')->assertSet('step', 2)
            ->assertSee('Twitch channel URL')->assertSee('Kick channel URL')->assertSee('Instagram profile URL');

        $application->set('instagram_url', '')->call('next')->assertHasErrors('instagram_url')->assertSet('step', 2);
        $application->set('instagram_url', 'https://www.instagram.com/combined_creator')
            ->set('twitch_url', '')->set('kick_url', '')->call('next')
            ->assertHasErrors(['twitch_url', 'kick_url'])->assertSet('step', 2);
        $application->set('kick_url', 'https://kick.com/combined_creator')->call('next')->assertHasNoErrors()->assertSet('step', 3)
            ->assertSee('Profile and audience verification video');
    }

    public function test_combined_application_cannot_submit_without_proof_and_saves_all_channels_with_valid_upload(): void
    {
        $owner = User::factory()->create(['role' => 'creator', 'telegram' => '@combined_creator']);
        $application = $this->application($owner);
        $application->call('submit')->assertHasErrors('verification_video');
        $this->assertDatabaseCount('creator_profiles', 0);
        $application->set('verification_video', $this->creatorProofVideo())->call('submit')->assertHasNoErrors()
            ->assertRedirect(route('creator.opportunities'));

        $profile = $owner->fresh()->creatorProfile;
        $this->assertSame('both', $profile->creator_type);
        $this->assertSame('Kick', $profile->platform);
        $this->assertSame('https://kick.com/combined_creator', $profile->kick_url);
        $this->assertSame('https://www.instagram.com/combined.creator', $profile->instagram_url);
        $this->assertSame('pending', $profile->status);
        Storage::disk('local')->assertExists($profile->verification_video_path);
    }

    public function test_changing_a_streamer_to_both_requires_new_proof_instead_of_reusing_the_old_video(): void
    {
        Storage::disk('local')->put('creator-proof/streamer-old.mp4', 'existing private proof');
        $profile = $this->profile(['creator_type' => 'streamer', 'instagram_url' => null, 'verification_video_path' => 'creator-proof/streamer-old.mp4']);
        $this->application($profile->user)->call('submit')->assertHasErrors('verification_video');
        $this->assertSame('streamer', $profile->fresh()->creator_type);
        Storage::disk('local')->assertExists('creator-proof/streamer-old.mp4');
    }

    public function test_pending_creator_accounts_cannot_mount_the_profile_application(): void
    {
        $owner = User::factory()->pendingPartner()->create(['role' => 'creator']);
        $this->profile([], $owner);
        Livewire::actingAs($owner)->test(CreatorApplication::class)->assertForbidden();
    }

    public function test_approved_verified_combined_creator_can_be_matched_to_streamer_and_influencer_briefs(): void
    {
        $admin = $this->staff();
        $profile = $this->profile();
        $workflow = app(CampaignWorkflow::class);
        foreach (['streamer', 'influencer', 'creator', 'either'] as $providerType) {
            $offer = $workflow->invite($this->brief($providerType), $profile, $admin);
            $this->assertSame($profile->id, $offer->creator_profile_id);
            $this->assertTrue($offer->fresh()->recipientApproved());
        }
        $this->assertDatabaseCount('invitations', 4);
    }

    public function test_combined_type_does_not_bypass_profile_approval_or_account_verification(): void
    {
        $admin = $this->staff();
        $profile = $this->profile();
        $workflow = app(CampaignWorkflow::class);
        $offer = $workflow->invite($this->brief('streamer'), $profile, $admin);
        $profile->user->forceFill(['partner_verification_status' => 'pending', 'partner_verified_at' => null])->save();
        $this->assertFalse($offer->fresh()->recipientApproved());
        try {
            $workflow->invite($this->brief('influencer'), $profile, $admin);
            $this->fail('Unverified combined creator was invited.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('creator_profile_id', $exception->errors());
        }
        $profile->user->forceFill(['partner_verification_status' => 'verified', 'partner_verified_at' => now()])->save();
        $profile->update(['status' => 'pending']);
        try {
            $workflow->invite($this->brief('influencer'), $profile, $admin);
            $this->fail('Unapproved combined profile was invited.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('creator_profile_id', $exception->errors());
        }
        $this->assertDatabaseCount('invitations', 1);
    }
}