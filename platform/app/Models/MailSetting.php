<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    // Only the administrator service may assign settings, including the secret.
    protected $guarded = ['*'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'port' => 'integer', 'password' => 'encrypted'];
    }

    public static function current(): self
    {
        if ($setting = static::query()->find(1)) return $setting;

        $setting = new static;
        $setting->id = 1;
        $setting->enabled = false;
        $setting->port = 587;
        $setting->encryption = 'tls';
        $setting->from_name = 'All In Streams';
        $setting->save();

        return $setting;
    }
}
