<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use Filament\Resources\Pages\ListRecords;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    public function getSubheading(): ?string
    {
        return 'Business contacts and their campaign activity. Agencies and independent creators have their own directories.';
    }
}
