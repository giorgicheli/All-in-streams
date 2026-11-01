<?php

namespace App\Filament\Resources\CampaignMetricResource\Pages;

use App\Filament\Resources\CampaignMetricResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMetrics extends ManageRecords
{
    protected static string $resource = CampaignMetricResource::class;
    protected static ?string $title = 'Campaign reporting';

    public function getSubheading(): ?string
    {
        return 'Maintain daily campaign results. Demo figures stay labeled as simulated data.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add daily report')->icon('heroicon-o-plus')];
    }
}
