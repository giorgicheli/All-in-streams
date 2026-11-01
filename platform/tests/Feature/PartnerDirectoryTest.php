<?php

namespace Tests\Feature;

use App\Filament\Resources\PartnerResource;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32))]);
        $this->withoutVite();
    }

    public function test_partner_directory_lists_companies_and_agencies_for_staff_only(): void
    {
        $agency = User::factory()->create(['role' => 'business', 'account_type' => 'agency', 'company' => 'Orbit Partner Agency', 'is_demo' => true]);
        $business = User::factory()->create(['role' => 'business', 'account_type' => 'business', 'company' => 'Pixel Harbor Games']);
        $creator = User::factory()->create(['role' => 'creator', 'account_type' => null]);

        foreach ([$agency, $business, $creator] as $user) {
            $this->actingAs($user)->get(PartnerResource::getUrl())->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(PartnerResource::getUrl())->assertOk()
            ->assertSee('Orbit Partner Agency')->assertSee('Pixel Harbor Games');
    }

    public function test_agency_workspace_is_labelled_and_demo_figures_are_disclosed(): void
    {
        $agency = User::factory()->create(['role' => 'business', 'account_type' => 'agency', 'is_demo' => true]);

        $this->actingAs($agency)->get('/dashboard')->assertOk()
            ->assertSee('AGENCY PORTAL')->assertSee('Agency account')->assertSee('Demo workspace');
    }

    public function test_both_channel_urls_stay_private_until_an_introduction_is_authorized(): void
    {
        $this->seed();
        $creator = CreatorProfile::findOrFail(24);
        $creator->update(['twitch_url' => 'https://www.twitch.tv/showcase_north', 'kick_url' => 'https://kick.com/showcase-north']);
        $invitation = Invitation::where('creator_profile_id', $creator->id)->whereNull('introduced_at')->firstOrFail();

        $private = $invitation->forBusiness();
        $this->assertNull($private['twitch_url']);
        $this->assertNull($private['kick_url']);
        $this->assertNull($private['channel_url']);

        $invitation->update(['introduced_at' => now()]);
        $introduced = $invitation->refresh()->forBusiness();
        $this->assertSame($creator->twitch_url, $introduced['twitch_url']);
        $this->assertSame($creator->kick_url, $introduced['kick_url']);
    }
}
