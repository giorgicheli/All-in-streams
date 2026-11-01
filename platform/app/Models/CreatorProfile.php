<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreatorProfile extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'public_name', 'country', 'language', 'platform', 'channel_url', 'twitch_url', 'kick_url', 'followers', 'average_viewers', 'audience_geos', 'vertical', 'rate', 'commercial_model', 'availability', 'bio', 'status'];

    protected function casts(): array
    {
        return ['audience_geos' => 'array', 'followers' => 'integer', 'average_viewers' => 'integer', 'rate' => 'decimal:2'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function invitations(): HasMany { return $this->hasMany(Invitation::class); }
    public function getCodeAttribute(): string { return 'Creator #'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT); }
}
