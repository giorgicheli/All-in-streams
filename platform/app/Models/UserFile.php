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
}
