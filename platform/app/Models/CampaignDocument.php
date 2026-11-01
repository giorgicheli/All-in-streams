<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CampaignDocument extends Model
{
    protected $fillable = ['brief_id', 'creator_profile_id', 'agency_user_id', 'title', 'file_path', 'audience', 'signature_status'];
    public function brief(): BelongsTo { return $this->belongsTo(Brief::class); }
    public function creatorProfile(): BelongsTo { return $this->belongsTo(CreatorProfile::class)->withTrashed(); }
    public function agency(): BelongsTo { return $this->belongsTo(User::class, 'agency_user_id'); }

    protected static function booted(): void
    {
        static::saving(function (CampaignDocument $document): void {
            if (! in_array($document->audience, ['admin', 'business', 'creator', 'agency'], true)) {
                throw ValidationException::withMessages(['audience' => 'Choose the party allowed to read this document.']);
            }
            if (in_array($document->audience, ['admin', 'business'], true)) {
                if ($document->creator_profile_id || $document->agency_user_id) {
                    throw ValidationException::withMessages(['audience' => 'Business and internal documents cannot be attached to a provider identity.']);
                }
                return;
            }
            $creator = $document->audience === 'creator';
            if (($creator && (! $document->creator_profile_id || $document->agency_user_id)) || (! $creator && (! $document->agency_user_id || $document->creator_profile_id))) {
                throw ValidationException::withMessages(['audience' => 'A provider document must identify exactly its intended creator or agency.']);
            }
            $recipient = $creator ? 'creator_profile_id' : 'agency_user_id';
            if (! Invitation::where('brief_id', $document->brief_id)->where($recipient, $document->{$recipient})
                ->where('selection_status', 'selected')->where('status', 'interested')->whereColumn('accepted_offer_version', 'offer_version')->exists()) {
                throw ValidationException::withMessages([$recipient => 'Select this provider for the campaign before sharing its private agreement.']);
            }
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'admin') { return $query; }
        if ($user->role === 'business') {
            return $query->where('audience', 'business')->whereHas('brief', fn (Builder $brief) => $brief->where('user_id', $user->id));
        }
        if ($user->role === 'agency') {
            return $query->where(function (Builder $documents) use ($user) {
                $documents->where(fn (Builder $owned) => $owned->where('audience', 'business')->whereHas('brief', fn (Builder $brief) => $brief->where('user_id', $user->id)))
                    ->orWhere(fn (Builder $provider) => $provider->where('audience', 'agency')->where('agency_user_id', $user->id)
                        ->whereHas('brief.invitations', fn (Builder $invitation) => $invitation->where('agency_user_id', $user->id)->where('selection_status', 'selected')->where('status', 'interested')->whereColumn('accepted_offer_version', 'offer_version')));
            });
        }
        if ($user->role !== 'creator') return $query->whereRaw('1 = 0');
        return $query->where('audience', 'creator')
            ->whereHas('creatorProfile', fn (Builder $creator) => $creator->where('user_id', $user->id))
            ->whereHas('brief.invitations', fn (Builder $invitation) => $invitation->where('selection_status', 'selected')->where('status', 'interested')->whereColumn('accepted_offer_version', 'offer_version')->whereHas('creatorProfile', fn (Builder $creator) => $creator->where('user_id', $user->id)));
    }

    public function visibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }
}
