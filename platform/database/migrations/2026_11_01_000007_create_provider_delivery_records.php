<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title', 200);
            $table->text('content_url');
            $table->date('delivered_on');
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('reported_impressions')->nullable();
            $table->unsignedInteger('reported_clicks')->nullable();
            $table->unsignedInteger('reported_conversions')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->restrictOnDelete();
            $table->string('label', 200);
            $table->string('kind')->default('fixed_fee');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_milestones');
        Schema::dropIfExists('delivery_submissions');
    }
};
