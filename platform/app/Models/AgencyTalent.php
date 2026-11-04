<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgencyTalent extends Model
{
    use SoftDeletes;

    protected $table = 'agency_talents';

    protected $fillable = ['agency_user_id', 'creator_type', 'public_name', 'country', 'language', 'platform', 'twitch_url', 'kick_url', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'followers', 'average_viewers', 'audience_geos', 'rate', 'availability', 'status'];

    protected function casts(): array
    {
        return ['audience_geos' => 'array', 'followers' => 'integer', 'average_viewers' => 'integer', 'rate' => 'decimal:2'];
    }

    public function agency(): BelongsTo { return $this->belongsTo(User::class, 'agency_user_id'); }

    public function isCommitted(): bool
    {
        return Invitation::where('agency_user_id', $this->agency_user_id)
            ->where('selection_status', 'selected')
            ->whereJsonContains('proposed_talent_ids', $this->id)
            ->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists();
    }
}
