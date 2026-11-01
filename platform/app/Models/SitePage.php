<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SitePage extends Model
{
    protected $fillable = ['slug', 'title', 'draft_content', 'published_content', 'published_at', 'updated_by'];

    protected function casts(): array
    {
        return ['draft_content' => 'array', 'published_content' => 'array', 'published_at' => 'datetime'];
    }

    public function revisions(): HasMany { return $this->hasMany(SitePageRevision::class); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
