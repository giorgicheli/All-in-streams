<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brief extends Model
{
    protected $fillable = ['user_id', 'title', 'brand', 'vertical', 'geos', 'language', 'platform', 'budget', 'currency', 'commercial_model', 'deliverables', 'start_date', 'end_date', 'kpis', 'notes', 'status', 'payment_ready', 'is_demo', 'demo_key', 'provider_type', 'attachment_path'];

    protected function casts(): array
    {
        return ['geos' => 'array', 'budget' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date', 'payment_ready' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function invitations(): HasMany { return $this->hasMany(Invitation::class); }
    public function selectedInvitations(): HasMany { return $this->hasMany(Invitation::class)->where('selection_status', 'selected'); }
    public function documents(): HasMany { return $this->hasMany(CampaignDocument::class); }
    public function activities(): HasMany { return $this->hasMany(Activity::class); }
    public function metrics(): HasMany { return $this->hasMany(CampaignMetric::class); }
    public function getCodeAttribute(): string { return 'BRF-'.($this->created_at?->format('Y') ?? now()->year).'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT); }
    public function getStatusLabelAttribute(): string { return ucwords(str_replace('_', ' ', $this->status)); }
}
