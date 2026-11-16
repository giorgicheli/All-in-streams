<?php

namespace App\Filament\Resources\CreatorShowcaseResource\Pages;

use App\Filament\Resources\CreatorShowcaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCreatorShowcases extends ManageRecords
{
    protected static string $resource = CreatorShowcaseResource::class;
    protected static ?string $title = 'Public creator profiles';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add anonymous profile')];
    }
}