<?php

namespace App\Filament\Widgets;

use App\Services\WorkspaceStatistics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Your private network';
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
        $stats = $this->workspace();
        return $stats['accounts']['demo_users'] || $stats['demo_briefs']
            ? 'Sample data included: '.$stats['accounts']['demo_users'].' sample accounts and '.$stats['demo_briefs'].' sample briefs. All figures come from private workspace records.'
            : 'Current account and campaign records. Budget amounts are planned brief budgets, not revenue or money collected.';
    }

    protected function getStats(): array
    {
        $stats = $this->workspace();
        $accounts = $stats['accounts'];
        return [
            Stat::make('Company accounts', number_format($accounts['companies']))->description('Registered business accounts')->descriptionIcon('heroicon-o-building-office-2')->color('primary'),
            Stat::make('Agency accounts', number_format($accounts['agencies']))->description('Registered agency partners')->descriptionIcon('heroicon-o-building-library')->color('info'),
            Stat::make('Creator accounts', number_format($accounts['creator_accounts']))->description($accounts['creators_total'].' creator profiles')->descriptionIcon('heroicon-o-users')->color('primary'),
            Stat::make('Approved creators', number_format($accounts['creators_approved']))->description('Profiles approved by staff')->descriptionIcon('heroicon-o-check-badge')->color('success'),
            Stat::make('Creator profiles in review', number_format($accounts['creators_pending']))->description($accounts['creators_declined'].' declined profiles')->descriptionIcon('heroicon-o-user-circle')->color('warning'),
            Stat::make('EUR brief budget', 'EUR '.number_format($stats['budget_eur'], 0))->description('EUR briefs only; declined excluded')->descriptionIcon('heroicon-o-currency-euro')->color('primary'),
        ];
    }
}
