<?php

namespace Tests\Feature;

use App\Filament\Pages\WebsiteEditor;
use App\Filament\Resources\PublicCampaignResultResource;
use App\Filament\Resources\PublicCampaignResultResource\Pages\ManagePublicResults;
use App\Models\PublicCampaignResult;
use App\Models\User;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PublicCampaignResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key'=>'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }
    private function staff(): User
    {
        $admin=User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin); Filament::setCurrentPanel(Filament::getPanel('admin'));
        return $admin;
    }
    private function data(array $overrides=[]): array
    {
        return array_replace(['internal_title'=>'PRIVATE_INTERNAL_RESULT_REFERENCE','country'=>'Canada','region'=>'North America','platform'=>'Twitch','completed_on'=>today()->subDays(3)->toDateString(),'streams'=>4,'posts'=>0,'views'=>128400,'clicks'=>2480,'conversions'=>64,'is_demo'=>true,'is_verified'=>true,'is_published'=>true],$overrides);
    }

    public function test_only_reviewed_published_aggregates_reach_the_public_gallery_and_private_fields_never_do(): void
    {
        $this->staff();
        $shown=PublicCampaignResult::create($this->data());
        PublicCampaignResult::create($this->data(['country'=>'France','is_verified'=>false,'is_published'=>false]));
        PublicCampaignResult::create($this->data(['country'=>'Brazil','is_published'=>false]));
        $private=User::factory()->create(['role'=>'creator','name'=>'PRIVATE_CREATOR_IDENTITY_918','telegram'=>'@private_contact_918']);
        $response=$this->get('/')->assertOk()->assertSee('Demo · simulated results')->assertSee('128,400')->assertSee('2,480')
            ->assertDontSee('PRIVATE_INTERNAL_RESULT_REFERENCE')->assertDontSee($private->email)->assertDontSee($private->name)->assertDontSee('@private_contact_918');
        $cards=$response->viewData('publicResults');
        $this->assertCount(1,$cards); $this->assertSame($shown->id,$cards->first()->id);
        foreach(['internal_title','reviewed_by','demo_key','verified_at','user_id','brief_id'] as $field) $this->assertArrayNotHasKey($field,$cards->first()->getAttributes());
        $this->assertSame(1,substr_count($response->getContent(),'data-result-region='));
    }

    public function test_publication_requires_review_completed_delivery_and_valid_funnel_figures(): void
    {
        $this->staff();
        foreach([
            ['is_verified'=>false], ['completed_on'=>today()->addDay()->toDateString()],
            ['streams'=>0,'posts'=>0], ['clicks'=>128401], ['conversions'=>2481],
            ['country'=>'PRIVATE_COMPANY_NAME'], ['platform'=>'https://private.example/profile'],
        ] as $invalid){
            try{PublicCampaignResult::create($this->data($invalid));$this->fail('Invalid public result was accepted');}
            catch(ValidationException $exception){$this->assertNotEmpty($exception->errors());}
        }
        $this->assertDatabaseCount('public_campaign_results',0);
    }

    public function test_staff_can_create_correct_unpublish_and_delete_results_without_touching_private_campaigns(): void
    {
        $this->staff();
        Livewire::test(ManagePublicResults::class)->callAction('create',data:$this->data())->assertHasNoActionErrors();
        $record=PublicCampaignResult::sole(); $this->assertTrue($record->is_published); $this->assertNotNull($record->verified_at);
        Livewire::test(ManagePublicResults::class)->callTableAction('edit',$record,data:$this->data(['views'=>140000]))->assertHasNoActionErrors();
        $this->assertSame(140000,$record->fresh()->views);
        Livewire::test(ManagePublicResults::class)->callTableAction('unpublish',$record)->assertHasNoActionErrors();
        $this->assertSame(0,PublicCampaignResult::published()->count());
        Livewire::test(ManagePublicResults::class)->callTableAction('delete',$record)->assertHasNoActionErrors();
        $this->assertDatabaseCount('public_campaign_results',0);
        $this->assertDatabaseCount('briefs',0); $this->assertDatabaseCount('campaign_metrics',0);
    }

    public function test_other_roles_cannot_manage_public_results(): void
    {
        foreach(['business','creator','agency'] as $role) $this->actingAs(User::factory()->create(['role'=>$role]))->get(PublicCampaignResultResource::getUrl())->assertForbidden();
    }

    public function test_public_demo_seed_is_repeatable_and_preserves_staff_corrections_and_hidden_cases(): void
    {
        $this->staff(); $this->artisan('showcase:public-results')->assertSuccessful();
        $this->assertDatabaseCount('public_campaign_results',4);
        $record=PublicCampaignResult::where('country','Canada')->sole(); $record->update(['views'=>150000,'is_published'=>false]);
        $this->artisan('showcase:public-results')->assertSuccessful();
        $this->assertDatabaseCount('public_campaign_results',4); $this->assertSame(150000,$record->fresh()->views); $this->assertFalse($record->fresh()->is_published);
    }

    public function test_network_statistics_are_an_editable_published_dataset_not_private_account_totals(): void
    {
        $this->staff();
        $editor=Livewire::test(WebsiteEditor::class)->call('selectPage','settings');
        $state=$editor->get('data'); $first=array_key_first($state['network_stats']['cards']);
        $editor->set('data.network_stats.cards.'.$first.'.count',240)->call('saveDraft')->assertHasNoErrors();
        $this->assertSame(120,app(SiteContent::class)->getPublished('settings')['network_stats']['cards'][0]['count']);
        $editor->call('publish')->assertHasNoErrors();
        $this->get('/')->assertOk()->assertSee('data-network-count="240"',false)->assertSee('Illustrative network figures for this demo.');
        $editor->set('data.network_stats.cards.'.$first.'.count',-1)->call('publish')->assertHasErrors();
    }
}
