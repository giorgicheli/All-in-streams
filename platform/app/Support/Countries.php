<?php

namespace App\Support;

class Countries
{
    private const FLAG_ASSET_VERSION = 'public-domain-20261104';

    private const COUNTRIES = [
        'DE' => 'Germany', 'PT' => 'Portugal', 'CA' => 'Canada',
        'PL' => 'Poland', 'CH' => 'Switzerland', 'BR' => 'Brazil',
        'AR' => 'Argentina', 'NZ' => 'New Zealand', 'HU' => 'Hungary',
        'US' => 'United States', 'AT' => 'Austria', 'SE' => 'Sweden',
        'NO' => 'Norway', 'FI' => 'Finland', 'KG' => 'Kyrgyzstan',
        'FR' => 'France', 'MX' => 'Mexico',
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

        return is_file(public_path($path)) ? asset($path).'?v='.self::FLAG_ASSET_VERSION : null;
    }
}
