<?php

namespace Tests\Feature;

use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $business;
    private Brief $brief;
    private CampaignWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->business = User::factory()->create(['role' => 'business', 'name' => 'CLIENT_OWNER_SENTINEL', 'company' => 'CLIENT_COMPANY_SENTINEL', 'email' => 'client-sentinel@example.test']);
        $this->brief = Brief::create([
            'user_id' => $this->business->id, 'title' => 'CLIENT_TITLE_SENTINEL', 'brand' => 'CLIENT_BRAND_SENTINEL',
            'vertical' => 'Gaming', 'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch',
            'budget' => 876543.21, 'currency' => 'EUR', 'commercial_model' => 'Fixed + CPA',
            'deliverables' => 'CLIENT_DELIVERABLES_SENTINEL', 'notes' => 'CLIENT_NOTES_SENTINEL', 'kpis' => 'CLIENT_KPI_SENTINEL',
            'attachment_path' => 'private-briefs/CLIENT_ATTACHMENT_SENTINEL.pdf',
            'start_date' => today()->addDay(), 'end_date' => today()->addDays(20), 'status' => 'matching',
        ]);
        $this->workflow = app(CampaignWorkflow::class);
    }

    private function creator(): CreatorProfile
    {
        $user = User::factory()->create(['role' => 'creator']);
        return CreatorProfile::create([
            'user_id' => $user->id, 'public_name' => 'PROVIDER_IDENTITY_SENTINEL_'.$user->id, 'country' => 'Germany',
            'language' => 'German', 'platform' => 'Twitch', 'channel_url' => 'https://www.twitch.tv/provider_'.$user->id,
            'twitch_url' => 'https://www.twitch.tv/provider_'.$user->id, 'audience_geos' => ['Germany'],
            'vertical' => 'Gaming', 'rate' => 4321.98, 'commercial_model' => 'Fixed', 'status' => 'approved',
        ]);
    }

    private function assertNoClientDetails(array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        foreach (['CLIENT_', 'client-sentinel@example.test', '876543', '876,543', 'private-briefs/'] as $private) $this->assertStringNotContainsString($private, $json);
        foreach (['brief', 'brief_id', 'brand', 'budget', 'kpis', 'user', 'email', 'attachment_path'] as $key) $this->assertArrayNotHasKey($key, $data);
    }

    public function test_recipient_dto_whitelists_terms_and_redacts_client_identity_in_accidental_shared_text_and_thread(): void
    {
        $creator = $this->creator();
        $offer = $this->workflow->invite($this->brief, $creator, $this->admin, [
            'compensation' => 1234.56, 'cpa_rate' => 25,
            'opportunity_title' => 'Gaming for CLIENT_BRAND_SENTINEL', 'deliverables' => '4 streams for CLIENT_COMPANY_SENTINEL',
            'requirements' => 'Review CLIENT_TITLE_SENTINEL privately', 'notes' => 'Ask CLIENT_OWNER_SENTINEL at client-sentinel@example.test',
        ]);
        $data = $offer->forRecipient($creator->user);
        $this->assertNoClientDetails($data);
        $this->assertSame('1234.56', $data['compensation']);
        $this->assertSame('25.00', $data['cpa_rate']);
        $this->assertStringContainsString('[client]', $data['notes']);
        $this->assertSame('All In Streams', $data['history'][0]['actor']);
        $this->actingAs($creator->user)->get(route('creator.opportunities'))->assertOk()->assertSee('1,234')->assertDontSee('CLIENT_')->assertDontSee('876,543')->assertDontSee('client-sentinel@example.test');
    }

    public function test_offer_snapshot_survives_private_brief_edits_without_releasing_new_client_requirements(): void
    {
        $creator = $this->creator();
        $offer = $this->workflow->invite($this->brief, $creator, $this->admin);
        $original = $offer->forRecipient($creator->user);
        $this->brief->update(['vertical' => 'Esports', 'geos' => ['France'], 'language' => 'French', 'platform' => 'Kick', 'start_date' => today()->addDays(5), 'deliverables' => 'CHANGED_CLIENT_DELIVERABLES_SENTINEL']);
        $current = $offer->fresh()->forRecipient($creator->user);
        foreach (['vertical', 'geos', 'language', 'platform', 'start_date', 'end_date', 'deliverables'] as $field) $this->assertSame($original[$field], $current[$field]);
        $this->assertNoClientDetails($current);
    }

    public function test_business_never_receives_private_provider_compensation_or_negotiation_even_after_introduction(): void
    {
        $creator = $this->creator();
        $offer = $this->workflow->invite($this->brief, $creator, $this->admin, ['compensation' => 1234.56]);
        $counter = $this->workflow->respond($offer, $creator->user, 'changes_requested', 'PRIVATE_PROVIDER_COUNTER_SENTINEL', 2345.67, 37, 1);
        $accepted = $this->workflow->acceptCounter($counter, $this->admin);
        $this->workflow->transition($this->brief, 'proposal', $this->admin);
        $data = $accepted->forBusiness();
        $this->assertNull($data['public_name']);
        $this->assertNull($data['channel_url']);
        foreach (['rate', 'compensation', 'cpa_rate', 'counter_amount', 'response_notes', 'history', 'notes', 'agency_user_id', 'user_id'] as $field) $this->assertArrayNotHasKey($field, $data);
        $page = $this->actingAs($this->business)->get(route('proposals'))->assertOk();
        foreach (['PROVIDER_IDENTITY_SENTINEL', 'PRIVATE_PROVIDER_COUNTER_SENTINEL', '2345.67', '2,345', '4321.98', '4,321'] as $private) $page->assertDontSee($private);
        $selected = $this->workflow->selectProvider($accepted, $this->admin);
        $this->assertSame($creator->public_name, $selected->forBusiness()['public_name']);
        $this->get(route('proposals'))->assertOk()->assertSee($creator->public_name)->assertDontSee('PRIVATE_PROVIDER_COUNTER_SENTINEL')->assertDontSee('2,345');
        $this->assertNoClientDetails($selected->forRecipient($creator->user));
    }

    public function test_agency_views_are_private_and_independent_from_its_client_request_side(): void
    {
        $agency = User::factory()->create(['role' => 'agency', 'account_type' => 'agency']);
        AgencyProfile::create(['user_id' => $agency->id, 'services' => ['supply', 'request'], 'status' => 'approved', 'country' => 'Germany', 'languages' => ['German'], 'audience_geos' => ['Germany'], 'platforms' => ['Twitch']]);
        $talent = AgencyTalent::create(['agency_user_id' => $agency->id, 'public_name' => 'Roster streamer', 'country' => 'Germany', 'language' => 'German', 'platform' => 'Twitch', 'twitch_url' => 'https://www.twitch.tv/roster_streamer', 'audience_geos' => ['Germany'], 'status' => 'approved']);
        $agencyOffer = $this->workflow->inviteAgency($this->brief, $agency, $this->admin, ['compensation' => 1357.91]);
        $creator = $this->creator();
        $creatorOffer = $this->workflow->invite($this->brief, $creator, $this->admin, ['compensation' => 2468.13]);
        $this->assertNoClientDetails($agencyOffer->forRecipient($agency));
        $this->actingAs($agency)->get(route('agency.opportunities'))->assertOk()->assertSee('1,357')->assertDontSee('2,468')->assertDontSee('CLIENT_')->assertDontSee('876,543');
        $this->post(route('agency.respond', $creatorOffer), ['status' => 'interested', 'offer_version' => 1])->assertForbidden();
        $this->post(route('agency.respond', $agencyOffer), ['status' => 'interested', 'offer_version' => 1, 'proposed_talent_ids' => [$talent->id]])->assertRedirect();
        $this->assertSame('interested', $agencyOffer->fresh()->status);
        $this->assertSame('pending', $creatorOffer->fresh()->status);
    }

    public function test_business_scope_excludes_stale_acceptances_without_exposing_provider_details(): void
    {
        $creator = $this->creator();
        $offer = $this->workflow->invite($this->brief, $creator, $this->admin);
        $this->workflow->respond($offer, $creator->user, 'interested', offerVersion: 1);
        $offer->refresh()->update(['offer_version' => 2]);
        $this->brief->update(['status' => 'proposal']);
        $this->assertSame(0, Invitation::eligibleForBusiness()->count());
        $this->actingAs($this->business)->get(route('proposals'))->assertOk()->assertDontSee($creator->code)->assertDontSee($creator->public_name);
    }
}
