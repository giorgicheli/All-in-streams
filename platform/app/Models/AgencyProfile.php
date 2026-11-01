<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyProfile extends Model
{
    protected $fillable = ['user_id', 'services', 'country', 'website', 'languages', 'audience_geos', 'platforms', 'bio', 'status'];

    protected function casts(): array
    {
        return ['services' => 'array', 'languages' => 'array', 'audience_geos' => 'array', 'platforms' => 'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function talents(): HasMany { return $this->hasMany(AgencyTalent::class, 'agency_user_id', 'user_id'); }

    public function getCodeAttribute(): string { return 'Agency #'.str_pad((string) $this->user_id, 3, '0', STR_PAD_LEFT); }
}
