<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use Filament\Resources\Pages\ManageRecords;

class ManageContactMessages extends ManageRecords
{
    protected static string $resource = ContactMessageResource::class;

    protected static ?string $title = 'Contact messages';
}
