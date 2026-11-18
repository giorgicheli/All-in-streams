<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('subject', 160);
            $table->text('message');
            $table->string('status', 24)->default('new')->index();
            $table->text('staff_notes')->nullable();
            $table->char('submission_key', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
