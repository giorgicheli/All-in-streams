<?php

namespace App\Filament\Resources\CreatorProfileResource\Pages;

use App\Filament\Resources\CreatorProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCreators extends ManageRecords
{
    protected static string $resource = CreatorProfileResource::class;
    protected static ?string $title = 'Streamer matching';

    public function getSubheading(): ?string
    {
        return 'Find Twitch and Kick streamers by location, audience country and budget. Profiles and contacts are private to staff.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add streamer profile')];
    }
}
