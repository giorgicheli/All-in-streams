<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMetric extends Model
{
    protected $fillable = ['brief_id', 'recorded_on', 'impressions', 'unique_viewers', 'clicks', 'conversions', 'streams', 'watch_minutes'];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'impressions' => 'integer',
            'unique_viewers' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'integer',
            'streams' => 'integer',
            'watch_minutes' => 'integer',
        ];
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(Brief::class);
    }
}
