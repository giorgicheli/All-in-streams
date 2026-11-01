<?php

namespace Tests\Feature;

use App\Models\Brief;
use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\CampaignDocument;
use App\Models\CampaignMetric;
use App\Models\CreatorProfile;
use App\Models\DeliverySubmission;
use App\Models\Invitation;
use App\Models\PaymentMilestone;
use App\Models\User;
use Database\Seeders\ShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ShowcaseSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $privateDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00'));
        Storage::fake('local');
        $this->privateDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ais-showcase-'.bin2hex(random_bytes(8));
        mkdir($this->privateDirectory, 0700);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->privateDirectory);
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_production_showcase_is_fictional_complete_and_keeps_existing_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'actual-admin@example.com']);
        $originalPassword = $admin->password;
        $this->app['env'] = 'production';
        $file = $this->privateDirectory.DIRECTORY_SEPARATOR.'accounts.json';

        try {
            $this->artisan('showcase:seed', ['--force' => true, '--credentials-file' => $file])
                ->expectsOutputToContain('Fictional sample workspace ready')->assertSuccessful();
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertDatabaseCount('users', 17);
        $this->assertSame(4, User::where('is_demo', true)->where('account_type', 'business')->count());
        $this->assertSame(2, User::where('is_demo', true)->where('account_type', 'agency')->count());
        $this->assertSame(2, User::where('is_demo', true)->where('role', 'agency')->count());
        $this->assertDatabaseCount('agency_profiles', 2);
        $this->assertDatabaseCount('agency_talents', 4);
        $this->assertSame(2, AgencyProfile::where('status', 'approved')->count());
        $this->assertSame(4, AgencyTalent::where('status', 'approved')->count());
        $this->assertSame(10, User::where('is_demo', true)->where('role', 'creator')->count());
        $this->assertDatabaseCount('briefs', 12);
        $this->assertDatabaseCount('creator_profiles', 10);
        $this->assertEqualsCanonicalizing(['submitted', 'review', 'changes_requested', 'matching', 'proposal', 'contracts', 'live', 'completed', 'declined'], Brief::distinct()->pluck('status')->all());
        $this->assertSame($originalPassword, $admin->fresh()->password);
        $this->assertFalse($admin->fresh()->is_demo);
        $this->assertSame(1, User::where('role', 'admin')->count());

        $document = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(16, $document['accounts']);
        $this->assertCount(16, array_unique(array_column($document['accounts'], 'password')));
        foreach ($document['accounts'] as $account) {
            $this->assertStringEndsWith('@workspace.allinstreams.example', $account['email']);
            $this->assertTrue(Hash::check($account['password'], User::where('email', $account['email'])->firstOrFail()->password));
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0600, fileperms($file) & 0777);
        }
    }

    public function test_repeated_seeding_preserves_passwords_and_workflow_edits_without_duplicate_records(): void
    {
        $file = $this->privateDirectory.DIRECTORY_SEPARATOR.'accounts.json';
        $this->artisan('showcase:seed', ['--credentials-file' => $file])->assertSuccessful();
        $user = User::where('email', 'heliogear@workspace.allinstreams.example')->firstOrFail();
        $user->update(['name' => 'Edited sample user', 'password' => 'ChangedLater!2026']);
        $password = $user->fresh()->password;
        $brief = Brief::where('demo_key', ShowcaseSeeder::PREFIX.'gear-launch')->firstOrFail();
        $brief->update(['title' => 'Edited campaign title', 'status' => 'review']);
        $counts = [User::count(), Brief::count(), CreatorProfile::count(), Invitation::count(), CampaignDocument::count(), CampaignMetric::count(), AgencyProfile::count(), AgencyTalent::count(), DeliverySubmission::count(), PaymentMilestone::count()];
        $credentials = file_get_contents($file);

        $this->artisan('showcase:seed', ['--credentials-file' => $file])->expectsOutput('New sample accounts: 0')->assertSuccessful();

        $this->assertSame($counts, [User::count(), Brief::count(), CreatorProfile::count(), Invitation::count(), CampaignDocument::count(), CampaignMetric::count(), AgencyProfile::count(), AgencyTalent::count(), DeliverySubmission::count(), PaymentMilestone::count()]);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame('Edited sample user', $user->fresh()->name);
        $this->assertSame('Edited campaign title', $brief->fresh()->title);
        $this->assertSame('review', $brief->fresh()->status);
        $this->assertSame($credentials, file_get_contents($file));
    }

    public function test_production_requires_explicit_authorization_and_reserved_email_collisions_are_preserved(): void
    {
        $this->app['env'] = 'production';
        try {
            $this->artisan('showcase:seed')->expectsOutputToContain('requires --force')->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertDatabaseCount('users', 0);

        $real = User::factory()->create(['email' => 'heliogear@workspace.allinstreams.example', 'role' => 'business']);
        $password = $real->password;
        $this->artisan('showcase:seed')->expectsOutputToContain('already used by another account')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('briefs', 0);
        $this->assertSame($password, $real->fresh()->password);
        $this->assertFalse($real->fresh()->is_demo);
    }

    public function test_credentials_cannot_be_written_to_the_web_root_or_over_unrelated_files(): void
    {
        $this->artisan('showcase:seed', ['--credentials-file' => public_path('sample-secrets.json')])
            ->expectsOutputToContain('cannot be stored under the public directory')->assertFailed();
        $file = $this->privateDirectory.DIRECTORY_SEPARATOR.'unrelated.json';
        file_put_contents($file, '{"keep":"this"}');
        chmod($file, 0600);
        $this->artisan('showcase:seed', ['--credentials-file' => $file])
            ->expectsOutputToContain('does not belong to this sample workspace')->assertFailed();
        $this->assertSame('{"keep":"this"}', file_get_contents($file));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_performance_data_and_private_sample_documents_match_campaign_stages(): void
    {
        $this->artisan('showcase:seed')->assertSuccessful();
        $this->assertGreaterThan(25, CampaignMetric::count());
        $this->assertSame(0, CampaignMetric::whereHas('brief', fn ($q) => $q->whereNotIn('status', ['live', 'completed']))->count());
        $this->assertSame(0, CampaignMetric::whereDate('recorded_on', '>', now())->count());
        $this->assertSame(0, CampaignMetric::whereColumn('clicks', '>', 'impressions')->count());
        $this->assertSame(0, CampaignMetric::whereColumn('conversions', '>', 'clicks')->count());

        foreach (Brief::whereIn('status', ['live', 'completed'])->get() as $brief) {
            $this->assertTrue($brief->payment_ready);
            $this->assertTrue($brief->activities()->where('title', 'Campaign delivery started')->firstOrFail()->created_at->isSameDay($brief->start_date));
            if ($brief->status === 'completed') {
                $this->assertTrue($brief->activities()->where('title', 'Delivery complete — reporting ready')->firstOrFail()->created_at->isSameDay($brief->end_date->copy()->addDay()));
            }
            $this->assertTrue($brief->documents()->where('audience', 'business')->where('signature_status', 'signed')->exists());
            $this->assertSame(1, $brief->invitations()->where('selection_status','selected')->count());
            foreach ($brief->invitations()->where('selection_status', 'selected')->get() as $invitation) {
                $this->assertSame('interested', $invitation->status);
                $this->assertSame($invitation->offer_version, $invitation->accepted_offer_version);
                $this->assertNotNull($invitation->introduced_at);
                if ($invitation->agency_user_id) {
                    $this->assertSame('approved', $invitation->agency->agencyProfile->status);
                    $this->assertNotEmpty($invitation->proposed_talent_ids);
                    $this->assertSame(count($invitation->proposed_talent_ids), AgencyTalent::where('agency_user_id',$invitation->agency_user_id)->where('status','approved')->whereIn('id',$invitation->proposed_talent_ids)->count());
                    $this->assertTrue($brief->documents()->where('agency_user_id', $invitation->agency_user_id)->where('audience','agency')->where('signature_status','signed')->exists());
                } else {
                    $this->assertSame('approved', $invitation->creatorProfile->status);
                    $this->assertTrue($brief->documents()->where('creator_profile_id', $invitation->creator_profile_id)->where('signature_status', 'signed')->exists());
                }
            }
        }
        foreach (CampaignDocument::all() as $document) {
            Storage::disk('local')->assertExists($document->file_path);
            $this->assertStringContainsString('FICTIONAL SAMPLE DOCUMENT', Storage::disk('local')->get($document->file_path));
        }
    }

    public function test_repeated_showcase_seed_preserves_roster_reviews_and_does_not_restore_archived_people(): void
    {
        $seeder = app(ShowcaseSeeder::class);
        $seeder->provision();
        $talent = AgencyTalent::firstOrFail();
        $profile = $talent->agency->agencyProfile;
        $profile->update(['status'=>'pending','bio'=>'Edited agency overview']);
        $talent->update(['status'=>'declined','followers'=>321]);
        $talent->delete();
        $creator = CreatorProfile::where('status','pending')->firstOrFail();
        $creator->delete();
        $seeder->provision();
        $this->assertSame('pending', $profile->refresh()->status);
        $this->assertSame('Edited agency overview', $profile->bio);
        $this->assertSoftDeleted('agency_talents',['id'=>$talent->id]);
        $this->assertSoftDeleted('creator_profiles',['id'=>$creator->id]);
        $this->assertSame(321, AgencyTalent::withTrashed()->findOrFail($talent->id)->followers);
        $this->assertSame(4, AgencyTalent::withTrashed()->count());
        $this->assertSame(10, CreatorProfile::withTrashed()->count());
    }

    public function test_production_rerun_keeps_legacy_selected_creators_when_adding_agency_offers(): void
    {
        $seeder = app(ShowcaseSeeder::class);
        $seeder->provision();
        $selected = [];
        foreach (['tournament-partners','cross-market-play'] as $slug) {
            $brief = Brief::where('demo_key', ShowcaseSeeder::PREFIX.$slug)->sole();
            $oldAgencyOffers = $brief->invitations()->whereNotNull('agency_user_id')->pluck('id');
            DeliverySubmission::whereIn('invitation_id',$oldAgencyOffers)->delete();
            PaymentMilestone::whereIn('invitation_id',$oldAgencyOffers)->delete();
            $brief->invitations()->whereNotNull('agency_user_id')->delete();
            $lead = $brief->invitations()->whereNotNull('creator_profile_id')->oldest('id')->firstOrFail();
            $lead->update(['selection_status'=>'selected','selected_at'=>now()->subDays(3),'introduced_at'=>now()->subDays(3)]);
            $selected[$brief->id] = $lead->id;
        }
        $this->app['env'] = 'production';
        try { $seeder->provision(true); } finally { $this->app['env'] = 'testing'; }
        foreach ($selected as $briefId=>$invitationId) {
            $lead = Invitation::where('brief_id',$briefId)->where('selection_status','selected')->sole();
            $this->assertSame($invitationId, $lead->id);
            $this->assertNull($lead->agency_user_id);
            $this->assertDatabaseHas('invitations',['brief_id'=>$briefId,'selection_status'=>'not_selected']);
        }
    }

    public function test_sample_delivery_and_payment_records_belong_only_to_selected_demo_providers(): void
    {
        app(ShowcaseSeeder::class)->provision();
        $this->assertGreaterThan(0, DeliverySubmission::count());
        $this->assertGreaterThan(0, PaymentMilestone::count());
        foreach (DeliverySubmission::with('invitation.brief','user')->get() as $submission) {
            $this->assertTrue($submission->user->is_demo);
            $this->assertTrue($submission->invitation->brief->is_demo);
            $this->assertSame('selected',$submission->invitation->selection_status);
            $this->assertContains($submission->invitation->brief->status,['live','completed']);
            $this->assertSame($submission->invitation->brief->status === 'completed' ? 'approved' : 'submitted',$submission->status);
        }
        foreach (PaymentMilestone::with('invitation.brief')->get() as $milestone) {
            $this->assertTrue($milestone->invitation->brief->is_demo);
            $this->assertSame('selected',$milestone->invitation->selection_status);
            $this->assertSame($milestone->invitation->currency,$milestone->currency);
            $this->assertSame('pending',$milestone->status);
            $this->assertNull($milestone->paid_at);
            $this->assertNull($milestone->approved_by);
        }
    }

    public function test_legacy_generated_provider_documents_remove_client_titles_without_replacing_custom_files(): void
    {
        $seeder = app(ShowcaseSeeder::class);
        $seeder->provision();
        $documents = CampaignDocument::where('audience','creator')->with('brief','creatorProfile')->get();
        $legacy = $documents->first();
        Storage::disk('local')->put($legacy->file_path, "ALL IN STREAMS — FICTIONAL SAMPLE DOCUMENT\n\nThis is not a legally binding agreement. Signature status is simulated for workflow demonstration.\nCampaign: ".$legacy->brief->title."\nAudience: creator\nCreator: ".$legacy->creatorProfile->public_name."\n");
        $custom = $documents->last();
        $customPath = 'campaign-documents/custom-provider-file.txt';
        Storage::disk('local')->put($customPath, 'Custom uploaded document: keep this content.');
        $custom->update(['file_path'=>$customPath]);
        $signature = $legacy->signature_status;
        $seeder->provision();
        $content = Storage::disk('local')->get($legacy->file_path);
        $this->assertStringNotContainsString($legacy->brief->title, $content);
        $this->assertStringContainsString('Campaign reference: '.$legacy->brief->code, $content);
        $this->assertSame($signature, $legacy->refresh()->signature_status);
        $this->assertSame('Custom uploaded document: keep this content.', Storage::disk('local')->get($custom->refresh()->file_path));
    }

    public function test_failure_to_persist_credentials_rolls_back_new_database_records(): void
    {
        try {
            app(ShowcaseSeeder::class)->provision(false, function () {
                throw new LogicException('Simulated private-file failure.');
            });
            $this->fail('The private-file error should roll back the seed transaction.');
        } catch (LogicException $exception) {
            $this->assertSame('Simulated private-file failure.', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('briefs', 0);
        $this->assertDatabaseCount('campaign_metrics', 0);
    }
}
