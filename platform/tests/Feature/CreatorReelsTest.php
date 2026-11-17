<?php

namespace Tests\Feature;

use App\Filament\Pages\WebsiteEditor;
use App\Models\SitePage;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorReelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('r', 32))]);
        $this->withoutVite();
    }

    public function test_old_settings_receive_the_public_showcase_without_changing_saved_drafts(): void
    {
        $published = SiteContentDefaults::settings();
        unset($published['creator_reels']);
        $published['network_stats']['cards'][0]['count'] = 333;
        $draft = $published;
        $draft['brand_name'] = 'Unpublished brand name';
        SitePage::create(['slug' => 'settings', 'title' => 'Site settings', 'published_content' => $published, 'draft_content' => $draft]);

        $content = app(SiteContent::class);
        $this->assertSame(SiteContentDefaults::settings()['creator_reels'], $content->getPublished('settings')['creator_reels']);
        $this->assertSame(333, $content->getPublished('settings')['network_stats']['cards'][0]['count']);
        $this->assertSame('Unpublished brand name', $content->getDraft('settings')['brand_name']);
        $page = SitePage::where('slug', 'settings')->firstOrFail();
        $this->assertSame($published, $page->published_content);
        $this->assertSame($draft, $page->draft_content);
        $this->assertSame(0, $page->revisions()->count());
    }

    public function test_managed_public_preview_upload_paths_are_canonicalized(): void
    {
        $settings = SiteContentDefaults::settings();
        $settings['creator_reels']['clips'][0]['video_path'] = '/storage/site-content/reels/preview-clip.mp4';
        $settings['creator_reels']['clips'][0]['poster_path'] = '/storage/site-content/reels/preview-poster.webp';
        $validated = app(SiteContent::class)->validate('settings', $settings);
        $this->assertSame('site-content/reels/preview-clip.mp4', $validated['creator_reels']['clips'][0]['video_path']);
        $this->assertSame('site-content/reels/preview-poster.webp', $validated['creator_reels']['clips'][0]['poster_path']);
    }

    public function test_private_external_and_executable_media_paths_are_rejected(): void
    {
        foreach ([
            ['video_path', 'creator-verification/private.mp4'],
            ['video_path', '/storage/creator-verification/private.mp4'],
            ['video_path', 'https://example.com/clip.mp4'],
            ['video_path', 'site-content/reels/../../private.mp4'],
            ['video_path', 'site-content/reels/code.php'],
            ['poster_path', 'site-content/reels/executable.svg'],
            ['poster_path', 'site-content/unmanaged.jpg'],
            ['poster_path', '//example.com/poster.jpg'],
        ] as [$field, $path]) {
            $settings = SiteContentDefaults::settings();
            $settings['creator_reels']['clips'][0][$field] = $path;
            try {
                app(SiteContent::class)->validate('settings', $settings);
                $this->fail('Unmanaged creator-preview media was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('creator_reels.clips.0.'.$field, $exception->errors());
            }
        }
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_only_known_sample_presets_and_a_bounded_clip_list_are_accepted(): void
    {
        foreach (['unknown', '../private', 'https://example.com/clip'] as $preset) {
            $settings = SiteContentDefaults::settings();
            $settings['creator_reels']['clips'][0]['sample_clip'] = $preset;
            try {
                app(SiteContent::class)->validate('settings', $settings);
                $this->fail('Unknown sample footage was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('creator_reels.clips.0.sample_clip', $exception->errors());
            }
        }
        foreach ([1, 13] as $count) {
            $settings = SiteContentDefaults::settings();
            $settings['creator_reels']['clips'] = array_fill(0, $count, $settings['creator_reels']['clips'][0]);
            try {
                app(SiteContent::class)->validate('settings', $settings);
                $this->fail('An unsupported clip count was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('creator_reels.clips', $exception->errors());
            }
        }
    }

    public function test_showcase_copy_and_visibility_are_editable_through_the_existing_admin_editor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $editor = Livewire::test(WebsiteEditor::class)->call('selectPage', 'settings')
            ->set('data.creator_reels.title', 'Our public format library')
            ->set('data.creator_reels.enabled', false)
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertTrue(app(SiteContent::class)->getPublished('settings')['creator_reels']['enabled']);
        $editor->call('publish')->assertHasNoErrors();
        $published = app(SiteContent::class)->getPublished('settings');
        $this->assertFalse($published['creator_reels']['enabled']);
        $this->assertSame('Our public format library', $published['creator_reels']['title']);
        $this->assertCount(6, $published['creator_reels']['clips']);
    }

    public function test_video_markup_starts_with_posters_and_contains_three_distinct_rows(): void
    {
        $html = Blade::render("@include('site.blocks.creator-reels', ['data' => \$data])", ['data' => SiteContentDefaults::settings()['creator_reels']]);
        $this->assertStringContainsString('data-reel-row="forward"', $html);
        $this->assertStringContainsString('data-reel-row="still"', $html);
        $this->assertStringContainsString('data-reel-row="reverse"', $html);
        $this->assertStringContainsString('aria-hidden="true" inert', $html);
        $this->assertStringContainsString('data-src="/videos/creator-reels/studio.mp4"', $html);
        $this->assertStringContainsString('poster="/images/creator-reels/studio.jpg"', $html);
        $this->assertDoesNotMatchRegularExpression('/<video\b[^>]*(?<!data-)\ssrc=/', $html);
        $this->assertStringContainsString('Format preview', $html);
        $this->assertStringNotContainsString('autoplay', $html);
    }

    public function test_disabling_the_showcase_omits_media_and_controls(): void
    {
        $data = SiteContentDefaults::settings()['creator_reels'];
        $data['enabled'] = false;
        $html = Blade::render("@include('site.blocks.creator-reels', ['data' => \$data])", ['data' => $data]);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('data-reel-control', $html);
    }
}
