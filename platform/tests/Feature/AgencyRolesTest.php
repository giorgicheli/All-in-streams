<?php

namespace Tests\Feature;

use App\Filament\Resources\AgencyResource;
use App\Filament\Resources\AgencyResource\Pages\ListAgencies;
use App\Filament\Resources\AgencyResource\Pages\ViewAgency;
use App\Filament\Resources\AgencyResource\RelationManagers\TalentsRelationManager;
use App\Filament\Resources\PartnerResource;
use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Brief;
use App\Models\Invitation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('g', 32))]);
        $this->withoutVite();
    }

    public function test_agency_registration_opens_a_separate_workspace_and_draft_profile(): void
    {
        $this->post('/register', [
            'name' => 'Agency Owner', 'company' => 'Orbit Talent', 'email' => 'agency@example.test',
            'role' => 'agency', 'password' => 'StrongAgency123!', 'password_confirmation' => 'StrongAgency123!',
            'telegram' => '@orbit_agency',
        ])->assertSessionHasNoErrors()->assertRedirect('/agency/dashboard');

        $agency = User::where('email', 'agency@example.test')->sole();
        $this->assertSame('agency', $agency->role);
        $this->assertSame(['supply', 'request'], $agency->agencyProfile->services);
        $this->assertDatabaseHas('agency_profiles', ['user_id' => $agency->id, 'status' => 'draft']);
        $this->assertDatabaseCount('creator_profiles', 0);
        $this->get('/agency/dashboard')->assertOk()->assertSee('AGENCY PORTAL')->assertSee('Supply roster talent');
        $this->get('/account')->assertRedirect('/agency/dashboard');
        $this->get('/dashboard')->assertForbidden();
        $this->get('/creator/opportunities')->assertForbidden();
    }

    public function test_only_agencies_enter_the_agency_workspace_and_admin_directory_is_separate(): void
    {
        $agency = $this->agency();
        $business = User::factory()->create(['role' => 'business', 'company' => 'Aster Games']);
        $creator = User::factory()->create(['role' => 'creator']);
        foreach ([$business, $creator] as $actor) {
            foreach (['/agency/dashboard', '/agency/profile', '/agency/roster', '/agency/opportunities'] as $url) {
                $this->actingAs($actor)->get($url)->assertForbidden();
            }
        }
        foreach ([$agency, $business, $creator] as $actor) {
            $this->actingAs($actor)->get(AgencyResource::getUrl())->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(AgencyResource::getUrl())->assertOk()->assertSee($agency->company)->assertDontSee('Aster Games');
        $this->get(PartnerResource::getUrl())->assertOk()->assertSee('Aster Games')->assertDontSee($agency->company);
    }

    public function test_profile_changes_return_an_approved_agency_to_staff_review(): void
    {
        $agency = $this->agency();
        $this->actingAs($agency)->post('/agency/profile', [
            'company' => 'Orbit Talent Updated', 'services' => ['supply', 'request'], 'country' => 'Germany',
            'website' => 'https://agency.example.test', 'languages' => 'German, English',
            'audience_geos' => 'Germany, Austria', 'platforms' => ['Twitch', 'Kick'], 'bio' => 'A talent management team.',
            'status' => 'approved',
        ])->assertSessionHasNoErrors()->assertRedirect(route('agency.profile'));

        $this->assertSame('pending', $agency->agencyProfile->refresh()->status);
        $this->assertSame(['German', 'English'], $agency->agencyProfile->languages);
        $this->assertSame('Orbit Talent Updated', $agency->refresh()->company);
    }

    public function test_agency_pages_render_both_roster_and_private_client_tools(): void
    {
        $agency = $this->agency();
        $talent = $this->talent($agency);
        $this->actingAs($agency);
        foreach (['/agency/profile','/agency/roster','/agency/roster?edit='.$talent->id,'/agency/briefs/create','/agency/campaigns','/agency/proposals','/documents','/provider/deliveries','/provider/payments'] as $url) {
            $this->get($url)->assertOk()->assertSee('AGENCY PORTAL');
        }
    }

    public function test_agency_roster_is_reviewed_owned_and_separate_from_creator_accounts(): void
    {
        $agency = $this->agency();
        $this->actingAs($agency)->post('/agency/roster', $this->talentData(['status' => 'approved']))
            ->assertSessionHasNoErrors()->assertRedirect(route('agency.roster'));
        $talent = $agency->agencyTalent()->sole();
        $this->assertSame('pending', $talent->status);
        $this->assertSame('https://www.twitch.tv/roster_stream', $talent->twitch_url);
        $this->assertDatabaseCount('creator_profiles', 0);
        $this->assertDatabaseCount('users', 1);

        $talent->update(['status' => 'approved']);
        $this->patch('/agency/roster/'.$talent->id, $this->talentData(['public_name' => 'Updated Talent']))->assertSessionHasNoErrors();
        $this->assertSame('pending', $talent->refresh()->status);
        $other = $this->agency('otheragency@example.test');
        $this->actingAs($other)->patch('/agency/roster/'.$talent->id, $this->talentData())->assertForbidden();
        $this->delete('/agency/roster/'.$talent->id)->assertForbidden();
        $this->get('/agency/roster?edit='.$talent->id)->assertNotFound();
    }

    public function test_roster_requires_real_platform_channel_links(): void
    {
        $agency = $this->agency();
        $this->actingAs($agency)->post('/agency/roster', $this->talentData(['twitch_url' => '', 'kick_url' => '']))
            ->assertSessionHasErrors(['twitch_url', 'kick_url']);
        $this->post('/agency/roster', $this->talentData(['twitch_url' => 'https://twitch.tv.example.test/stream']))->assertSessionHasErrors('twitch_url');
        $this->assertDatabaseCount('agency_talents', 0);
    }

    public function test_agency_influencers_use_social_profiles_and_remain_distinct_from_streamers(): void
    {
        $agency = $this->agency();
        $streamer = $this->talent($agency);
        $this->actingAs($agency)->post('/agency/roster', $this->talentData([
            'creator_type'=>'influencer', 'public_name'=>'Orbit Social Talent', 'platform'=>'Instagram',
            'instagram_url'=>'https://instagram.com/Orbit_Social/', 'country'=>'Canada', 'audience_geos'=>['Canada'],
            'twitch_url'=>'https://evil.test/ignored', 'status'=>'approved',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('agency.roster'));
        $talent = $agency->agencyTalent()->where('creator_type','influencer')->sole();
        $this->assertSame('pending', $talent->status);
        $this->assertSame('https://www.instagram.com/orbit_social', $talent->instagram_url);
        $this->assertNull($talent->twitch_url);
        $this->assertNull($talent->kick_url);
        $this->assertDatabaseCount('creator_profiles', 0);
        $this->get('/agency/roster?creator_type=influencer')->assertOk()->assertSee('Orbit Social Talent')->assertDontSee($streamer->public_name);
        $this->get('/agency/roster?creator_type=streamer')->assertOk()->assertSee($streamer->public_name)->assertDontSee('Orbit Social Talent');

        $other = $this->agency('private-roster@example.test');
        $this->actingAs($other)->patch('/agency/roster/'.$talent->id, $this->talentData(['creator_type'=>'influencer']))->assertForbidden();
        $this->get('/agency/roster?edit='.$talent->id)->assertNotFound();
    }

    public function test_roster_rejects_cross_type_or_spoofed_social_profiles(): void
    {
        $this->actingAs($this->agency());
        $this->post('/agency/roster', $this->talentData(['creator_type'=>'influencer','platform'=>'Instagram']))->assertSessionHasErrors(['instagram_url','facebook_url','youtube_url','tiktok_url']);
        $this->post('/agency/roster', $this->talentData(['creator_type'=>'influencer','platform'=>'Instagram','instagram_url'=>'https://instagram.com.evil.test/person']))->assertSessionHasErrors('instagram_url');
        $this->post('/agency/roster', $this->talentData(['creator_type'=>'streamer','twitch_url'=>'','kick_url'=>'','instagram_url'=>'https://instagram.com/person']))->assertSessionHasErrors(['twitch_url','kick_url']);
        $this->assertDatabaseCount('agency_talents', 0);
    }

    public function test_staff_can_create_and_filter_an_agency_influencer_and_canonicalize_their_profile(): void
    {
        $agency = $this->agency();
        $streamer = $this->talent($agency);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(TalentsRelationManager::class, ['ownerRecord'=>$agency->agencyProfile, 'pageClass'=>ViewAgency::class])
            ->callTableAction('create', data:$this->talentData([
                'creator_type'=>'influencer','public_name'=>'Admin Social Talent','platform'=>'YouTube',
                'youtube_url'=>'https://youtube.com/@AdminCreator','country'=>'Canada','audience_geos'=>['Canada'],'status'=>'approved',
            ]))->assertHasNoActionErrors();
        $influencer = $agency->agencyTalent()->where('creator_type','influencer')->sole();
        $this->assertSame('https://www.youtube.com/@AdminCreator', $influencer->youtube_url);
        $this->assertNull($influencer->twitch_url);
        Livewire::test(TalentsRelationManager::class, ['ownerRecord'=>$agency->agencyProfile, 'pageClass'=>ViewAgency::class])
            ->filterTable('creator_type','influencer')->assertCanSeeTableRecords([$influencer])->assertCanNotSeeTableRecords([$streamer]);
    }

    public function test_a_selected_agency_cannot_replace_its_profile_during_contracts_or_live_delivery(): void
    {
        $agency = $this->agency();
        $this->offer($agency, $this->talent($agency), 'contracts');
        $this->actingAs($agency)->post('/agency/profile', [
            'company'=>'Replacement agency','services'=>['request'],'country'=>'France','languages'=>['French'],
            'audience_geos'=>['France'],'platforms'=>['Kick'],
        ])->assertSessionHasErrors('agency');
        $this->assertSame('approved', $agency->agencyProfile->refresh()->status);
        $this->assertNotSame('Replacement agency', $agency->refresh()->company);
    }

    public function test_roster_removal_is_soft_and_selected_live_talent_cannot_be_changed_or_removed(): void
    {
        $agency = $this->agency();
        $talent = $this->talent($agency);
        $invitation = $this->offer($agency, $talent, 'live');
        $this->actingAs($agency)->delete('/agency/roster/'.$talent->id)->assertSessionHasErrors('talent');
        $this->patch('/agency/roster/'.$talent->id, $this->talentData())->assertSessionHasErrors('talent');
        $this->assertFalse($talent->refresh()->trashed());

        $invitation->brief->update(['status' => 'completed']);
        $this->delete('/agency/roster/'.$talent->id)->assertSessionHasNoErrors();
        $this->assertSoftDeleted('agency_talents', ['id' => $talent->id]);
        $this->assertSame([$talent->id], $invitation->refresh()->proposed_talent_ids);
        $this->assertDatabaseHas('invitations', ['id' => $invitation->id, 'agency_user_id' => $agency->id]);
    }

    public function test_agency_offer_page_never_discloses_client_identity_or_private_budget(): void
    {
        $agency = $this->agency();
        $talent = $this->talent($agency);
        $invitation = $this->offer($agency, $talent);
        $invitation->update(['status' => 'pending', 'selection_status' => 'unselected', 'proposed_talent_ids' => []]);
        $this->actingAs($agency)->get('/agency/opportunities')->assertOk()
            ->assertSee('Gaming partnership')->assertSee('4 streams')
            ->assertDontSee('Hidden Client Corporation')->assertDontSee('hidden-client@example.test')
            ->assertDontSee('Secret Product Launch')->assertDontSee('888,888');
        $this->actingAs($this->agency('another@example.test'))->get('/agency/opportunities')->assertOk()->assertDontSee('4 streams');
    }

    public function test_staff_review_agency_profile_and_roster_as_distinct_records(): void
    {
        $agency = $this->agency();
        $agency->agencyProfile->update(['status' => 'pending']);
        $talent = $this->talent($agency);
        $talent->update(['status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListAgencies::class)->callTableAction('approve', $agency->agencyProfile)->assertHasNoActionErrors();
        $this->assertSame('approved', $agency->agencyProfile->refresh()->status);
        Livewire::test(TalentsRelationManager::class, ['ownerRecord' => $agency->agencyProfile, 'pageClass' => ViewAgency::class])
            ->callTableAction('approve', $talent)->assertHasNoActionErrors();
        $this->assertSame('approved', $talent->refresh()->status);
    }

    public function test_an_existing_roster_component_cannot_mutate_after_staff_access_is_removed(): void
    {
        $agency = $this->agency();
        $talent = $this->talent($agency);
        $talent->update(['status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::test(TalentsRelationManager::class, ['ownerRecord' => $agency->agencyProfile, 'pageClass' => ViewAgency::class]);
        $admin->update(['role' => 'agency']);
        $component->call('mountAction', 'approve', [], ['table'=>true,'recordKey'=>$talent->id])->assertForbidden();
        $this->assertSame('pending', $talent->refresh()->status);
    }

    public function test_staff_cannot_change_or_remove_selected_active_roster_talent_or_revoke_its_agency(): void
    {
        $agency = $this->agency();
        $talent = $this->talent($agency);
        $this->offer($agency, $talent, 'live');
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(TalentsRelationManager::class, ['ownerRecord'=>$agency->agencyProfile,'pageClass'=>ViewAgency::class])
            ->callTableAction('delete', $talent)->assertNotified('Talent is part of an active campaign');
        Livewire::test(TalentsRelationManager::class, ['ownerRecord'=>$agency->agencyProfile,'pageClass'=>ViewAgency::class])
            ->callTableAction('edit', $talent, data: $this->talentData(['status'=>'declined']))->assertNotified('Talent is part of an active campaign');
        Livewire::test(ListAgencies::class)->callTableAction('decline',$agency->agencyProfile)->assertNotified('Agency has an active campaign');
        $this->assertFalse($talent->refresh()->trashed());
        $this->assertSame('approved',$talent->status);
        $this->assertSame('approved',$agency->agencyProfile->refresh()->status);
    }

    public function test_staff_restore_retains_roster_history_and_requires_review_again(): void
    {
        $agency = $this->agency();
        $talent = $this->talent($agency);
        $talent->delete();
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(TalentsRelationManager::class, ['ownerRecord'=>$agency->agencyProfile,'pageClass'=>ViewAgency::class])
            ->filterTable('trashed',true)->callTableAction('restore',$talent)->assertHasNoActionErrors();
        $this->assertFalse($talent->refresh()->trashed());
        $this->assertSame('pending',$talent->status);
        $this->assertSame(1,$agency->agencyTalent()->count());
    }

    private function agency(string $email = 'agency@example.test'): User
    {
        $agency = User::factory()->create(['role' => 'agency', 'account_type' => 'agency', 'company' => 'Orbit Talent '.$email, 'email' => $email]);
        AgencyProfile::create(['user_id' => $agency->id, 'services' => ['supply', 'request'], 'country' => 'Germany', 'languages' => ['German'], 'audience_geos' => ['Germany'], 'platforms' => ['Twitch'], 'status' => 'approved']);
        return $agency;
    }

    private function talentData(array $values = []): array
    {
        return array_replace(['public_name' => 'Roster Stream', 'country' => 'Germany', 'language' => 'German', 'platform' => 'Twitch', 'twitch_url' => 'https://twitch.tv/Roster_Stream/', 'kick_url' => '', 'followers' => 12000, 'average_viewers' => 400, 'audience_geos' => ['Germany'], 'rate' => 500, 'availability' => 'Open'], $values);
    }

    private function talent(User $agency): AgencyTalent
    {
        return $agency->agencyTalent()->create($this->talentData(['status' => 'approved']));
    }

    private function offer(User $agency, AgencyTalent $talent, string $status = 'matching'): Invitation
    {
        $client = User::factory()->create(['role' => 'business', 'name' => 'Hidden Contact', 'company' => 'Hidden Client Corporation', 'email' => 'hidden-client@example.test']);
        $brief = Brief::create(['user_id' => $client->id, 'title' => 'Secret Product Launch', 'brand' => 'Hidden Client Corporation', 'vertical' => 'Gaming', 'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch', 'budget' => 888888, 'currency' => 'EUR', 'commercial_model' => 'Fixed', 'deliverables' => '4 streams', 'start_date' => now()->addDay(), 'end_date' => now()->addDays(8), 'status' => $status]);
        return $brief->invitations()->create(['agency_user_id' => $agency->id, 'opportunity_title' => 'Gaming partnership', 'status' => 'interested', 'selection_status' => 'selected', 'compensation' => 1500, 'currency' => 'EUR', 'deliverables' => '4 streams', 'proposed_talent_ids' => [$talent->id]]);
    }
}
