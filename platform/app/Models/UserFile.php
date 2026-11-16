<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFile extends Model
{
    protected $fillable = ['user_id', 'uploaded_by', 'title', 'original_name', 'file_path', 'mime_type', 'size', 'notes'];

    protected function casts(): array { return ['size' => 'integer']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getIsLatestAttachmentAttribute(): bool
    {
        $latest = $this->getAttribute('latest_attachment_id');
        if ($latest === null) {
            $latest = self::query()->where('user_id', $this->user_id)->orderByDesc('created_at')->orderByDesc('id')->value('id');
        }
        return (int) $latest === (int) $this->id;
    }
}
