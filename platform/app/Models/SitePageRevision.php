<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePageRevision extends Model
{
    protected $fillable = ['site_page_id', 'content', 'published_by'];

    protected function casts(): array { return ['content' => 'array']; }
    public function page(): BelongsTo { return $this->belongsTo(SitePage::class, 'site_page_id'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
}
