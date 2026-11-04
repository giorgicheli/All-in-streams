<?php

namespace App\Filament\Resources\InfluencerProfileResource\Pages;

use App\Filament\Resources\InfluencerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInfluencers extends ManageRecords
{
    protected static string $resource = InfluencerProfileResource::class;
    protected static ?string $title = 'Influencer matching';

    public function getSubheading(): ?string { return 'Find influencers by location, audience country, platform and budget. All profiles and links are private to staff.'; }

    protected function getHeaderActions(): array { return [CreateAction::make()->label('Add influencer profile')]; }
}
