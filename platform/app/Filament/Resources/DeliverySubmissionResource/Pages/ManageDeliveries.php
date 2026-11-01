<?php

namespace App\Filament\Resources\DeliverySubmissionResource\Pages;

use App\Filament\Resources\DeliverySubmissionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveries extends ManageRecords
{
    protected static string $resource = DeliverySubmissionResource::class;
    protected static ?string $title = 'Delivery review';

    public function getSubheading(): ?string { return 'Review provider evidence, request revisions and approve completed work. Self-reported figures stay separate from validated campaign reporting.'; }
}
