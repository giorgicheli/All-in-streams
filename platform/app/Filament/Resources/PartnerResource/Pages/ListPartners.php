<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use Filament\Resources\Pages\ListRecords;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    public function getSubheading(): ?string
    {
        return 'Company contacts, agency accounts and their campaign activity. Demo accounts are clearly marked.';
    }
}
