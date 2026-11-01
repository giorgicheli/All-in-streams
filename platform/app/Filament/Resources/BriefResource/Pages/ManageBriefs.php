<?php

namespace App\Filament\Resources\BriefResource\Pages;

use App\Filament\Resources\BriefResource;
use Filament\Resources\Pages\ManageRecords;

class ManageBriefs extends ManageRecords
{
    protected static string $resource = BriefResource::class;
    protected static ?string $title = 'Brief review queue';

    public function getSubheading(): ?string
    {
        return 'Review the requirements, approve the fit, and move each campaign forward.';
    }
}
