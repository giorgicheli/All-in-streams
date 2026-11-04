<?php

namespace App\Filament\Resources\InvitationResource\Pages;

use App\Filament\Resources\InvitationResource;
use App\Filament\Resources\CreatorProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageInvitations extends ManageRecords
{
    protected static string $resource = InvitationResource::class;
    protected static ?string $title = 'Better partnerships start here.';

    public function getSubheading(): ?string
    {
        return 'Review private provider responses and approve the final provider. Identities stay private to staff.';
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('match')->label('Find creators')->icon('heroicon-o-users')->url(CreatorProfileResource::getUrl())];
    }
}
