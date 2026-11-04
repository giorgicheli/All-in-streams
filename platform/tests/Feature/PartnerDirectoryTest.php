<?php

namespace Tests\Feature;

use App\Filament\Resources\PartnerResource;
use App\Filament\Resources\AgencyResource;
use App\Models\AgencyProfile;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CampaignWorkflow;
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

    public function test_businesses_and_agencies_have_distinct_staff_directories(): void
    {
        $agency = User::factory()->create(['role' => 'agency', 'account_type' => 'agency', 'company' => 'Orbit Partner Agency', 'is_demo' => true]);
        AgencyProfile::create(['user_id'=>$agency->id,'services'=>['supply','request'],'country'=>'Germany','languages'=>['German'],'audience_geos'=>['Germany'],'platforms'=>['Twitch'],'status'=>'approved']);
        $business = User::factory()->create(['role' => 'business', 'account_type' => 'business', 'company' => 'Pixel Harbor Games']);
        $creator = User::factory()->create(['role' => 'creator', 'account_type' => null]);

        foreach ([$agency, $business, $creator] as $user) {
            $this->actingAs($user)->get(PartnerResource::getUrl())->assertForbidden();
            $this->get(AgencyResource::getUrl())->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(PartnerResource::getUrl())->assertOk()
            ->assertDontSee('Orbit Partner Agency')->assertSee('Pixel Harbor Games');
        $this->get(AgencyResource::getUrl())->assertOk()->assertSee('Orbit Partner Agency')->assertDontSee('Pixel Harbor Games');
    }

    public function test_agency_workspace_is_labelled_and_demo_figures_are_disclosed(): void
    {
        $agency = User::factory()->create(['role' => 'agency', 'account_type' => 'agency', 'is_demo' => true]);

        $this->actingAs($agency)->get('/agency/dashboard')->assertOk()
            ->assertSee('AGENCY PORTAL')->assertSee('Agency account')->assertSee('Demo workspace');
    }

    public function test_both_channel_urls_stay_private_even_with_legacy_introduction(): void
    {
        $this->seed();
        $creator = CreatorProfile::findOrFail(24);
        $creator->update(['twitch_url' => 'https://www.twitch.tv/showcase_north', 'kick_url' => 'https://kick.com/showcase-north']);
        $invitation = Invitation::where('creator_profile_id', $creator->id)->whereNull('introduced_at')->firstOrFail();
        $invitation = app(CampaignWorkflow::class)->respond($invitation, $creator->user, 'interested', offerVersion: $invitation->offer_version);

        $private = $invitation->forBusiness();
        $this->assertNull($private['twitch_url']);
        $this->assertNull($private['kick_url']);
        $this->assertNull($private['channel_url']);

        app(CampaignWorkflow::class)->introduce($invitation, User::where('role','admin')->firstOrFail());
        $introduced = $invitation->refresh()->forBusiness();
        $this->assertNull($introduced['twitch_url']);
        $this->assertNull($introduced['kick_url']);
    }
}
