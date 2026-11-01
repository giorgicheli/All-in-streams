<?php

namespace Tests\Feature;

use App\Filament\Resources\CampaignMetricResource;
use App\Filament\Resources\CampaignMetricResource\Pages\ManageMetrics;
use App\Models\Brief;
use App\Models\CampaignMetric;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function brief(string $status = 'live'): Brief
    {
        return Brief::create([
            'user_id' => User::factory()->create(['role' => 'business'])->id,
            'title' => 'Community launch campaign', 'brand' => 'Northstar Games', 'vertical' => 'Gaming',
            'geos' => ['Georgia'], 'language' => 'Georgian', 'platform' => 'Twitch',
            'budget' => 12000, 'currency' => 'EUR', 'commercial_model' => 'Fixed',
            'deliverables' => '4 live streams', 'start_date' => today()->subDays(10), 'end_date' => today()->addDays(10),
            'status' => $status, 'is_demo' => false,
        ]);
    }

    private function results(Brief $brief, array $values = []): array
    {
        return array_replace([
            'brief_id' => $brief->id, 'recorded_on' => today()->toDateString(),
            'impressions' => 18000, 'unique_viewers' => 6400, 'clicks' => 720,
            'conversions' => 46, 'streams' => 2, 'watch_minutes' => 42000,
        ], $values);
    }

    public function test_campaign_reporting_is_available_only_to_staff(): void
    {
        foreach (['business', 'creator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(CampaignMetricResource::getUrl())->assertForbidden();
        }

        $this->staff();
        $this->get(CampaignMetricResource::getUrl())->assertOk()->assertSee('Campaign reporting');
    }

    public function test_staff_can_create_and_edit_an_actual_daily_report(): void
    {
        $brief = $this->brief();
        $this->staff();

        Livewire::test(ManageMetrics::class)->callAction('create', data: $this->results($brief))->assertHasNoActionErrors();

        $metric = CampaignMetric::sole();
        $this->assertSame($brief->id, $metric->brief_id);
        $this->assertSame(720, $metric->clicks);
        $this->assertFalse($metric->brief->is_demo);

        Livewire::test(ManageMetrics::class)->callTableAction('edit', $metric, data: $this->results($brief, ['clicks' => 780]))->assertHasNoActionErrors();

        $this->assertDatabaseCount('campaign_metrics', 1);
        $this->assertSame(780, $metric->fresh()->clicks);
    }

    public function test_staff_cannot_create_two_reports_for_the_same_campaign_and_day(): void
    {
        $brief = $this->brief();
        CampaignMetric::create($this->results($brief));
        $this->staff();

        Livewire::test(ManageMetrics::class)->callAction('create', data: $this->results($brief))->assertHasActionErrors(['recorded_on']);

        $this->assertDatabaseCount('campaign_metrics', 1);
    }

    public function test_same_day_reports_for_different_campaigns_are_allowed(): void
    {
        CampaignMetric::create($this->results($this->brief()));
        $brief = $this->brief('completed');
        $this->staff();

        Livewire::test(ManageMetrics::class)->callAction('create', data: $this->results($brief))->assertHasNoActionErrors();

        $this->assertDatabaseCount('campaign_metrics', 2);
    }

    #[DataProvider('invalidResults')]
    public function test_daily_report_rejects_invalid_counts_and_dates(array $values, string $field, string $rule): void
    {
        $brief = $this->brief();
        $this->staff();

        Livewire::test(ManageMetrics::class)->callAction('create', data: $this->results($brief, $values))->assertHasActionErrors([$field => $rule]);

        $this->assertDatabaseCount('campaign_metrics', 0);
    }

    public static function invalidResults(): array
    {
        return [
            'negative impressions' => [['impressions' => -1], 'impressions', 'min'],
            'negative watch time' => [['watch_minutes' => -1], 'watch_minutes', 'min'],
            'fractional stream count' => [['streams' => 1.5], 'streams', 'integer'],
            'unbounded clicks' => [['clicks' => 2147483648], 'clicks', 'max'],
            'clicks exceed impressions' => [['clicks' => 18001], 'clicks', 'lte'],
            'conversions exceed clicks' => [['conversions' => 721], 'conversions', 'lte'],
            'viewers exceed impressions' => [['unique_viewers' => 18001], 'unique_viewers', 'lte'],
            'future report' => [['recorded_on' => '2099-12-31'], 'recorded_on', 'before_or_equal'],
        ];
    }

    public function test_reports_cannot_be_added_before_a_campaign_is_live(): void
    {
        $brief = $this->brief('submitted');
        $this->staff();

        Livewire::test(ManageMetrics::class)->callAction('create', data: $this->results($brief))->assertHasActionErrors(['brief_id']);

        $this->assertDatabaseCount('campaign_metrics', 0);
    }
}
