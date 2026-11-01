<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = ['brief_id', 'user_id', 'title', 'body', 'audience'];
    public function brief(): BelongsTo { return $this->belongsTo(Brief::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
