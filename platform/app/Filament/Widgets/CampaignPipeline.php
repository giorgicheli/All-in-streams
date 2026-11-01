<?php

namespace App\Filament\Widgets;

use App\Services\WorkspaceStatistics;
use Filament\Widgets\Widget;

class CampaignPipeline extends Widget
{
    protected string $view = 'filament.widgets.campaign-pipeline';
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    protected function getViewData(): array
    {
        return ['statistics' => app(WorkspaceStatistics::class)->admin(auth()->user())];
    }
}
