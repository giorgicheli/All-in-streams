<?php

namespace Tests\Feature;

use App\Livewire\CreatorApplication;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Rules\SocialProfileUrl;
use App\Rules\TelegramHandle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakeCreatorProofVideo;
use Tests\TestCase;

class CreatorIdentityRegistrationTest extends TestCase
{
    use RefreshDatabase, FakeCreatorProofVideo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        $this->fakeCreatorProofVideos();
    }

    private function registration(array $values = []): array
    {
        return array_replace([
            'name' => 'Social Creator', 'email' => 'social@example.test', 'role' => 'creator', 'creator_type' => 'influencer',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!', 'telegram' => 'https://t.me/Social_Creator',
            'instagram_url' => 'https://instagram.com/Social.Creator/', 'audience_geos' => ['Canada', 'United States'], 'verification_video' => $this->creatorProofVideo(),
        ], $values);
    }

    public function test_influencer_registers_with_social_profile_countries_private_video_and_telegram(): void
    {
        $this->post('/register', $this->registration(['facebook_url' => 'https://facebook.com/social.creator', 'twitch_url' => 'https://evil.test/not-used']))->assertSessionHasNoErrors()->assertRedirect('/creator/apply');
        $user = User::where('email', 'social@example.test')->sole();
        $profile = $user->creatorProfile;
        $this->assertSame('@social_creator', $user->telegram);
        $this->assertSame('influencer', $profile->creator_type);
        $this->assertSame('https://www.instagram.com/social.creator', $profile->instagram_url);
        $this->assertSame('Facebook', $profile->platform);
        $this->assertSame(['Canada', 'United States'], $profile->audience_geos);
        $this->assertNull($profile->twitch_url);
        Storage::disk('local')->assertExists($profile->verification_video_path);
        $this->assertStringStartsWith('creator-proof/', $profile->verification_video_path);
        $this->assertSame('profile-and-audience.mp4', $profile->verification_video_original_name);
        $this->assertSame('Influencer', $user->account_type_label);
    }

    public function test_influencer_cannot_register_using_only_a_streaming_channel(): void
    {
        $this->post('/register', $this->registration(['instagram_url' => '', 'twitch_url' => 'https://twitch.tv/streamer']))->assertSessionHasErrors(['facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url']);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_streamer_cannot_register_using_only_a_social_profile(): void
    {
        $this->post('/register', $this->registration(['creator_type' => 'streamer']))->assertSessionHasErrors(['twitch_url', 'kick_url']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_creator_cannot_register_without_video_or_audience_countries(): void
    {
        $this->post('/register', $this->registration(['verification_video' => null, 'audience_geos' => []]))->assertSessionHasErrors(['verification_video', 'audience_geos']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public function test_a_document_cannot_be_uploaded_as_a_verification_video(): void
    {
        $this->post('/register', $this->registration(['verification_video' => UploadedFile::fake()->create('not-a-video.pdf', 20, 'application/pdf')]))->assertSessionHasErrors('verification_video');
        $this->assertDatabaseCount('users', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_every_public_role_requires_telegram_before_an_account_is_created(): void
    {
        foreach (['business', 'agency', 'creator'] as $role) {
            $this->post('/register', $this->registration(['role' => $role, 'telegram' => '']))->assertSessionHasErrors('telegram');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_malformed_registration_parameters_return_validation_errors_without_creating_accounts(): void
    {
        $business = $this->registration(['role'=>'business']);
        unset($business['verification_video']);
        foreach (['email'=>['user@example.test'], 'role'=>['admin'], 'account_type'=>['agency']] as $field=>$value) {
            $this->postJson('/register', array_replace($business, [$field=>$value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/register', array_replace($business, ['role'=>'creator', 'creator_type'=>['streamer']]))->assertUnprocessable()->assertJsonValidationErrors('creator_type');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public function test_telegram_normalization_rejects_invites_phones_and_spoofed_hosts(): void
    {
        $this->assertSame('@creator_team', TelegramHandle::canonicalize(' https://t.me/Creator_Team/ '));
        foreach (['https://t.me.evil.test/creator', 'https://t.me/+invite', '+995555123456', '@@creator', '@abc', 'https://t.me/user?x=1'] as $invalid) $this->assertNull(TelegramHandle::canonicalize($invalid));
    }

    #[DataProvider('socialProfiles')]
    public function test_social_profile_validation_is_limited_to_real_platform_profile_paths(string $platform, string $url, ?string $expected): void
    {
        $this->assertSame($expected, SocialProfileUrl::canonicalize($url, $platform));
    }

    public static function socialProfiles(): array
    {
        return [
            ['Instagram', 'https://www.instagram.com/person.name/', 'https://www.instagram.com/person.name'],
            ['Facebook', 'https://facebook.com/profile.php?id=123456789', 'https://www.facebook.com/profile.php?id=123456789'],
            ['YouTube', 'https://youtube.com/@Creator123', 'https://www.youtube.com/@Creator123'],
            ['TikTok', 'https://tiktok.com/@creator.name', 'https://www.tiktok.com/@creator.name'],
            ['Instagram', 'https://instagram.com.example.test/person', null],
            ['Instagram', 'https://instagram.com/p/123', null],
            ['Facebook', 'https://facebook.com/watch', null],
            ['Facebook', 'https://facebook.com/user?redirect=evil', null],
            ['YouTube', 'https://youtube.com/watch?v=123', null],
            ['TikTok', 'https://tiktok.com/@creator/video/123', null],
            ['TikTok', 'http://tiktok.com/@creator', null],
        ];
    }

    public function test_deleting_proof_requires_a_new_video_before_profile_resubmission(): void
    {
        $this->post('/register', $this->registration())->assertSessionHasNoErrors();
        $user = User::where('email', 'social@example.test')->sole();
        $profile = $user->creatorProfile;
        Storage::disk('local')->delete($profile->verification_video_path);
        $profile->update(['verification_video_path' => null, 'verification_video_original_name' => null]);
        Livewire::actingAs($user->fresh())->test(CreatorApplication::class)
            ->set('country', 'Canada')->set('language', 'English')->set('followers', '20000')->set('average_viewers', '500')->set('rate', '600')
            ->call('submit')->assertHasErrors('verification_video');
        $this->assertSame('draft', $profile->refresh()->status);
    }

    public function test_switching_creator_type_requires_appropriate_channels_and_a_new_proof_video(): void
    {
        $this->post('/register', $this->registration())->assertSessionHasNoErrors();
        $user = User::where('email', 'social@example.test')->sole();
        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('creator_type', 'streamer')->set('twitch_url', 'https://twitch.tv/social_creator')
            ->set('country', 'Canada')->set('language', 'English')->set('followers', '20000')->set('average_viewers', '500')->set('rate', '600')
            ->call('submit')->assertHasErrors('verification_video');
        $this->assertSame('influencer', $user->creatorProfile->refresh()->creator_type);
    }
}
