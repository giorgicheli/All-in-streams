<?php

namespace App\Filament\Resources\PartnerEnquiryResource\Pages;

use App\Filament\Resources\PartnerEnquiryResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePartnerEnquiries extends ManageRecords
{
    protected static string $resource = PartnerEnquiryResource::class;
    protected static ?string $title = 'Partner enquiries';
}
