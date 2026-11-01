<?php

namespace App\Filament\Widgets;

use App\Models\Brief;
use App\Models\CreatorProfile;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Awaiting review', Brief::whereIn('status', ['submitted', 'review'])->count())->description('New opportunities to shape')->descriptionIcon('heroicon-o-document-text')->color('primary'),
            Stat::make('In matching', Brief::where('status', 'matching')->count())->description('Finding the right people')->descriptionIcon('heroicon-o-adjustments-horizontal')->color('info'),
            Stat::make('Live campaigns', Brief::where('status', 'live')->count())->description('Partnerships in play')->descriptionIcon('heroicon-o-bolt')->color('success'),
            Stat::make('Creator network', CreatorProfile::count())->description('Independent voices, shared ambition')->descriptionIcon('heroicon-o-users')->color('primary'),
        ];
    }
}
