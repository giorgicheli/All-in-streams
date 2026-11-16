<?php

namespace App\Models;

use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Private signup requirements retained for staff review, without committing campaign terms. */
class PartnerEnquiry extends Model
{
    public const KINDS = ['brand' => 'Brand', 'agency' => 'Agency', 'creator' => 'Creator'];
    public const CREATOR_TYPES = ['streamer' => 'Streamer', 'influencer' => 'Influencer', 'both' => 'Streamer & influencer'];
    public const CURRENCIES = ['EUR', 'USD', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'PLN', 'SEK', 'NOK', 'BRL', 'GEL'];
    public const STATUSES = ['new' => 'New', 'in_review' => 'In review', 'contacted' => 'Contacted', 'closed' => 'Closed'];

    protected $fillable = ['user_id', 'submission_key', 'kind', 'creator_type', 'name', 'email', 'telegram', 'website', 'audience_geos', 'streams', 'budget', 'currency', 'budget_scope', 'requirements', 'twitch_url', 'kick_url', 'instagram_url', 'stream_rate', 'story_rate', 'reel_rate', 'age_confirmed_at', 'status', 'staff_notes'];
    protected $hidden = ['submission_key'];
    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['audience_geos' => 'array', 'streams' => 'integer', 'budget' => 'decimal:2', 'stream_rate' => 'decimal:2', 'story_rate' => 'decimal:2', 'reel_rate' => 'decimal:2', 'age_confirmed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function countries(): array
    {
        $countries = array_values(array_unique([...CreatorProfile::AUDIENCE_COUNTRIES, ...array_values(Countries::options())]));
        sort($countries, SORT_NATURAL | SORT_FLAG_CASE);
        return $countries;
    }
}
