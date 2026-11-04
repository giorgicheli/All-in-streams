<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorMediaSetting extends Model
{
    protected $fillable = ['max_upload_mb', 'max_duration_seconds', 'compression_enabled', 'compression_crf', 'compression_max_width'];

    protected function casts(): array
    {
        return ['max_upload_mb' => 'integer', 'max_duration_seconds' => 'integer', 'compression_enabled' => 'boolean', 'compression_crf' => 'integer', 'compression_max_width' => 'integer'];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['max_upload_mb' => 10, 'max_duration_seconds' => 120, 'compression_enabled' => false, 'compression_crf' => 28, 'compression_max_width' => 1280]);
    }
}
