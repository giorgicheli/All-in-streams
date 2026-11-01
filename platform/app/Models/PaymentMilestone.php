<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMilestone extends Model
{
    protected $fillable = ['invitation_id', 'label', 'kind', 'amount', 'currency', 'status', 'paid_at', 'reference', 'approved_by'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_at' => 'datetime']; }
    public function invitation(): BelongsTo { return $this->belongsTo(Invitation::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

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
            'opportunity' => $this->invitation->forRecipient($actor),
            'label' => $this->invitation->recipientText($this->label),
            'kind' => $this->kind,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->format('M j, Y'),
            'reference' => $this->invitation->recipientText($this->reference),
        ];
    }
}
