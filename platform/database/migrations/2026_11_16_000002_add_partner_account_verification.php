<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('partner_verification_status', 20)->default('pending')->index();
            $table->timestamp('partner_verified_at')->nullable();
            $table->foreignId('partner_verified_by')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::table('users')->update([
            'partner_verification_status' => 'verified',
            'partner_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['partner_verified_by']);
            $table->dropIndex(['partner_verification_status']);
            $table->dropColumn(['partner_verification_status', 'partner_verified_at', 'partner_verified_by']);
        });
    }
};