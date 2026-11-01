<?php

namespace App\Filament\Widgets;

use App\Services\WorkspaceStatistics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PerformanceOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Recorded campaign performance';
    protected ?string $pollingInterval = null;
    protected ?array $workspace = null;

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function workspace(): array
    {
        return $this->workspace ??= app(WorkspaceStatistics::class)->admin(auth()->user());
    }

    protected function getDescription(): ?string
    {
        $workspace = $this->workspace();
        $performance = $workspace['performance'];
        return $performance['from'].' to '.$performance['to'].' inclusive · Staff-recorded daily campaign totals. '.
            ($workspace['contains_demo'] ? 'Sample data included. ' : '').
            ($performance['has_data'] ? 'Only reported dates are included.' : 'No performance records in this period.');
    }

    protected function getStats(): array
    {
        $data = $this->workspace()['performance'];
        return [
            Stat::make('Impressions', number_format($data['impressions']))->description('Recorded in the last 30 calendar days')->color('primary'),
            Stat::make('Clicks', number_format($data['clicks']))->description('Recorded campaign clicks')->color('info'),
            Stat::make('Click-through rate', $data['ctr'] === null ? '—' : number_format($data['ctr'], 2).'%')->description('Total clicks ÷ total impressions')->color('primary'),
            Stat::make('Conversions', number_format($data['conversions']))->description('Recorded campaign conversions')->color('success'),
            Stat::make('Streams', number_format($data['streams']))->description('Reported streams delivered')->color('info'),
            Stat::make('Campaigns with reports', number_format($data['campaigns']))->description($data['reported_days'].' reported dates in this period')->color('primary'),
        ];
    }
}
