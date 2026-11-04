<?php

namespace Tests\Feature;

use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserFile;
use App\Services\CreatorProofVideo;
use App\Services\DemoRoleWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class DemoRoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
        $this->directory = storage_path('framework/testing/demo-roles-'.Str::uuid());
        File::makeDirectory($this->directory, 0700, true);
        file_put_contents($this->directory.'/sample-agreement.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        $zip = new ZipArchive;
        $zip->open($this->directory.'/internal-notes.docx', ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>DEMO ONLY — Internal notes</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        file_put_contents($this->directory.'/verification-demo.mp4', 'DEMO TEST FIXTURE — video processing is mocked in this workspace test.');
        // Playback, duration, compression and upload format are tested by the video service suite.
        $this->app->instance(CreatorProofVideo::class, new class extends CreatorProofVideo {
            public function store(UploadedFile $file): array
            {
                $path = 'creator-proof/'.Str::uuid().'.mp4';
                Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
                return ['verification_video_path' => $path, 'verification_video_original_name' => $file->getClientOriginalName(), 'verification_video_mime' => 'video/mp4', 'verification_video_size' => $file->getSize()];
            }
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function seedRoles(?string $credentials = null): int
    {
        return Artisan::call('demo:roles', ['--fixtures-dir' => $this->directory, '--credentials-file' => $credentials ?: $this->directory.'/access.json']);
    }

    public function test_command_creates_all_five_roles_with_private_files_and_coherent_stages_without_printing_passwords(): void
    {
        $this->assertSame(0, $this->seedRoles());
        $output = Artisan::output();
        $accounts = json_decode(file_get_contents($this->directory.'/access.json'), true)['accounts'];
        $this->assertCount(5, $accounts);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('creator_profiles', 2);
        $this->assertDatabaseCount('agency_profiles', 1);
        $this->assertDatabaseCount('briefs', 4);
        $this->assertDatabaseCount('invitations', 5);
        $this->assertDatabaseCount('campaign_documents', 6);
        $this->assertDatabaseCount('user_files', 10);
        $this->assertDatabaseCount('payment_milestones', 0);
        foreach ($accounts as $account) {
            $user = User::where('email', $account['email'])->sole();
            $this->assertTrue($user->is_demo);
            $this->assertStringStartsWith('@ais_demo_', $user->telegram);
            $this->assertTrue(Hash::check($account['password'], $user->password));
            $this->assertTrue($account['password_is_current']);
            $this->assertStringNotContainsString($account['password'], $output);
            $this->assertSame(2, $user->internalFiles()->count());
        }
        $streamer = CreatorProfile::where('creator_type', 'streamer')->sole();
        $influencer = CreatorProfile::where('creator_type', 'influencer')->sole();
        $this->assertNotNull($streamer->twitch_url);
        $this->assertNull($influencer->twitch_url);
        $this->assertNotNull($influencer->instagram_url);
        foreach ([$streamer, $influencer] as $profile) {
            $this->assertSame('Canada', $profile->country);
            $this->assertContains('Canada', $profile->audience_geos);
            Storage::disk('local')->assertExists($profile->verification_video_path);
            Storage::disk('public')->assertMissing($profile->verification_video_path);
        }
        foreach (Brief::where('status', 'contracts')->get() as $brief) {
            $this->assertSame(1, $brief->selectedInvitations()->count());
            $this->assertFalse($brief->payment_ready);
            $this->assertSame(2, $brief->documents()->where('signature_status', 'uploaded')->count());
        }
        $this->assertDatabaseHas('invitations', ['status' => 'pending', 'selection_status' => 'unselected']);
        $this->assertDatabaseHas('invitations', ['status' => 'changes_requested', 'counter_amount' => 550]);
        foreach (UserFile::all() as $file) {
            Storage::disk('local')->assertExists($file->file_path);
            Storage::disk('public')->assertMissing($file->file_path);
        }
    }

    public function test_rerun_is_idempotent_and_preserves_existing_passwords_profile_edits_and_workflow_progress(): void
    {
        $this->assertSame(0, $this->seedRoles());
        $before = json_decode(file_get_contents($this->directory.'/access.json'), true);
        $real = User::factory()->create(['role' => 'business', 'name' => 'Real Account', 'is_demo' => false]);
        $user = User::where('email', 'streamer@demo.allinstreams.example')->sole();
        $user->update(['password' => 'Changed-By-Test-User-Only!']);
        $profile = $user->creatorProfile;
        $profile->update(['public_name' => 'Edited by staff', 'rate' => 777]);
        $pending = Invitation::where('status', 'pending')->sole();
        $pending->update(['status' => 'declined', 'response_notes' => 'Edited response']);
        $document = CampaignDocument::first();
        $document->update(['title' => 'Staff renamed this agreement']);
        $this->assertSame(0, $this->seedRoles());
        $after = json_decode(file_get_contents($this->directory.'/access.json'), true);
        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseCount('briefs', 4);
        $this->assertDatabaseCount('invitations', 5);
        $this->assertDatabaseCount('campaign_documents', 6);
        $this->assertDatabaseCount('user_files', 10);
        $this->assertTrue(Hash::check('Changed-By-Test-User-Only!', $user->fresh()->password));
        $this->assertSame('Edited by staff', $profile->fresh()->public_name);
        $this->assertSame('777.00', $profile->fresh()->rate);
        $this->assertSame('declined', $pending->fresh()->status);
        $this->assertSame('Staff renamed this agreement', $document->fresh()->title);
        $this->assertSame('Real Account', $real->fresh()->name);
        $streamer = collect($after['accounts'])->firstWhere('role', 'streamer');
        $this->assertFalse($streamer['password_is_current']);
        foreach ($before['accounts'] as $account) $this->assertSame($account['password'], collect($after['accounts'])->firstWhere('email', $account['email'])['password']);
    }

    public function test_demo_contracts_and_internal_files_are_separated_between_business_and_all_provider_roles(): void
    {
        $this->assertSame(0, $this->seedRoles());
        $business = User::where('email', 'business@demo.allinstreams.example')->sole();
        foreach (['streamer', 'influencer', 'agency'] as $role) {
            $provider = User::where('email', $role.'@demo.allinstreams.example')->sole();
            $brief = Brief::where('demo_key', DemoRoleWorkspace::WORKSPACE.'-'.$role)->sole();
            $providerDocument = $brief->documents()->where('audience', $role === 'agency' ? 'agency' : 'creator')->sole();
            $businessDocument = $brief->documents()->where('audience', 'business')->sole();
            $this->actingAs($provider)->get(route('documents.download', $providerDocument))->assertDownload();
            $this->get(route('documents.download', $businessDocument))->assertForbidden();
            $this->get(route('admin.user-files.download', $provider->internalFiles()->first()))->assertForbidden();
            $this->actingAs($business)->get(route('documents.download', $providerDocument))->assertForbidden();
            $this->get(route('documents.download', $businessDocument))->assertDownload();
        }
    }

    public function test_command_refuses_public_credentials_existing_real_accounts_or_invalid_fixtures_without_changes(): void
    {
        $this->assertSame(1, $this->seedRoles(public_path('forbidden-demo-passwords.json')));
        $this->assertDatabaseCount('users', 0);
        $this->assertFileDoesNotExist(public_path('forbidden-demo-passwords.json'));
        User::factory()->create(['email' => 'admin@demo.allinstreams.example', 'role' => 'admin', 'is_demo' => false]);
        $this->assertSame(1, $this->seedRoles());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_files', 0);
        $this->assertFileDoesNotExist($this->directory.'/access.json');
        file_put_contents($this->directory.'/sample-agreement.pdf', 'Not a PDF');
        $this->assertSame(1, $this->seedRoles());
        $this->assertDatabaseCount('users', 1);
    }
}
