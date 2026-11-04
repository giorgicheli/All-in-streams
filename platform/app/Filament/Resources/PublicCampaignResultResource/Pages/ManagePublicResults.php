<?php
namespace App\Filament\Resources\PublicCampaignResultResource\Pages;

use App\Filament\Resources\PublicCampaignResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePublicResults extends ManageRecords
{
    protected static string $resource=PublicCampaignResultResource::class;
    protected static ?string $title='Public campaign results';
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Add completed result')]; }
}
