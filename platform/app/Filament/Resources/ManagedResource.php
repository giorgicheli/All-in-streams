<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

abstract class ManagedResource extends Resource
{
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if (auth()->user()?->role !== 'admin') {
            return Response::deny('Only All In Streams staff can manage this resource.');
        }

        return parent::getAuthorizationResponse($action, $record);
    }
}
