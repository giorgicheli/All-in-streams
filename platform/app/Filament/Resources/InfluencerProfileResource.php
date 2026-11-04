<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InfluencerProfileResource\Pages\ManageInfluencers;

class InfluencerProfileResource extends CreatorProfileResource
{
    protected static ?string $navigationLabel = 'Influencers';
    protected static ?string $modelLabel = 'influencer';
    protected static ?string $pluralModelLabel = 'influencers';
    protected static ?int $navigationSort = 2;

    public static function creatorType(): string { return 'influencer'; }

    public static function getPages(): array
    {
        return ['index' => ManageInfluencers::route('/')];
    }
}
