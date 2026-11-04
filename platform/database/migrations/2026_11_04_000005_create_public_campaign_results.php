<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('public_campaign_results', function (Blueprint $table) {
            $table->id();
            $table->string('internal_title', 180);
            $table->string('demo_key')->nullable()->unique();
            $table->string('country', 80);
            $table->string('region', 50);
            $table->string('platform', 40);
            $table->date('completed_on');
            foreach (['streams', 'posts', 'views', 'clicks', 'conversions'] as $field) $table->unsignedInteger($field)->default(0);
            $table->boolean('is_demo')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('public_campaign_results'); }
};
