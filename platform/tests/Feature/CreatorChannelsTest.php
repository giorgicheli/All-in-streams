<?php

namespace Tests\Feature;

use App\Livewire\CreatorApplication;
use App\Models\CreatorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreatorChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }

    private function registration(array $values = []): array
    {
        return array_replace([
            'name' => 'New Streamer', 'email' => 'streamer@example.test', 'role' => 'creator',
            'password' => 'StrongChannel123!', 'password_confirmation' => 'StrongChannel123!',
            'twitch_url' => 'https://twitch.tv/Stream_Person/',
        ], $values);
    }

    public function test_creator_signup_saves_both_canonical_channel_links_in_a_draft_profile(): void
    {
        $this->post('/register', $this->registration(['kick_url' => 'https://www.kick.com/Stream_Person/']))
            ->assertSessionHasNoErrors()->assertRedirect('/creator/apply');

        $user = User::where('email', 'streamer@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->account_type);
        $this->assertDatabaseHas('creator_profiles', [
            'user_id' => $user->id, 'status' => 'draft',
            'twitch_url' => 'https://www.twitch.tv/stream_person',
            'kick_url' => 'https://kick.com/stream_person',
            'platform' => 'Twitch', 'channel_url' => 'https://www.twitch.tv/stream_person',
            'country' => '', 'language' => '', 'followers' => 0,
        ]);
    }

    public function test_a_kick_only_creator_can_register(): void
    {
        $this->post('/register', $this->registration(['twitch_url' => '', 'kick_url' => 'https://kick.com/kick_person']))
            ->assertSessionHasNoErrors()->assertRedirect('/creator/apply');

        $this->assertDatabaseHas('creator_profiles', [
            'platform' => 'Kick', 'channel_url' => 'https://kick.com/kick_person',
            'kick_url' => 'https://kick.com/kick_person', 'twitch_url' => null, 'status' => 'draft',
        ]);
    }

    public function test_creator_signup_requires_at_least_one_channel_and_does_not_create_partial_accounts(): void
    {
        $this->post('/register', $this->registration(['twitch_url' => '', 'kick_url' => '']))
            ->assertSessionHasErrors(['twitch_url', 'kick_url']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    #[DataProvider('invalidChannelLinks')]
    public function test_signup_rejects_unrelated_or_non_channel_links(string $field, string $url): void
    {
        $this->post('/register', $this->registration(['twitch_url' => '', $field => $url]))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public static function invalidChannelLinks(): array
    {
        return [
            'unrelated host' => ['twitch_url', 'https://example.com/streamer'],
            'spoofed host' => ['twitch_url', 'https://twitch.tv.example.com/streamer'],
            'credential host confusion' => ['twitch_url', 'https://twitch.tv@evil.example/streamer'],
            'credentials on actual host' => ['twitch_url', 'https://user:password@twitch.tv/streamer'],
            'plain HTTP' => ['twitch_url', 'http://twitch.tv/streamer'],
            'provider root' => ['twitch_url', 'https://twitch.tv/'],
            'browse route' => ['twitch_url', 'https://www.twitch.tv/directory'],
            'video route' => ['twitch_url', 'https://www.twitch.tv/videos/1234'],
            'unexpected port' => ['twitch_url', 'https://www.twitch.tv:444/streamer'],
            'query redirect' => ['twitch_url', 'https://www.twitch.tv/streamer?redirect=https://evil.example'],
            'kick host mismatch' => ['kick_url', 'https://www.twitch.tv/streamer'],
            'kick browse route' => ['kick_url', 'https://kick.com/categories'],
        ];
    }

    public function test_agency_registration_uses_business_permissions_and_keeps_its_account_type(): void
    {
        $this->post('/register', $this->registration([
            'role' => 'business', 'account_type' => 'agency', 'company' => 'Northstar Talent',
            'twitch_url' => 'https://unrelated.example/channel',
        ]))->assertSessionHasNoErrors()->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'streamer@example.test', 'role' => 'business', 'account_type' => 'agency', 'company' => 'Northstar Talent']);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public function test_company_registration_defaults_to_business_and_ignores_creator_links(): void
    {
        $this->post('/register', $this->registration(['role' => 'business']))
            ->assertSessionHasNoErrors()->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'streamer@example.test', 'role' => 'business', 'account_type' => 'business']);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public function test_draft_profile_completion_preserves_both_links_and_submits_for_review(): void
    {
        $this->post('/register', $this->registration(['kick_url' => 'https://kick.com/stream_person']))->assertSessionHasNoErrors();
        $user = User::where('email', 'streamer@example.test')->firstOrFail();

        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->assertSet('twitch_url', 'https://www.twitch.tv/stream_person')
            ->assertSet('kick_url', 'https://kick.com/stream_person')
            ->set('country', 'Georgia')->set('language', 'Georgian')
            ->set('followers', '17500')->set('average_viewers', '650')
            ->set('audience_geos', ['Georgia'])->set('rate', '500')
            ->call('submit')->assertHasNoErrors()->assertRedirect(route('creator.opportunities'));

        $this->assertDatabaseHas('creator_profiles', [
            'user_id' => $user->id, 'status' => 'pending', 'country' => 'Georgia',
            'twitch_url' => 'https://www.twitch.tv/stream_person', 'kick_url' => 'https://kick.com/stream_person',
        ]);
    }

    public function test_existing_other_platform_profiles_can_still_be_completed(): void
    {
        $user = User::factory()->create(['role' => 'creator']);

        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('country', 'Georgia')->set('platform', 'YouTube')->set('channel_url', 'https://www.youtube.com/@creator_channel')
            ->set('followers', '12000')->set('average_viewers', '420')->set('audience_geos', ['Georgia'])->set('rate', '350')
            ->call('submit')->assertHasNoErrors();

        $this->assertDatabaseHas('creator_profiles', ['user_id' => $user->id, 'platform' => 'YouTube', 'status' => 'pending']);
        $this->assertNull(CreatorProfile::where('user_id', $user->id)->firstOrFail()->twitch_url);
    }

    public function test_application_validates_each_added_streaming_link(): void
    {
        $user = User::factory()->create(['role' => 'creator']);

        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('country', 'Georgia')->call('next')->assertSet('step', 2)
            ->set('twitch_url', 'https://www.twitch.tv/stream_person')
            ->set('kick_url', 'https://example.com/fake_kick')->call('next')
            ->assertHasErrors('kick_url')->assertSet('step', 2);
    }

    public function test_legacy_primary_channel_must_match_its_streaming_platform(): void
    {
        $user = User::factory()->create(['role' => 'creator']);

        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('country', 'Georgia')->call('next')->assertSet('step', 2)
            ->set('channel_url', 'https://example.com/creator')->call('next')
            ->assertHasErrors('channel_url')->assertSet('step', 2);
    }

    public function test_valid_legacy_primary_channel_is_saved_into_its_platform_field(): void
    {
        $user = User::factory()->create(['role' => 'creator']);

        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('country', 'Georgia')->set('channel_url', 'https://twitch.tv/Legacy_Person/')
            ->set('followers', '10000')->set('average_viewers', '300')->set('audience_geos', ['Georgia'])->set('rate', '250')
            ->call('submit')->assertHasNoErrors();

        $this->assertDatabaseHas('creator_profiles', ['user_id' => $user->id, 'twitch_url' => 'https://www.twitch.tv/legacy_person', 'channel_url' => 'https://www.twitch.tv/legacy_person']);
    }

    public function test_removing_both_signup_channels_requires_a_replacement_instead_of_reusing_the_old_primary(): void
    {
        $this->post('/register', $this->registration())->assertSessionHasNoErrors();
        $user = User::where('email', 'streamer@example.test')->firstOrFail();

        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('country', 'Georgia')->set('language', 'Georgian')->call('next')->assertSet('step', 2)
            ->set('twitch_url', '')->set('kick_url', '')->call('next')
            ->assertHasErrors('channel_url')->assertSet('step', 2)->assertSet('channel_url', '');
    }

    public function test_only_kick_link_switches_the_primary_platform_when_twitch_was_selected(): void
    {
        $user = User::factory()->create(['role' => 'creator']);

        Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('country', 'Georgia')->call('next')->assertSet('step', 2)
            ->set('kick_url', 'https://kick.com/new_person')->call('next')
            ->assertHasNoErrors()->assertSet('step', 3)->assertSet('platform', 'Kick')->assertSet('channel_url', 'https://kick.com/new_person');
    }
}
