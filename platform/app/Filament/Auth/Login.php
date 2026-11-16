<?php

namespace App\Filament\Auth;

use Filament\Schemas\Components\Component;
use Illuminate\Support\HtmlString;

class Login extends \Filament\Auth\Pages\Login
{
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->hint(new HtmlString(
            '<a href="'.e(route('password.request')).'" class="text-primary-400">Forgot password?</a>'
        ));
    }
}
