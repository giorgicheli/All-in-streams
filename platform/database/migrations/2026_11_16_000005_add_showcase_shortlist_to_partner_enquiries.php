<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_enquiries', function (Blueprint $table) {
            $table->json('shortlisted_profiles')->nullable();
            $table->string('requested_region', 40)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('partner_enquiries', fn (Blueprint $table) => $table->dropColumn(['shortlisted_profiles', 'requested_region']));
    }
};
