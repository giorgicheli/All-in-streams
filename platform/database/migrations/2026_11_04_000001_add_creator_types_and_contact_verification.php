<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram', 40)->nullable();
        });
        Schema::table('creator_profiles', function (Blueprint $table) {
            $table->string('creator_type', 20)->default('streamer')->index();
            foreach (['facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url'] as $field) $table->string($field)->nullable();
            $table->string('verification_video_path')->nullable();
            $table->string('verification_video_original_name')->nullable();
            $table->string('verification_video_mime', 100)->nullable();
            $table->unsignedBigInteger('verification_video_size')->nullable();
        });
        DB::table('creator_profiles')->whereIn('platform', ['YouTube', 'TikTok', 'Facebook', 'Instagram'])->update(['creator_type' => 'influencer']);
        foreach (['YouTube' => 'youtube_url', 'TikTok' => 'tiktok_url', 'Facebook' => 'facebook_url', 'Instagram' => 'instagram_url'] as $platform => $field) {
            DB::table('creator_profiles')->where('platform', $platform)->update([$field => DB::raw('channel_url')]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('telegram'));
        Schema::table('creator_profiles', function (Blueprint $table) {
            $table->dropIndex(['creator_type']);
            $table->dropColumn(['creator_type', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'verification_video_path', 'verification_video_original_name', 'verification_video_mime', 'verification_video_size']);
        });
    }
};
