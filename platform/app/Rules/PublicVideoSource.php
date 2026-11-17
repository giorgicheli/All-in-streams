<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicVideoSource implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::allowed($value)) {
            $fail('Use a public HTTPS link ending in .mp4 or .webm. Uploads and watch-page links have separate fields.');
        }
    }

    /** Validate a browser media URL only. This rule never fetches a remote resource. */
    public static function allowed(mixed $value): bool
    {
        if (! SiteLink::allowed($value) || ! is_string($value)) return false;
        $url = parse_url($value);
        if (! is_array($url) || strtolower($url['scheme'] ?? '') !== 'https' || isset($url['fragment'])) return false;
        $host = strtolower(rtrim(trim($url['host'] ?? '', '[]'), '.'));
        if ($host === '' || $host === 'localhost' || preg_match('/(?:^|\.)(?:localhost|local|internal|localdomain)$/D', $host)) return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
            if (str_starts_with($host, '::ffff:') && ! filter_var(substr($host, 7), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        } elseif (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z][a-z0-9-]*$/D', $host)) return false;
        $path = rawurldecode($url['path'] ?? '');
        return preg_match('/\.(?:mp4|webm)$/iD', $path) === 1
            && ! preg_match('~(?:^|/)(?:creator-verification|verification-videos|campaign-documents|user-files)(?:/|$)~i', $path);
    }
}
