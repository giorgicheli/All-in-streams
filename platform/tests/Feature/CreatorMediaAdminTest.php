<?php

namespace Tests\Feature;

use App\Filament\Pages\CreatorVideoSettings;
use App\Filament\Resources\CreatorProfileResource\Pages\ManageCreators;
use App\Filament\Resources\InfluencerProfileResource\Pages\ManageInfluencers;
use App\Models\CreatorMediaSetting;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\CreatorProofVideo;
use App\Services\CreatorMediaProcessor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorMediaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        Storage::fake('local');
    }

    private function creator(array $values = []): CreatorProfile
    {
        return CreatorProfile::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'creator'])->id,
            'creator_type' => 'streamer', 'public_name' => 'Private creator', 'country' => 'Germany', 'language' => 'English',
            'platform' => 'Twitch', 'channel_url' => 'https://www.twitch.tv/examplecreator', 'audience_geos' => ['Canada'],
            'vertical' => 'Gaming', 'rate' => 300, 'commercial_model' => 'Fixed', 'status' => 'pending',
        ], $values));
    }

    private function staff(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        return $user;
    }

    public function test_staff_searches_separate_streamer_and_influencer_directories_by_audience_location_and_budget(): void
    {
        $canadianAudience = $this->creator();
        $expensive = $this->creator(['country' => 'Canada', 'rate' => 1500]);
        $differentAudience = $this->creator(['audience_geos' => ['France']]);
        $influencer = $this->creator(['creator_type' => 'influencer', 'platform' => 'Instagram']);
        $this->staff();
        Livewire::test(ManageCreators::class)->assertCanSeeTableRecords([$canadianAudience, $expensive, $differentAudience])->assertCanNotSeeTableRecords([$influencer])
            ->filterTable('audience_country', 'Canada')->filterTable('budget', ['maximum_rate' => 500])
            ->assertCanSeeTableRecords([$canadianAudience])->assertCanNotSeeTableRecords([$expensive, $differentAudience, $influencer]);
        Livewire::test(ManageCreators::class)->filterTable('country', 'Canada')->assertCanSeeTableRecords([$expensive])->assertCanNotSeeTableRecords([$canadianAudience]);
        Livewire::test(ManageInfluencers::class)->assertCanSeeTableRecords([$influencer])->assertCanNotSeeTableRecords([$canadianAudience, $expensive]);
    }

    public function test_only_staff_can_view_or_delete_private_proof_video_even_when_owner_knows_route(): void
    {
        $creator = $this->creator(['verification_video_path' => 'creator-proof/private.mp4', 'verification_video_mime' => 'video/mp4', 'verification_video_size' => 20]);
        Storage::disk('local')->put($creator->verification_video_path, 'private proof video');
        $route = route('admin.creator-video', $creator);
        $this->get($route)->assertRedirect(route('login'));
        foreach ([$creator->user, User::factory()->create(['role' => 'business']), User::factory()->create(['role' => 'agency'])] as $actor) {
            $this->actingAs($actor)->get($route)->assertForbidden();
            try { app(CreatorProofVideo::class)->delete($creator); $this->fail('Non-admin deletion succeeded'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
        $this->staff();
        $this->get($route)->assertOk()->assertHeader('Content-Type', 'video/mp4')->assertHeader('X-Content-Type-Options', 'nosniff');
        Livewire::test(ManageCreators::class)->callTableAction('deleteProofVideo', $creator)->assertHasNoActionErrors();
        $this->assertNull($creator->fresh()->verification_video_path);
        Storage::disk('local')->assertMissing('creator-proof/private.mp4');
        $this->get($route)->assertNotFound();
    }

    public function test_staff_controls_dynamic_limit_and_cannot_enable_unavailable_compressor(): void
    {
        $this->staff();
        $this->partialMock(CreatorProofVideo::class, fn ($mock) => $mock->shouldReceive('available')->andReturn(false));
        Livewire::test(CreatorVideoSettings::class)->fillForm(['max_upload_mb' => 5, 'max_duration_seconds' => 45, 'compression_enabled' => false, 'compression_crf' => 28, 'compression_max_width' => 1280])->call('save')->assertHasNoFormErrors();
        $this->assertSame(5, CreatorMediaSetting::current()->max_upload_mb);
        Livewire::test(CreatorVideoSettings::class)->fillForm(['max_upload_mb' => 101, 'max_duration_seconds' => 121, 'compression_enabled' => false, 'compression_crf' => 28, 'compression_max_width' => 1280])->call('save')->assertHasFormErrors(['max_upload_mb', 'max_duration_seconds']);
        Livewire::test(CreatorVideoSettings::class)->fillForm(['max_upload_mb' => 5, 'max_duration_seconds' => 45, 'compression_enabled' => true, 'compression_crf' => 28, 'compression_max_width' => 1280])->call('save')->assertHasFormErrors(['compression_enabled']);
        $this->assertFalse(CreatorMediaSetting::current()->compression_enabled);
    }

    public function test_upload_size_is_checked_before_any_video_processing_and_invalid_files_never_persist(): void
    {
        CreatorMediaSetting::current()->update(['max_upload_mb' => 1]);
        $service = app(CreatorProofVideo::class);
        $this->assertContains('max:1024', $service->rules());
        foreach ([UploadedFile::fake()->create('large.mp4', 1100, 'video/mp4'), UploadedFile::fake()->createWithContent('pretend.mp4', '<?php echo "not a video";')] as $file) {
            try { $service->store($file); $this->fail('Invalid upload was accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('verification_video', $e->errors()); }
        }
        $this->assertSame([], Storage::disk('local')->allFiles('creator-proof'));
    }

    public function test_deployment_request_ceiling_limits_both_admin_setting_and_effective_uploads(): void
    {
        config(['creator-media.request_max_mb' => 49]);
        CreatorMediaSetting::current()->update(['max_upload_mb' => 80]);
        $this->assertSame(49, app(CreatorProofVideo::class)->requestMaxMb());
        $this->assertLessThanOrEqual(49, app(CreatorProofVideo::class)->maxUploadMb());
        $this->staff();
        $this->partialMock(CreatorProofVideo::class, fn ($mock) => $mock->shouldReceive('available')->andReturn(false));
        Livewire::test(CreatorVideoSettings::class)->fillForm(['max_upload_mb' => 50, 'max_duration_seconds' => 120, 'compression_enabled' => false, 'compression_crf' => 28, 'compression_max_width' => 1280])->call('save')->assertHasFormErrors(['max_upload_mb']);
        Livewire::test(CreatorVideoSettings::class)->fillForm(['max_upload_mb' => 49, 'max_duration_seconds' => 120, 'compression_enabled' => false, 'compression_crf' => 28, 'compression_max_width' => 1280])->call('save')->assertHasNoFormErrors();
        $this->assertSame(49, CreatorMediaSetting::current()->max_upload_mb);
    }

    public function test_proof_validation_fails_closed_when_media_processor_is_missing(): void
    {
        config(['creator-media.ffprobe' => 'nonexistent-all-in-streams-ffprobe']);
        // Real MP4 signature passes MIME detection, but a signature alone cannot establish a playable short video.
        $file = UploadedFile::fake()->createWithContent('proof.mp4', pack('N', 24).'ftypisom'.pack('N', 512).'isomiso2'.str_repeat("\0", 64));
        try { app(CreatorProofVideo::class)->store($file); $this->fail('Unverified MP4 was accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('verification_video', $e->errors()); }
        $this->assertSame([], Storage::disk('local')->allFiles('creator-proof'));
    }

    public function test_private_worker_transport_requires_loopback_and_sends_only_fixed_operations(): void
    {
        config(['creator-media.worker_url' => 'http://127.0.0.1:18743', 'creator-media.worker_token' => str_repeat('a', 64)]);
        Http::fake(['http://127.0.0.1:18743/media' => Http::response(['result' => ['ffprobe' => true, 'ffmpeg' => false]])]);
        $this->assertTrue(app(CreatorProofVideo::class)->available('ffprobe'));
        $this->assertFalse(app(CreatorProofVideo::class)->available('ffmpeg'));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.str_repeat('a', 64)) && $request['operation'] === 'capabilities');
        Http::assertSentCount(1);
        config(['creator-media.worker_url' => 'http://example.com:18743']);
        $this->assertFalse(app(CreatorProofVideo::class)->available('ffprobe'));
    }

    public function test_private_worker_rejects_traversal_and_arbitrary_commands_before_starting_any_process(): void
    {
        $processor = app(CreatorMediaProcessor::class);
        foreach ([['operation' => 'shell', 'command' => 'whoami'], ['operation' => 'probe', 'source' => '../secret.mp4'], ['operation' => 'probe', 'source' => '/tmp/private.mp4'], ['operation' => 'compress', 'source' => 'creator-proof/not-a-uuid.mp4']] as $request) {
            try { $processor->execute($request); $this->fail('Unsafe worker request was accepted'); }
            catch (\RuntimeException $e) { $this->assertContains($e->getMessage(), ['Unsupported media operation.', 'Invalid private media path.']); }
        }
    }
}
