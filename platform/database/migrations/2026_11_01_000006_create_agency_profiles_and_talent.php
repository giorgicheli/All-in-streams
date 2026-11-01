<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('services');
            $table->string('country')->default('');
            $table->string('website')->nullable();
            $table->json('languages');
            $table->json('audience_geos');
            $table->json('platforms');
            $table->text('bio')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });
        Schema::create('agency_talents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('public_name');
            $table->string('country');
            $table->string('language');
            $table->string('platform');
            $table->string('twitch_url')->nullable();
            $table->string('kick_url')->nullable();
            $table->unsignedBigInteger('followers')->default(0);
            $table->unsignedInteger('average_viewers')->default(0);
            $table->json('audience_geos');
            $table->decimal('rate', 12, 2)->default(0);
            $table->string('availability')->default('Open');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('users')->where('role', 'business')->where('account_type', 'agency')->update(['role' => 'agency']);
        foreach (DB::table('users')->where('role', 'agency')->get() as $agency) {
            DB::table('agency_profiles')->insert([
                'user_id' => $agency->id, 'services' => json_encode(['supply', 'request']),
                'country' => '', 'languages' => '[]', 'audience_geos' => '[]', 'platforms' => '[]',
                'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'agency')->update(['role' => 'business', 'account_type' => 'agency']);
        Schema::dropIfExists('agency_talents');
        Schema::dropIfExists('agency_profiles');
    }
};
