<?php

namespace Tests\Feature;

use App\Filament\Widgets\CampaignPipeline;
use App\Filament\Widgets\OperationsStats;
use App\Filament\Widgets\PerformanceOverview;
use App\Models\Brief;
use App\Models\CampaignMetric;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\WorkspaceStatistics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkspaceStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('s',32))]);
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-01 12:00:00','UTC'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function partner(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role'=>'business','account_type'=>'business']);
    }

    private function brief(User $owner, array $attributes = []): Brief
    {
        return Brief::create($attributes + [
            'user_id'=>$owner->id,'title'=>'Private creator campaign','brand'=>'Private brand','vertical'=>'Gaming',
            'geos'=>['Germany'],'language'=>'English','platform'=>'Twitch','budget'=>1000,'currency'=>'EUR',
            'commercial_model'=>'Fixed','deliverables'=>'4 live streams','start_date'=>'2026-10-01','end_date'=>'2026-12-01','status'=>'live',
        ]);
    }

    private function metric(Brief $brief, string $date, array $attributes = []): CampaignMetric
    {
        return CampaignMetric::create($attributes + [
            'brief_id'=>$brief->id,'recorded_on'=>$date,'impressions'=>0,'unique_viewers'=>0,'clicks'=>0,'conversions'=>0,'streams'=>0,'watch_minutes'=>0,
        ]);
    }

    private function creator(array $attributes = []): CreatorProfile
    {
        return CreatorProfile::create($attributes + [
            'user_id'=>User::factory()->create(['role'=>'creator'])->id,'public_name'=>'Private Streamer','country'=>'Germany','language'=>'English',
            'platform'=>'Twitch','channel_url'=>'https://example.com/private-streamer','followers'=>1337,'average_viewers'=>42,
            'audience_geos'=>['Germany'],'vertical'=>'Gaming','rate'=>500,'commercial_model'=>'Fixed','availability'=>'Open','status'=>'approved',
        ]);
    }

    public function test_metrics_use_only_the_owners_briefs_and_the_inclusive_thirty_day_window_with_weighted_ctr(): void
    {
        $owner=$this->partner();
        $first=$this->brief($owner);
        $second=$this->brief($owner,['status'=>'completed','currency'=>'USD','budget'=>2000]);
        $other=$this->brief($this->partner());
        $this->metric($first,'2026-10-03',['impressions'=>1000,'clicks'=>10,'conversions'=>2,'streams'=>1]);
        $this->metric($second,'2026-11-01',['impressions'=>9000,'clicks'=>900,'conversions'=>9,'streams'=>3]);
        $this->metric($first,'2026-10-02',['impressions'=>80000,'clicks'=>40000]);
        $this->metric($first,'2026-11-02',['impressions'=>70000,'clicks'=>30000]);
        $this->metric($other,'2026-11-01',['impressions'=>33333,'clicks'=>22222]);

        $stats=app(WorkspaceStatistics::class)->business($owner);
        $data=$stats['performance'];
        $this->assertSame('2026-10-03',$data['from']);
        $this->assertSame('2026-11-01',$data['to']);
        $this->assertSame(10000,$data['impressions']);
        $this->assertSame(910,$data['clicks']);
        $this->assertSame(11,$data['conversions']);
        $this->assertSame(4,$data['streams']);
        $this->assertSame(9.1,$data['ctr']);
        $this->assertSame(2,$data['campaigns']);
        $this->assertSame(2,$data['reported_days']);
        $this->assertSame(['2026-11-01','2026-10-03'],array_column($data['daily'],'date'));
        $this->assertSame(['EUR'=>1000.0,'USD'=>2000.0],$stats['budgets']);
    }

    public function test_pipeline_counts_every_current_stage_once_and_budgets_keep_currencies_separate(): void
    {
        $owner=$this->partner();
        foreach(array_keys(WorkspaceStatistics::STAGES) as $stage){$this->brief($owner,['status'=>$stage,'budget'=>100]);}
        $this->brief($owner,['currency'=>'USD','budget'=>500,'status'=>'matching']);
        $this->brief($owner,['currency'=>'GBP','budget'=>900,'status'=>'declined']);
        $this->brief($this->partner(),['status'=>'submitted','budget'=>999999]);

        $stats=app(WorkspaceStatistics::class)->business($owner);
        $this->assertSame(11,$stats['total_briefs']);
        $this->assertSame(5,$stats['active']);
        $this->assertSame(3,$stats['review']);
        $this->assertSame(1,$stats['completed']);
        $this->assertSame(2,$stats['pipeline']['matching']['count']);
        $this->assertSame(2,$stats['pipeline']['declined']['count']);
        $this->assertSame(800.0,$stats['budget_eur']);
        $this->assertSame(['EUR'=>800.0,'USD'=>500.0],$stats['budgets']);
        $this->assertSame(11,array_sum(array_column($stats['pipeline'],'count')));
    }

    public function test_empty_and_zero_impression_reports_have_no_invented_ctr(): void
    {
        $owner=$this->partner();
        $statistics=app(WorkspaceStatistics::class);
        $empty=$statistics->business($owner)['performance'];
        $this->assertFalse($empty['has_data']);
        $this->assertNull($empty['ctr']);
        $this->assertSame([],$empty['daily']);
        $this->metric($this->brief($owner),'2026-11-01',['streams'=>1]);
        $recorded=$statistics->business($owner)['performance'];
        $this->assertTrue($recorded['has_data']);
        $this->assertNull($recorded['ctr']);
        $this->assertSame(1,$recorded['streams']);
    }

    public function test_admin_counts_companies_agencies_reviewed_creators_and_sample_records(): void
    {
        $admin=User::factory()->create(['role'=>'admin']);
        $company=$this->partner();
        $this->partner(['account_type'=>null]);
        $this->partner(['role'=>'agency','account_type'=>'agency']);
        $this->partner(['is_demo'=>true]);
        foreach(['approved','pending','declined'] as $status){$this->creator(['status'=>$status]);}
        User::factory()->create(['role'=>'creator']);
        $this->brief($company,['is_demo'=>true]);
        $stats=app(WorkspaceStatistics::class)->admin($admin);
        $this->assertSame(3,$stats['accounts']['companies']);
        $this->assertSame(1,$stats['accounts']['agencies']);
        $this->assertSame(4,$stats['accounts']['creator_accounts']);
        $this->assertSame(3,$stats['accounts']['creators_total']);
        $this->assertSame(1,$stats['accounts']['creators_approved']);
        $this->assertSame(1,$stats['accounts']['creators_pending']);
        $this->assertSame(1,$stats['accounts']['creators_declined']);
        $this->assertSame(1,$stats['accounts']['demo_users']);
        $this->assertSame(1,$stats['demo_briefs']);
        $this->assertTrue($stats['contains_demo']);
    }

    public function test_private_aggregate_services_reject_wrong_roles_and_other_campaign_owners(): void
    {
        $business=$this->partner();
        $creator=$this->creator()->user;
        $brief=$this->brief($business);
        $statistics=app(WorkspaceStatistics::class);
        foreach([
            fn()=>$statistics->admin($business),fn()=>$statistics->admin($creator),
            fn()=>$statistics->business($creator),fn()=>$statistics->campaign($brief,$this->partner()),
            fn()=>$statistics->campaign($brief,$creator),
        ] as $attempt){
            try{$attempt();$this->fail('Private statistics must reject unauthorized callers.');}
            catch(HttpException $exception){$this->assertSame(403,$exception->getStatusCode());}
        }
    }

    public function test_agencies_see_only_their_own_client_brief_statistics(): void
    {
        $agency = $this->partner(['role' => 'agency', 'account_type' => 'agency']);
        $own = $this->brief($agency, ['budget' => 1200]);
        $other = $this->brief($this->partner(), ['budget' => 888888]);
        $this->metric($own, '2026-11-01', ['impressions' => 12000, 'clicks' => 100]);
        $this->metric($other, '2026-11-01', ['impressions' => 987654, 'clicks' => 400]);
        $statistics = app(WorkspaceStatistics::class);
        $stats = $statistics->business($agency);
        $this->assertSame(1, $stats['total_briefs']);
        $this->assertSame(1200.0, $stats['budget_eur']);
        $this->assertSame(12000, $stats['performance']['impressions']);
        $this->assertSame(12000, $statistics->campaign($own, $agency)['performance']['impressions']);
        try {
            $statistics->campaign($other, $agency);
            $this->fail('An agency must not read another client campaign aggregate.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_business_pages_render_only_their_own_recorded_campaign_figures(): void
    {
        $owner=$this->partner();
        $brief=$this->brief($owner);
        $this->metric($brief,'2026-11-01',['impressions'=>12345,'clicks'=>22,'conversions'=>3,'streams'=>4]);
        $this->metric($this->brief($this->partner()),'2026-11-01',['impressions'=>987654,'clicks'=>9876]);
        $response=$this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('12,345')->assertDontSee('987,654');
        $this->assertSame(12345,$response->viewData('stats')['performance']['impressions']);
        $this->get('/campaigns/'.$brief->id)->assertOk()->assertSee('12,345')->assertDontSee('987,654');
    }

    public function test_creator_profile_and_invitation_counts_do_not_attribute_campaign_aggregate_performance(): void
    {
        $profile=$this->creator();
        $brief=$this->brief($this->partner(), ['status' => 'matching']);
        $this->metric($brief,'2026-11-01',['impressions'=>987654,'clicks'=>777,'conversions'=>333,'streams'=>9]);
        $profile->invitations()->create(['brief_id'=>$brief->id,'status'=>'pending','compensation'=>2400,'deliverables'=>'4 live streams']);
        $profile->invitations()->create(['brief_id'=>$this->brief($this->partner(), ['status'=>'matching'])->id,'status'=>'pending','selection_status'=>'not_selected','compensation'=>2400,'deliverables'=>'Closed offer']);
        $profile->invitations()->create(['brief_id'=>$this->brief($this->partner(), ['status'=>'live'])->id,'status'=>'pending','compensation'=>2400,'deliverables'=>'Past matching stage']);
        $response=$this->actingAs($profile->user)->get('/creator/opportunities')->assertOk()->assertSee('1,337')->assertSee('Self-reported profile figure')->assertDontSee('987,654');
        $data=$response->viewData('creatorStats');
        $this->assertSame(1,$data['pending']);
        $this->assertSame(0,$data['interested']);
        $this->assertArrayNotHasKey('impressions',$data);
        $this->assertArrayNotHasKey('clicks',$data);
    }

    public function test_sample_campaigns_are_explicitly_labeled_for_real_owners_and_admin_widgets_render(): void
    {
        $owner=$this->partner();
        $brief=$this->brief($owner,['is_demo'=>true]);
        $this->metric($brief,'2026-11-01',['impressions'=>1000,'clicks'=>100]);
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('Sample data included');
        $this->get('/campaigns/'.$brief->id)->assertOk()->assertSee('Sample campaign');

        $admin=User::factory()->create(['role'=>'admin']);
        Livewire::actingAs($admin)->test(OperationsStats::class)->assertSee('Company accounts')->assertSee('Sample data included');
        Livewire::test(CampaignPipeline::class)->assertSee('Campaign pipeline')->assertSee('sample briefs included');
        Livewire::test(PerformanceOverview::class)->assertSee('Recorded campaign performance')->assertSee('10.00%')->assertSee('2026-10-03 to 2026-11-01');
    }
}
