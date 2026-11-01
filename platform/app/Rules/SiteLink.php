<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SiteLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::allowed($value)) {
            $fail('Use a local path, page anchor, HTTPS link, email link or telephone link.');
        }
    }

    public static function allowed(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || $value !== trim($value)
            || preg_match('/[\x00-\x20\x7f\\\\]/', $value)
            || preg_match('/%(?:00|0a|0d|5c)/i', $value)) {
            return false;
        }
        if (str_starts_with($value, '/')) return ! str_starts_with($value, '//');
        if (str_starts_with($value, '#')) return (bool) preg_match('/^#[A-Za-z0-9_-]*$/D', $value);
        if (str_starts_with($value, 'mailto:')) return filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL) !== false;
        if (str_starts_with($value, 'tel:')) return (bool) preg_match('/^tel:\+?[0-9()-]{3,25}$/D', $value);
        $url = parse_url($value);
        return filter_var($value, FILTER_VALIDATE_URL) !== false && is_array($url)
            && strtolower($url['scheme'] ?? '') === 'https' && ! empty($url['host'])
            && ! isset($url['user']) && ! isset($url['pass']);
    }
}
