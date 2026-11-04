<?php

namespace Tests\Feature;

use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountContactAndMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_update_only_its_own_private_telegram_contact(): void
    {
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $other = User::factory()->create(['telegram' => '@untouched_contact']);
        foreach (['admin', 'business', 'agency', 'creator'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('account.settings'))->assertOk();
            $this->post(route('account.settings.update'), ['telegram' => 'https://t.me/Example_'.$role, 'user_id' => $other->id, 'role' => 'admin'])
                ->assertSessionHasNoErrors()->assertRedirect(route('account.settings'));
            $this->assertSame('@example_'.$role, $actor->fresh()->telegram);
            $this->assertSame($role, $actor->fresh()->role);
            $this->assertSame('@untouched_contact', $other->fresh()->telegram);
            $this->post(route('account.settings.update'), ['telegram' => 'https://evil.test/example'])->assertSessionHasErrors('telegram');
        }
    }

    public function test_streamer_and_influencer_briefs_cannot_be_sent_to_the_wrong_creator_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'business']);
        $workflow = app(CampaignWorkflow::class);
        foreach (['streamer', 'influencer'] as $type) {
            $profile = CreatorProfile::create([
                'user_id' => User::factory()->create(['role' => 'creator'])->id,
                'creator_type' => $type, 'public_name' => ucfirst($type).' sample', 'country' => 'Canada', 'language' => 'English',
                'platform' => $type === 'streamer' ? 'Twitch' : 'Instagram', 'channel_url' => 'https://example.test/profile',
                'audience_geos' => ['Canada'], 'vertical' => 'Gaming', 'rate' => 450, 'commercial_model' => 'Fixed', 'status' => 'approved',
            ]);
            $base = ['user_id' => $owner->id, 'title' => 'Private sample brief', 'brand' => 'Private sample brand', 'vertical' => 'Gaming',
                'geos' => ['Canada'], 'language' => 'English', 'platform' => $profile->platform, 'budget' => 2000, 'currency' => 'EUR',
                'commercial_model' => 'Fixed', 'deliverables' => 'Sample delivery', 'start_date' => today(), 'end_date' => today()->addDays(4), 'status' => 'matching'];
            $wrong = Brief::create($base + ['provider_type' => $type === 'streamer' ? 'influencer' : 'streamer']);
            try {
                $workflow->invite($wrong, $profile, $admin);
                $this->fail('A mismatched provider type must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('brief_id', $exception->errors());
            }
            $matching = Brief::create($base + ['provider_type' => $type]);
            $this->assertSame($profile->id, $workflow->invite($matching, $profile, $admin)->creator_profile_id);
            $legacyAnyCreator = Brief::create($base + ['provider_type' => 'creator']);
            $this->assertSame($profile->id, $workflow->invite($legacyAnyCreator, $profile, $admin)->creator_profile_id);
        }
    }
}
