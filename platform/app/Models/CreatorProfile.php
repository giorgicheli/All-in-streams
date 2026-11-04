<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreatorProfile extends Model
{
    use SoftDeletes;
    public const AUDIENCE_COUNTRIES = ['Germany', 'Austria', 'Switzerland', 'France', 'Poland', 'Portugal', 'Spain', 'Italy', 'Netherlands', 'Belgium', 'Sweden', 'Norway', 'Denmark', 'Finland', 'Ireland', 'United Kingdom', 'United States', 'Canada', 'Brazil', 'Mexico', 'Argentina', 'Chile', 'Colombia', 'Georgia', 'Turkey', 'Ukraine', 'Romania', 'Greece', 'Australia', 'New Zealand', 'India', 'Japan', 'South Korea', 'Philippines', 'Indonesia', 'United Arab Emirates', 'Saudi Arabia', 'South Africa'];

    protected $fillable = ['user_id', 'creator_type', 'public_name', 'country', 'language', 'platform', 'channel_url', 'twitch_url', 'kick_url', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'verification_video_path', 'verification_video_original_name', 'verification_video_mime', 'verification_video_size', 'followers', 'average_viewers', 'audience_geos', 'vertical', 'rate', 'commercial_model', 'availability', 'bio', 'status'];

    protected function casts(): array
    {
        return ['audience_geos' => 'array', 'followers' => 'integer', 'average_viewers' => 'integer', 'rate' => 'decimal:2', 'verification_video_size' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function invitations(): HasMany { return $this->hasMany(Invitation::class); }
    public function getCodeAttribute(): string { return 'Creator #'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT); }
}
