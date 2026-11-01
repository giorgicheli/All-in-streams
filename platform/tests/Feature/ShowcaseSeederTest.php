<?php

namespace Tests\Feature;

use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CampaignMetric;
use App\Models\CreatorProfile;
use App\Models\Invitation;
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
        $counts = [User::count(), Brief::count(), CreatorProfile::count(), Invitation::count(), CampaignDocument::count(), CampaignMetric::count()];
        $credentials = file_get_contents($file);

        $this->artisan('showcase:seed', ['--credentials-file' => $file])->expectsOutput('New sample accounts: 0')->assertSuccessful();

        $this->assertSame($counts, [User::count(), Brief::count(), CreatorProfile::count(), Invitation::count(), CampaignDocument::count(), CampaignMetric::count()]);
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
            foreach ($brief->invitations()->where('status', 'interested')->get() as $invitation) {
                $this->assertSame('approved', $invitation->creatorProfile->status);
                $this->assertNotNull($invitation->introduced_at);
                $this->assertTrue($brief->documents()->where('creator_profile_id', $invitation->creator_profile_id)->where('signature_status', 'signed')->exists());
            }
        }
        foreach (CampaignDocument::all() as $document) {
            Storage::disk('local')->assertExists($document->file_path);
            $this->assertStringContainsString('FICTIONAL SAMPLE DOCUMENT', Storage::disk('local')->get($document->file_path));
        }
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
