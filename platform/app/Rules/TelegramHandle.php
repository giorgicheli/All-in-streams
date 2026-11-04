<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelegramHandle implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::canonicalize($value) === null) $fail('Enter a Telegram username such as @your_username or https://t.me/your_username.');
    }

    public static function canonicalize(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        if (preg_match('~^https://(?:www\.)?t\.me/([a-zA-Z][a-zA-Z0-9_]{4,31})/?$~D', $value, $match)) $value = $match[1];
        if (str_starts_with($value, '@')) $value = substr($value, 1);
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/D', $value) ? '@'.strtolower($value) : null;
    }
}
