<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_talents', function (Blueprint $table) {
            $table->string('creator_type', 20)->default('streamer')->index();
            foreach (['facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url'] as $field) $table->string($field)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agency_talents', function (Blueprint $table) {
            $table->dropIndex(['creator_type']);
            $table->dropColumn(['creator_type', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url']);
        });
    }
};
