<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });

        Schema::table('briefs', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
            $table->string('demo_key')->nullable()->unique();
        });

        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedInteger('unique_viewers')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->unsignedInteger('streams')->default(0);
            $table->unsignedBigInteger('watch_minutes')->default(0);
            $table->timestamps();
            $table->unique(['brief_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
        Schema::table('briefs', fn (Blueprint $table) => $table->dropColumn(['is_demo', 'demo_key']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_demo'));
    }
};
