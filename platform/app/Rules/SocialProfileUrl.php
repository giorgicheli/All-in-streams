<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SocialProfileUrl implements ValidationRule
{
    public const FIELDS = ['facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'youtube_url' => 'YouTube', 'tiktok_url' => 'TikTok'];

    public function __construct(private readonly string $platform) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::canonicalize($value, $this->platform) === null) $fail('Enter a valid HTTPS '.$this->platform.' profile link, rather than a post, video or unrelated website.');
    }

    public static function canonicalize(mixed $value, string $platform): ?string
    {
        if (!is_string($value) || !filter_var(trim($value), FILTER_VALIDATE_URL)) return null;
        $url = parse_url(trim($value));
        if (!is_array($url) || strtolower($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])) return null;
        $hosts = match ($platform) {
            'Facebook' => ['facebook.com', 'www.facebook.com', 'fb.com', 'www.fb.com'],
            'Instagram' => ['instagram.com', 'www.instagram.com'],
            'YouTube' => ['youtube.com', 'www.youtube.com'],
            'TikTok' => ['tiktok.com', 'www.tiktok.com'],
            default => [],
        };
        if (!in_array(strtolower($url['host'] ?? ''), $hosts, true)) return null;
        $path = rtrim($url['path'] ?? '', '/');
        if ($platform === 'Facebook' && $path === '/profile.php' && preg_match('/^id=([0-9]{3,30})$/D', $url['query'] ?? '', $match)) return 'https://www.facebook.com/profile.php?id='.$match[1];
        if (isset($url['query'])) return null;
        $valid = match ($platform) {
            'Facebook' => preg_match('~^/([a-zA-Z0-9.]{3,100})$~D', $path),
            'Instagram' => preg_match('~^/([a-zA-Z0-9_.]{1,30})$~D', $path),
            'YouTube' => preg_match('~^/(?:@[a-zA-Z0-9_.-]{3,100}|channel/[a-zA-Z0-9_-]{3,100}|c/[a-zA-Z0-9_.-]{3,100})$~D', $path),
            'TikTok' => preg_match('~^/@[a-zA-Z0-9_.]{2,24}$~D', $path),
            default => false,
        };
        if (!$valid || in_array(strtolower(ltrim($path, '/')), ['login', 'signup', 'register', 'explore', 'reels', 'reel', 'stories', 'p', 'watch', 'posts', 'videos', 'settings', 'help', 'about', 'directory', 'groups', 'pages', 'events', 'marketplace'], true)) return null;
        $host = match ($platform) { 'Facebook' => 'www.facebook.com', 'Instagram' => 'www.instagram.com', 'YouTube' => 'www.youtube.com', 'TikTok' => 'www.tiktok.com' };
        return 'https://'.$host.($platform === 'YouTube' ? $path : strtolower($path));
    }
}
