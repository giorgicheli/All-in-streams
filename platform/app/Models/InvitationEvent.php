<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationEvent extends Model
{
    protected $fillable = ['invitation_id', 'user_id', 'kind', 'amount', 'cpa_rate', 'notes'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'cpa_rate' => 'decimal:2']; }
    public function invitation(): BelongsTo { return $this->belongsTo(Invitation::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
