<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StreamingChannelUrl implements ValidationRule
{
    public function __construct(private readonly string $platform) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::canonicalize($value, $this->platform) === null) {
            $host = $this->platform === 'Twitch' ? 'www.twitch.tv' : 'kick.com';
            $fail('Enter your '.$this->platform.' channel link, for example https://'.$host.'/your_channel.');
        }
    }

    public static function canonicalize(mixed $value, string $platform): ?string
    {
        if (! is_string($value) || ! in_array($platform, ['Twitch', 'Kick'], true)) {
            return null;
        }

        $value = trim($value);
        $url = parse_url($value);
        $host = $platform === 'Twitch' ? 'twitch.tv' : 'kick.com';

        if (! filter_var($value, FILTER_VALIDATE_URL) || ! is_array($url)
            || strtolower($url['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($url['host'] ?? ''), [$host, 'www.'.$host], true)
            || isset($url['user']) || isset($url['pass'])
            || isset($url['port']) || isset($url['query']) || isset($url['fragment'])
            || ! preg_match('~^/([a-zA-Z0-9_'.($platform === 'Kick' ? '-' : '').']{1,25})/?$~D', $url['path'] ?? '', $matches)) {
            return null;
        }

        $channel = strtolower($matches[1]);
        $reserved = $platform === 'Twitch'
            ? ['directory', 'downloads', 'jobs', 'p', 'settings', 'login', 'signup', 'search', 'videos', 'clips', 'following', 'dashboard', 'popout', 'subscriptions', 'wallet', 'inventory', 'turbo', 'store', 'drops', 'friends', 'messages', 'broadcast', 'products']
            : ['categories', 'browse', 'following', 'search', 'settings', 'login', 'signup', 'privacy-policy', 'terms-of-service', 'community-guidelines', 'dashboard', 'clips', 'video', 'videos', 'transactions', 'subscriptions'];

        if (in_array($channel, $reserved, true)) {
            return null;
        }

        return 'https://'.($platform === 'Twitch' ? 'www.twitch.tv' : 'kick.com').'/'.$channel;
    }
}
