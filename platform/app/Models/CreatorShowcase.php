<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** An explicitly approved public catalogue, with no partner identity or contact fields. */
class CreatorShowcase extends Model
{
    public const COUNTRIES_BY_REGION = [
        'Europe' => ['Germany', 'Portugal', 'Poland', 'France', 'Switzerland', 'Austria', 'Sweden', 'Norway', 'Finland', 'Hungary', 'United Kingdom', 'Spain', 'Italy'],
        'CIS' => ['Georgia', 'Kazakhstan', 'Kyrgyzstan', 'Armenia', 'Azerbaijan', 'Uzbekistan'],
        'MENA' => ['United Arab Emirates', 'Saudi Arabia', 'Turkey', 'Egypt', 'Morocco'],
        'Africa' => ['South Africa', 'Nigeria', 'Kenya', 'Ghana'],
        'North America' => ['Canada', 'United States', 'Mexico'],
        'LATAM' => ['Brazil', 'Argentina', 'Colombia', 'Chile', 'Peru'],
        'Asia' => ['India', 'Japan', 'South Korea', 'Indonesia', 'Philippines', 'Singapore'],
        'Oceania' => ['Australia', 'New Zealand', 'Fiji'],
    ];
    public const TYPES = ['streamer' => 'Streamer', 'influencer' => 'Influencer', 'both' => 'Streamer & influencer'];
    public const PLATFORMS = ['Twitch', 'Kick', 'Instagram', 'TikTok', 'YouTube', 'Facebook'];
    public const LANGUAGES = ['English', 'German', 'Portuguese', 'Polish', 'French', 'Spanish', 'Georgian', 'Russian', 'Kyrgyz', 'Arabic', 'Swahili', 'Hindi', 'Japanese', 'Korean'];
    public const IMAGES = ['gaming-studio' => 'Gaming studio', 'creator-microphone' => 'Creator microphone', 'streaming-desk' => 'Streaming desk'];

    protected $fillable = ['demo_key', 'internal_title', 'region', 'country', 'creator_type', 'platforms', 'language', 'image_theme', 'followers', 'average_views', 'sort_order', 'is_demo', 'is_verified', 'is_published'];
    protected $attributes = ['followers' => 0, 'average_views' => 0, 'sort_order' => 0, 'is_demo' => false, 'is_verified' => false, 'is_published' => false];

    protected function casts(): array
    {
        return ['platforms' => 'array', 'followers' => 'integer', 'average_views' => 'integer', 'sort_order' => 'integer', 'is_demo' => 'boolean', 'is_verified' => 'boolean', 'is_published' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            $profile->public_id = (string) Str::uuid();
        });
        static::saving(function (self $profile): void {
            $rules = [
                'internal_title' => ['required', 'string', 'max:180'],
                'region' => ['required', Rule::in(array_keys(self::COUNTRIES_BY_REGION))],
                'country' => ['required', Rule::in(self::COUNTRIES_BY_REGION[$profile->region] ?? [])],
                'creator_type' => ['required', Rule::in(array_keys(self::TYPES))],
                'platforms' => ['required', 'array', 'min:1', 'max:6'],
                'platforms.*' => ['required', 'distinct', Rule::in(self::PLATFORMS)],
                'language' => ['required', Rule::in(self::LANGUAGES)],
                'image_theme' => ['required', Rule::in(array_keys(self::IMAGES))],
                'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
                'followers' => ['required', 'integer', 'min:0', 'max:2147483647'],
                'average_views' => ['required', 'integer', 'min:0', 'max:2147483647'],
                'is_demo' => ['required', 'boolean'], 'is_verified' => ['required', 'boolean'], 'is_published' => ['required', 'boolean'],
            ];
            validator($profile->attributesToArray(), $rules)->validate();
            $streaming = array_intersect($profile->platforms, ['Twitch', 'Kick']);
            $social = array_intersect($profile->platforms, ['Instagram', 'TikTok', 'YouTube', 'Facebook']);
            if (($profile->creator_type !== 'influencer' && !$streaming) || ($profile->creator_type !== 'streamer' && !$social)) {
                throw ValidationException::withMessages(['platforms' => 'Choose platforms matching the published creator type.']);
            }
            if ($profile->is_published && !$profile->is_verified) {
                throw ValidationException::withMessages(['is_published' => 'Review this public profile before publishing.']);
            }
            if ($profile->isDirty('is_published') && $profile->is_published && auth()->user()?->role !== 'admin') {
                throw ValidationException::withMessages(['is_published' => 'An administrator must publish the public profile.']);
            }
            if ($profile->is_verified && $profile->isDirty(['region', 'country', 'creator_type', 'platforms', 'language', 'image_theme', 'followers', 'average_views', 'is_demo', 'is_verified'])) {
                if (auth()->user()?->role !== 'admin') {
                    throw ValidationException::withMessages(['is_verified' => 'An administrator must review the public profile.']);
                }
                $profile->reviewed_at = now();
            }
            if (!$profile->is_verified) {
                $profile->reviewed_at = null;
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('is_verified', true)->whereNotNull('reviewed_at');
    }

    public function publicData(): array
    {
        return [
            'public_id' => $this->public_id,
            'region' => $this->region,
            'country' => $this->country,
            'creator_type' => $this->creator_type,
            'type_label' => self::TYPES[$this->creator_type],
            'caption' => match ($this->creator_type) { 'influencer' => 'The community voice', 'both' => 'The gaming storyteller', default => 'The live entertainer' },
            'platforms' => $this->platforms,
            'language' => $this->language,
            'image_url' => asset('images/creator-showcase/'.$this->image_theme.'.jpg'),
            'image_alt' => self::IMAGES[$this->image_theme],
            'followers' => $this->followers,
            'average_views' => $this->average_views,
            'is_demo' => $this->is_demo,
        ];
    }
}
