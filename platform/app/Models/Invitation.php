<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Invitation extends Model
{
    protected $fillable = ['brief_id', 'creator_profile_id', 'agency_user_id', 'status', 'compensation', 'deliverables', 'notes', 'requirements', 'response_notes', 'introduced_at', 'selection_status', 'selected_at', 'selected_by', 'offer_version', 'accepted_offer_version', 'opportunity_title', 'offer_details', 'currency', 'cpa_rate', 'counter_amount', 'counter_cpa_rate', 'proposed_talent_ids'];

    protected function casts(): array
    {
        return ['compensation' => 'decimal:2', 'cpa_rate' => 'decimal:2', 'counter_amount' => 'decimal:2', 'counter_cpa_rate' => 'decimal:2', 'introduced_at' => 'datetime', 'selected_at' => 'datetime', 'offer_details' => 'array', 'proposed_talent_ids' => 'array', 'offer_version' => 'integer', 'accepted_offer_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Invitation $invitation): void {
            if ((bool) $invitation->creator_profile_id === (bool) $invitation->agency_user_id) {
                throw ValidationException::withMessages(['creator_profile_id' => 'An offer must have exactly one independent creator or agency recipient.']);
            }
        });
    }

    public function brief(): BelongsTo { return $this->belongsTo(Brief::class); }
    public function creatorProfile(): BelongsTo { return $this->belongsTo(CreatorProfile::class)->withTrashed(); }
    public function agency(): BelongsTo { return $this->belongsTo(User::class, 'agency_user_id'); }
    public function events(): HasMany { return $this->hasMany(InvitationEvent::class); }

    public function getRecipientTypeAttribute(): string { return $this->agency_user_id ? 'agency' : 'creator'; }
    public function getRecipientNameAttribute(): string { return $this->agency_user_id ? ($this->agency?->company ?: $this->agency?->name ?: 'Agency') : ($this->creatorProfile?->public_name ?? 'Archived creator'); }
    public function getRecipientCodeAttribute(): string { return $this->agency_user_id ? 'Agency #'.str_pad((string) $this->agency_user_id, 3, '0', STR_PAD_LEFT) : ($this->creatorProfile?->code ?? 'Archived creator'); }
    public function getCodeAttribute(): string { return 'INV-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT); }

    public function ownedBy(User $actor): bool
    {
        if ($this->agency_user_id) return $actor->role === 'agency' && $this->agency_user_id === $actor->id;
        return $actor->role === 'creator' && $this->creatorProfile?->user_id === $actor->id;
    }

    public function recipientApproved(): bool
    {
        if (! $this->agency_user_id) return $this->creatorProfile && ! $this->creatorProfile->trashed() && $this->creatorProfile->status === 'approved';
        $profile = $this->agency?->agencyProfile;
        if ($this->agency?->role !== 'agency' || ! $profile || $profile->status !== 'approved' || ! in_array('supply', $profile->services ?? [], true)) return false;
        $roster = $this->agency->relationLoaded('agencyTalent')
            ? $this->agency->getRelation('agencyTalent')->where('status', 'approved')
            : AgencyTalent::where('agency_user_id', $this->agency_user_id)->where('status', 'approved')->get();
        if ($roster->isEmpty()) return false;
        $ids = $this->proposed_talent_ids ?? [];
        return ! $ids || $roster->whereIn('id', $ids)->count() === count($ids);
    }

    public function scopeEligibleForBusiness(Builder $query): Builder
    {
        return $query->where('status', 'interested')->where('selection_status', '!=', 'not_selected')->whereColumn('accepted_offer_version', 'offer_version')
            ->where(function (Builder $recipient) {
                $recipient->whereHas('creatorProfile', fn (Builder $creator) => $creator->where('status', 'approved')->whereNull('deleted_at'))
                    ->orWhereHas('agency.agencyProfile', fn (Builder $agency) => $agency->where('status', 'approved'))
                    ->orWhere(fn (Builder $historical) => $historical->where('selection_status', 'selected')->whereHas('brief', fn (Builder $brief) => $brief->where('status', 'completed')));
            });
    }

    public static function detailsFromBrief(Brief $brief): array
    {
        return ['vertical' => $brief->vertical, 'geos' => $brief->geos ?? [], 'language' => $brief->language, 'platform' => $brief->platform, 'start_date' => $brief->start_date?->toDateString(), 'end_date' => $brief->end_date?->toDateString()];
    }

    public function forRecipient(User $actor): array
    {
        abort_unless($this->ownedBy($actor), 403);
        $details = $this->offer_details ?: static::detailsFromBrief($this->brief);
        $sharedText = fn (?string $text) => $this->recipientText($text);
        return [
            'id' => $this->id, 'code' => $this->code, 'recipient_type' => $this->recipient_type,
            'opportunity_title' => $sharedText($this->opportunity_title ?: ($details['vertical'].' partnership')),
            'campaign_type' => $details['vertical'], 'vertical' => $details['vertical'], 'geos' => $details['geos'], 'language' => $details['language'], 'platform' => $details['platform'],
            'start_date' => $details['start_date'], 'end_date' => $details['end_date'],
            'currency' => $this->currency, 'compensation' => $this->compensation, 'cpa_rate' => $this->cpa_rate,
            'commercial_model' => $this->cpa_rate !== null ? 'Fixed + CPA' : 'Fixed',
            'deliverables' => $sharedText($this->deliverables), 'requirements' => $sharedText($this->requirements), 'notes' => $sharedText($this->notes),
            'status' => $this->status, 'selection_status' => $this->selection_status, 'selected_at' => $this->selected_at?->toIso8601String(),
            'offer_version' => $this->offer_version, 'accepted_offer_version' => $this->accepted_offer_version,
            'counter_amount' => $this->counter_amount, 'counter_cpa_rate' => $this->counter_cpa_rate, 'response_notes' => $sharedText($this->response_notes),
            'proposed_talent_ids' => $this->proposed_talent_ids ?? [],
            'can_respond' => $this->status === 'pending' && $this->selection_status !== 'not_selected' && in_array($this->brief->status, ['matching', 'proposal'], true) && ($this->recipientApproved() || ! $this->creatorProfile?->trashed()),
            'history' => $this->events()->oldest('id')->get()->map(fn (InvitationEvent $event) => ['kind' => $event->kind, 'actor' => $event->user_id === $actor->id ? 'You' : 'All In Streams', 'amount' => $event->amount, 'cpa_rate' => $event->cpa_rate, 'notes' => $sharedText($event->notes), 'created_at' => $event->created_at?->toIso8601String()])->all(),
        ];
    }

    public function recipientText(?string $text): ?string
    {
        if ($text === null) return null;
        $client = $this->brief->user;
        $identifiers = array_filter([$this->brief->brand, $this->brief->title, $client?->company, $client?->name, $client?->email, $client?->telegram], fn ($value) => is_string($value) && mb_strlen($value) >= 3);
        usort($identifiers, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        return $identifiers ? str_ireplace($identifiers, '[client]', $text) : $text;
    }

    public function forBusiness(): array
    {
        $historical = $this->selection_status === 'selected' && $this->brief->status === 'completed';
        abort_unless($this->status === 'interested' && $this->accepted_offer_version === $this->offer_version && $this->selection_status !== 'not_selected' && ($this->recipientApproved() || $historical), 403);
        $creator = $this->creatorProfile;
        $profile = $this->agency_user_id ? $this->agency?->agencyProfile : null;
        return [
            'id' => $this->id, 'code' => $this->recipient_code, 'recipient_type' => $this->recipient_type,
            'country' => $profile?->country ?: $creator?->country,
            'platform' => $profile ? implode(', ', $profile->platforms ?? []) : $creator?->platform,
            'followers' => $creator?->followers ?? 0, 'average_viewers' => $creator?->average_viewers ?? 0,
            'roster_count' => $this->agency_user_id ? AgencyTalent::where('agency_user_id', $this->agency_user_id)->where('status', 'approved')->count() : 0,
            'creator_type' => $creator?->creator_type,
            'deliverables' => $this->businessText($this->deliverables), 'status' => $this->status, 'selection_status' => $this->selection_status,
            // Legacy introduction timestamps are audit history, never permission to release identity.
            'public_name' => null, 'channel_url' => null, 'twitch_url' => null, 'kick_url' => null,
            'introduced' => false, 'brief_id' => $this->brief_id,
            'brief_status' => $this->brief->status, 'title' => $this->brief->title,
        ];
    }

    private function businessText(?string $text): ?string
    {
        if ($text === null) return null;
        $creator = $this->creatorProfile;
        $user = $this->agency_user_id ? $this->agency : $creator?->user;
        $identifiers = array_filter([
            $this->recipient_name, $user?->name, $user?->company, $user?->email, $user?->telegram,
            $this->agency?->agencyProfile?->website,
            ...array_map(fn ($field) => $creator?->{$field}, ['channel_url', 'twitch_url', 'kick_url', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url']),
        ], fn ($value) => is_string($value) && mb_strlen($value) >= 3);
        usort($identifiers, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        return $identifiers ? str_ireplace($identifiers, '[provider]', $text) : $text;
    }
}
