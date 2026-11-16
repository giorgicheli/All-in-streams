<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_enquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_key')->unique();
            $table->string('kind', 20)->index();
            $table->string('creator_type', 20)->nullable();
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->string('telegram', 40);
            $table->string('website', 2048)->nullable();
            $table->json('audience_geos');
            $table->unsignedInteger('streams')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('budget_scope', 20)->nullable();
            $table->text('requirements')->nullable();
            $table->string('twitch_url')->nullable();
            $table->string('kick_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->decimal('stream_rate', 12, 2)->nullable();
            $table->decimal('story_rate', 12, 2)->nullable();
            $table->decimal('reel_rate', 12, 2)->nullable();
            $table->timestamp('age_confirmed_at');
            $table->string('status', 20)->default('new')->index();
            $table->text('staff_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_enquiries');
    }
};
