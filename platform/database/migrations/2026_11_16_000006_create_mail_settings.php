<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host', 253)->nullable();
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption', 10)->default('tls');
            $table->string('username', 255)->nullable();
            $table->text('password')->nullable();
            $table->string('from_address', 254)->nullable();
            $table->string('from_name', 160)->default('All In Streams');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('mail_settings'); }
};
