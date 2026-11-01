<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDocument extends Model
{
    protected $fillable = ['brief_id', 'creator_profile_id', 'title', 'file_path', 'audience', 'signature_status'];
    public function brief(): BelongsTo { return $this->belongsTo(Brief::class); }
    public function creatorProfile(): BelongsTo { return $this->belongsTo(CreatorProfile::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'admin') { return $query; }
        if ($user->role === 'business') {
            return $query->where('audience', 'business')->whereHas('brief', fn (Builder $brief) => $brief->where('user_id', $user->id));
        }
        return $query->where('audience', 'creator')
            ->whereHas('creatorProfile', fn (Builder $creator) => $creator->where('user_id', $user->id))
            ->whereHas('brief.invitations', fn (Builder $invitation) => $invitation->whereHas('creatorProfile', fn (Builder $creator) => $creator->where('user_id', $user->id)));
    }

    public function visibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }
}
