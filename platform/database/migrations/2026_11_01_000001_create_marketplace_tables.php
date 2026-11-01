<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('business')->index();
            $table->string('company')->nullable();
        });

        Schema::create('creator_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_name');
            $table->string('country');
            $table->string('language');
            $table->string('platform');
            $table->string('channel_url');
            $table->unsignedBigInteger('followers')->default(0);
            $table->unsignedInteger('average_viewers')->default(0);
            $table->json('audience_geos');
            $table->string('vertical');
            $table->decimal('rate', 12, 2)->default(0);
            $table->string('commercial_model');
            $table->string('availability')->default('Open');
            $table->text('bio')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('brand');
            $table->string('vertical');
            $table->json('geos');
            $table->string('language');
            $table->string('platform');
            $table->decimal('budget', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('commercial_model');
            $table->text('deliverables');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('kpis')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->boolean('payment_ready')->default(false);
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->decimal('compensation', 12, 2)->default(0);
            $table->text('deliverables');
            $table->text('notes')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamp('introduced_at')->nullable();
            $table->timestamps();
            $table->unique(['brief_id', 'creator_profile_id']);
        });

        Schema::create('campaign_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('audience')->default('admin');
            $table->string('signature_status')->default('uploaded');
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience')->default('business');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('campaign_documents');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('briefs');
        Schema::dropIfExists('creator_profiles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'company']));
    }
};
