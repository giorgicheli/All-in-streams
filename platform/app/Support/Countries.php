<?php

namespace App\Support;

class Countries
{
    private const COUNTRIES = [
        'DE' => 'Germany', 'FR' => 'France', 'PL' => 'Poland',
        'BR' => 'Brazil', 'CA' => 'Canada', 'MX' => 'Mexico',
    ];

    public static function options(): array
    {
        return self::COUNTRIES;
    }

    public static function code(?string $value): ?string
    {
        $value = trim((string) $value);
        $code = strtoupper($value);
        if (isset(self::COUNTRIES[$code])) {
            return $code;
        }
        foreach (self::COUNTRIES as $code => $name) {
            if (strcasecmp($value, $name) === 0) {
                return $code;
            }
        }
        return null;
    }

    public static function name(?string $value): ?string
    {
        $code = self::code($value);
        return $code ? self::COUNTRIES[$code] : null;
    }

    public static function flagUrl(?string $value): ?string
    {
        $code = self::code($value);
        if (! $code) {
            return null;
        }
        $path = 'images/flags/'.strtolower($code).'.png';

        return is_file(public_path($path)) ? asset($path) : null;
    }
}
