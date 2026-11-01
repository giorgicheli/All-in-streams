<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->nullable();
        });

        DB::table('users')->where('role', 'business')->update(['account_type' => 'business']);

        Schema::table('creator_profiles', function (Blueprint $table) {
            $table->string('twitch_url')->nullable();
            $table->string('kick_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creator_profiles', fn (Blueprint $table) => $table->dropColumn(['twitch_url', 'kick_url']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('account_type'));
    }
};
