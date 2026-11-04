<?php

namespace App\Filament\Resources\UserFileResource\Pages;

use App\Filament\Resources\UserFileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageUserFiles extends ManageRecords
{
    protected static string $resource = UserFileResource::class;
    protected static ?string $title = 'Private user files';

    public function getSubheading(): ?string { return 'Internal documents for businesses, streamers, influencers, agencies and staff. Administrator access only.'; }

    protected function getHeaderActions(): array
    {
        return [Action::make('upload')->label('Attach files')->icon('heroicon-o-arrow-up-tray')->schema(UserFileResource::uploadSchema())->action(fn (array $data) => UserFileResource::upload($data))];
    }
}
