<?php

namespace App\Filament\Resources\AgencyResource\Pages;

use App\Filament\Resources\AgencyResource;
use Filament\Resources\Pages\ListRecords;

class ListAgencies extends ListRecords
{
    protected static string $resource = AgencyResource::class;
    public function getSubheading(): ?string { return 'Review agency profiles and the talent they represent. Agencies, businesses and independent creators have separate accounts.'; }
}
