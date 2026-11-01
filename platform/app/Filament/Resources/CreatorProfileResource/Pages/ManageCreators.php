<?php

namespace App\Filament\Resources\CreatorProfileResource\Pages;

use App\Filament\Resources\CreatorProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCreators extends ManageRecords
{
    protected static string $resource = CreatorProfileResource::class;
    protected static ?string $title = 'Find the right fit.';

    public function getSubheading(): ?string
    {
        return 'Filter your creator network, review applications, and invite talent to a campaign.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add creator profile')];
    }
}
