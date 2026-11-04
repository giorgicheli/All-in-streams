<?php

namespace App\Filament\Resources\UserAccountResource\Pages;

use App\Filament\Resources\UserAccountResource;
use Filament\Resources\Pages\ListRecords;

class ListUserAccounts extends ListRecords
{
    protected static string $resource = UserAccountResource::class;
    protected static ?string $title = 'All user accounts';
    public function getSubheading(): ?string { return 'Staff-only account directory. Partner identities and internal documents stay private.'; }
}
