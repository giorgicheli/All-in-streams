<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_profiles', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('briefs', function (Blueprint $table) {
            $table->string('provider_type')->default('either');
            $table->string('attachment_path')->nullable();
        });
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('creator_profile_id')->nullable()->change();
            $table->foreignId('agency_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('selection_status')->default('unselected')->index();
            $table->timestamp('selected_at')->nullable();
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('offer_version')->default(1);
            $table->unsignedInteger('accepted_offer_version')->nullable();
            $table->string('opportunity_title')->nullable();
            $table->json('offer_details')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('cpa_rate', 12, 2)->nullable();
            $table->text('requirements')->nullable();
            $table->decimal('counter_amount', 12, 2)->nullable();
            $table->decimal('counter_cpa_rate', 12, 2)->nullable();
            $table->json('proposed_talent_ids')->nullable();
            $table->unique(['brief_id', 'agency_user_id']);
        });
        Schema::table('campaign_documents', function (Blueprint $table) {
            $table->foreignId('agency_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('invitation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('cpa_rate', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('invitations')->orderBy('id')->chunkById(100, function ($invitations): void {
            foreach ($invitations as $invitation) {
                $brief = DB::table('briefs')->where('id', $invitation->brief_id)->first();
                if (! $brief) continue;
                DB::table('invitations')->where('id', $invitation->id)->update([
                    'currency' => $brief->currency,
                    'opportunity_title' => $brief->vertical.' creator partnership',
                    'offer_details' => json_encode([
                        'vertical' => $brief->vertical, 'geos' => json_decode($brief->geos, true) ?: [],
                        'language' => $brief->language, 'platform' => $brief->platform,
                        'start_date' => $brief->start_date, 'end_date' => $brief->end_date,
                    ], JSON_THROW_ON_ERROR),
                    'accepted_offer_version' => $invitation->status === 'interested' ? 1 : null,
                ]);
            }
        });
        // Existing historical campaigns keep one deterministic lead provider.
        // No new campaign may bypass the explicit approval action.
        DB::table('briefs')->whereIn('status', ['contracts', 'live', 'completed'])->orderBy('id')->chunkById(100, function ($briefs): void {
            foreach ($briefs as $brief) {
                $lead = DB::table('invitations')->where('brief_id', $brief->id)->where('status', 'interested')->orderBy('id')->first();
                if (! $lead) continue;
                DB::table('invitations')->where('brief_id', $brief->id)->update(['selection_status' => 'not_selected']);
                DB::table('invitations')->where('id', $lead->id)->update(['selection_status' => 'selected', 'selected_at' => $lead->introduced_at ?: $lead->updated_at]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('invitations')->whereNotNull('agency_user_id')->exists() || DB::table('campaign_documents')->whereNotNull('agency_user_id')->exists()) {
            throw new RuntimeException('Agency offers or agreements exist. Preserve those records before rolling back provider workflow support.');
        }
        Schema::dropIfExists('invitation_events');
        Schema::table('briefs', fn (Blueprint $table) => $table->dropColumn(['provider_type', 'attachment_path']));
        Schema::table('campaign_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('agency_user_id'));
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropUnique(['brief_id', 'agency_user_id']);
            $table->dropConstrainedForeignId('agency_user_id');
            $table->dropConstrainedForeignId('selected_by');
            $table->dropColumn(['selection_status', 'selected_at', 'offer_version', 'accepted_offer_version', 'opportunity_title', 'offer_details', 'currency', 'cpa_rate', 'requirements', 'counter_amount', 'counter_cpa_rate', 'proposed_talent_ids']);
        });
        Schema::table('invitations', fn (Blueprint $table) => $table->foreignId('creator_profile_id')->nullable(false)->change());
        Schema::table('creator_profiles', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
