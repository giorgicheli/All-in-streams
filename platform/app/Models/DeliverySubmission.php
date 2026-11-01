<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverySubmission extends Model
{
    protected $fillable = ['invitation_id', 'user_id', 'title', 'content_url', 'delivered_on', 'notes', 'file_path', 'reported_impressions', 'reported_clicks', 'reported_conversions', 'status', 'review_notes', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['delivered_on' => 'date', 'reported_impressions' => 'integer', 'reported_clicks' => 'integer', 'reported_conversions' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function invitation(): BelongsTo { return $this->belongsTo(Invitation::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function scopeOwnedBy(Builder $query, User $actor): Builder
    {
        return $query->whereHas('invitation', fn (Builder $invitations) => $invitations->where(function (Builder $recipient) use ($actor) {
            if ($actor->role === 'creator') {
                $recipient->whereHas('creatorProfile', fn (Builder $profile) => $profile->where('user_id', $actor->id));
            } elseif ($actor->role === 'agency') {
                $recipient->where('agency_user_id', $actor->id);
            } else {
                $recipient->whereRaw('1 = 0');
            }
        }));
    }

    public function forRecipient(User $actor): array
    {
        abort_unless($this->invitation->ownedBy($actor), 403);

        return [
            'id' => $this->id,
            'invitation' => $this->invitation->forRecipient($actor),
            'title' => $this->title,
            'content_url' => $this->content_url,
            'delivered_on' => $this->delivered_on->format('Y-m-d'),
            'notes' => $this->notes,
            'has_file' => (bool) $this->file_path,
            'reported_impressions' => $this->reported_impressions,
            'reported_clicks' => $this->reported_clicks,
            'reported_conversions' => $this->reported_conversions,
            'status' => $this->status,
            'review_notes' => $this->invitation->recipientText($this->review_notes),
            'can_resubmit' => $this->status === 'changes_requested' && $this->invitation->brief->status === 'live' && $this->invitation->selection_status === 'selected',
        ];
    }
}
