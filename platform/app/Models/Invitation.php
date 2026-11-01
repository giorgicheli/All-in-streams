<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = ['brief_id', 'creator_profile_id', 'status', 'compensation', 'deliverables', 'notes', 'response_notes', 'introduced_at'];
    protected function casts(): array { return ['compensation' => 'decimal:2', 'introduced_at' => 'datetime']; }
    public function brief(): BelongsTo { return $this->belongsTo(Brief::class); }
    public function creatorProfile(): BelongsTo { return $this->belongsTo(CreatorProfile::class); }

    public function forBusiness(): array
    {
        $creator = $this->creatorProfile;
        abort_unless($creator->status === 'approved', 403);
        return [
            'id' => $this->id,
            'code' => $creator->code,
            'country' => $creator->country,
            'platform' => $creator->platform,
            'followers' => $creator->followers,
            'average_viewers' => $creator->average_viewers,
            'deliverables' => $this->deliverables,
            'status' => $this->status,
            'public_name' => $this->introduced_at ? $creator->public_name : null,
            'channel_url' => $this->introduced_at ? $creator->channel_url : null,
            'introduced' => $this->introduced_at !== null,
            'brief_id' => $this->brief_id,
            'brief_status' => $this->brief->status,
            'title' => $this->brief->title,
        ];
    }
}
