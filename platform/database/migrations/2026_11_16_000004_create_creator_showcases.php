<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('creator_showcases', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('demo_key')->nullable()->unique();
            $table->string('internal_title', 180);
            $table->string('region', 40)->index();
            $table->string('country', 80);
            $table->string('creator_type', 20);
            $table->json('platforms');
            $table->string('language', 40);
            $table->string('image_theme', 40);
            $table->unsignedInteger('followers')->default(0);
            $table->unsignedInteger('average_views')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_showcases');
    }
};