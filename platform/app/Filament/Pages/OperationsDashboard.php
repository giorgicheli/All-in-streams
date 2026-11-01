<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BriefResource;
use App\Filament\Resources\CreatorProfileResource;
use App\Filament\Widgets\OperationsStats;
use App\Filament\Widgets\ReviewQueue;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class OperationsDashboard extends Dashboard
{
    protected static ?string $navigationLabel = 'Overview';

    public function getTitle(): string|Htmlable
    {
        return 'Your network. In motion.';
    }

    public function getSubheading(): ?string
    {
        return 'Review the brief, find the fit, and keep every partnership moving.';
    }

    public function getWidgets(): array
    {
        return [OperationsStats::class, ReviewQueue::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')->label('Review briefs')->icon('heroicon-o-document-magnifying-glass')->url(BriefResource::getUrl()),
            Action::make('network')->label('Creator network')->color('gray')->url(CreatorProfileResource::getUrl()),
        ];
    }
}
