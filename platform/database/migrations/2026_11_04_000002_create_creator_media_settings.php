<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('creator_media_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('max_upload_mb')->default(10);
            $table->unsignedInteger('max_duration_seconds')->default(120);
            $table->boolean('compression_enabled')->default(false);
            $table->unsignedInteger('compression_crf')->default(28);
            $table->unsignedInteger('compression_max_width')->default(1280);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('creator_media_settings'); }
};
