<?php

namespace App\Filament\Resources\CampaignDocumentResource\Pages;

use App\Filament\Resources\CampaignDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDocuments extends ManageRecords
{
    protected static string $resource = CampaignDocumentResource::class;
    protected static ?string $title = 'Document centre';

    public function getSubheading(): ?string
    {
        return 'Separate agreements. Clear access. Every signature accounted for.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Upload document')->icon('heroicon-o-arrow-up-tray')];
    }
}
