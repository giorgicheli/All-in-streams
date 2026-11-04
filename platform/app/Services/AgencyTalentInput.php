<?php

namespace App\Services;

use App\Rules\SocialProfileUrl;
use App\Rules\StreamingChannelUrl;

class AgencyTalentInput
{
    public static function rules(string $type): array
    {
        $streamer = $type === 'streamer';
        $rules = [
            'creator_type' => ['required', 'string', 'in:streamer,influencer'],
            'public_name' => ['required', 'string', 'min:2', 'max:120'], 'country' => ['required', 'string', 'max:80'],
            'language' => ['required', 'string', 'max:80'], 'platform' => ['required', $streamer ? 'in:Twitch,Kick' : 'in:Facebook,Instagram,YouTube,TikTok'],
            'twitch_url' => $streamer ? ['nullable', 'required_without:kick_url', 'string', 'max:255', new StreamingChannelUrl('Twitch')] : ['exclude'],
            'kick_url' => $streamer ? ['nullable', 'required_without:twitch_url', 'string', 'max:255', new StreamingChannelUrl('Kick')] : ['exclude'],
            'followers' => ['required', 'integer', 'min:0', 'max:2000000000'], 'average_viewers' => ['required', 'integer', 'min:0', 'max:100000000'],
            'audience_geos' => ['required', 'array', 'min:1', 'max:30'], 'audience_geos.*' => ['required', 'string', 'max:80', 'distinct'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999'], 'availability' => ['required', 'in:Open,Opening soon,Unavailable'],
            'status' => ['sometimes', 'in:pending,approved,declined'],
        ];
        foreach (SocialProfileUrl::FIELDS as $field => $platform) {
            $rules[$field] = !$streamer ? ['nullable', 'required_without_all:'.implode(',', array_diff(array_keys(SocialProfileUrl::FIELDS), [$field])), 'string', 'max:255', new SocialProfileUrl($platform)] : ['exclude'];
        }
        return $rules;
    }

    public static function validate(array $data): array
    {
        $data['creator_type'] ??= 'streamer';
        $data = validator($data, self::rules(is_string($data['creator_type']) ? $data['creator_type'] : ''))->validate();
        $data['twitch_url'] = StreamingChannelUrl::canonicalize($data['twitch_url'] ?? null, 'Twitch');
        $data['kick_url'] = StreamingChannelUrl::canonicalize($data['kick_url'] ?? null, 'Kick');
        $social = [];
        foreach (SocialProfileUrl::FIELDS as $field => $platform) {
            $data[$field] = SocialProfileUrl::canonicalize($data[$field] ?? null, $platform);
            if ($data[$field]) $social[$platform] = $data[$field];
        }
        if ($data['creator_type'] === 'influencer' && !isset($social[$data['platform']])) $data['platform'] = array_key_first($social);
        if ($data['creator_type'] === 'streamer') {
            if ($data['platform'] === 'Twitch' && !$data['twitch_url']) $data['platform'] = 'Kick';
            if ($data['platform'] === 'Kick' && !$data['kick_url']) $data['platform'] = 'Twitch';
        }
        return $data;
    }
}
