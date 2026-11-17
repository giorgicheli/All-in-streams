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
        $orderedClips = array_reverse(SiteContentDefaults::settings()['creator_reels']['clips']);
        $orderedClips[0]['video_source_url'] = 'https://media.example.com/from-editor.mp4';
        $orderedClips[0]['link_url'] = 'https://kick.com/example_creator';
        $editor = Livewire::test(WebsiteEditor::class)->call('selectPage', 'settings')
            ->set('data.creator_reels.title', 'Our public format library')
            ->set('data.creator_reels.enabled', false)
            ->set('data.creator_reels.clips', $orderedClips)
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertTrue(app(SiteContent::class)->getPublished('settings')['creator_reels']['enabled']);
        $editor->call('publish')->assertHasNoErrors();
        $published = app(SiteContent::class)->getPublished('settings');
        $this->assertFalse($published['creator_reels']['enabled']);
        $this->assertSame('Our public format library', $published['creator_reels']['title']);
        $this->assertCount(9, $published['creator_reels']['clips']);
        $this->assertSame($orderedClips, $published['creator_reels']['clips']);
    }

    public function test_video_markup_starts_with_posters_and_contains_three_distinct_rows(): void
    {
        $html = Blade::render("@include('site.blocks.creator-reels', ['data' => \$data])", ['data' => SiteContentDefaults::settings()['creator_reels']]);
        $this->assertStringContainsString('data-reel-row="forward"', $html);
        $this->assertStringContainsString('data-reel-row="still"', $html);
        $this->assertStringContainsString('data-reel-row="reverse"', $html);
        $this->assertStringContainsString('aria-hidden="true" data-reel-clone', $html);
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

    public function test_old_authored_clips_gain_optional_links_without_reordering_or_replacing_content(): void
    {
        $settings = SiteContentDefaults::settings();
        unset($settings['brand_spotlight']);
        $settings['creator_reels']['clips'] = array_reverse(array_slice($settings['creator_reels']['clips'], 0, 4));
        foreach ($settings['creator_reels']['clips'] as &$clip) unset($clip['video_source_url'], $clip['link_url']);
        unset($clip);
        SitePage::create(['slug' => 'settings', 'title' => 'Site settings', 'published_content' => $settings, 'draft_content' => $settings]);

        $published = app(SiteContent::class)->getPublished('settings');
        $this->assertSame(array_column($settings['creator_reels']['clips'], 'sample_clip'), array_column($published['creator_reels']['clips'], 'sample_clip'));
        foreach ($published['creator_reels']['clips'] as $clip) {
            $this->assertSame('', $clip['video_source_url']);
            $this->assertSame('', $clip['link_url']);
        }
        $this->assertSame(SiteContentDefaults::settings()['brand_spotlight'], $published['brand_spotlight']);
        $this->assertSame($settings, SitePage::where('slug', 'settings')->value('published_content'));
    }

    public function test_optional_video_and_watch_links_accept_public_sources_and_reject_unsafe_urls(): void
    {
        $settings = SiteContentDefaults::settings();
        $settings['creator_reels']['clips'][0]['video_source_url'] = 'https://media.example.com/creator.mp4?token=public-preview';
        $settings['creator_reels']['clips'][0]['link_url'] = 'https://www.twitch.tv/example_creator';
        $validated = app(SiteContent::class)->validate('settings', $settings);
        $this->assertSame($settings['creator_reels']['clips'][0], $validated['creator_reels']['clips'][0]);

        foreach ([
            'http://media.example.com/clip.mp4', 'javascript:alert(1)', '//media.example.com/clip.mp4',
            '/storage/creator-verification/private.mp4', 'https://localhost/clip.mp4', 'https://127.0.0.1/clip.mp4',
            'https://192.168.0.2/clip.mp4', 'https://10.0.0.2/clip.mp4', 'https://[::1]/clip.mp4',
            'https://user:password@media.example.com/clip.mp4', 'https://media.example.com/clip.mp4#fragment',
            'https://www.youtube.com/watch?v=example', 'https://media.example.com/clip.php',
            'https://media.example.com/creator-verification/private.mp4',
        ] as $url) {
            $settings['creator_reels']['clips'][0]['video_source_url'] = $url;
            try {
                app(SiteContent::class)->validate('settings', $settings);
                $this->fail('An unsupported video source was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('creator_reels.clips.0.video_source_url', $exception->errors());
            }
        }
        $settings['creator_reels']['clips'][0]['video_source_url'] = '';
        $settings['creator_reels']['clips'][0]['link_url'] = 'javascript:alert(1)';
        $this->expectException(ValidationException::class);
        app(SiteContent::class)->validate('settings', $settings);
    }

    public function test_admin_order_and_link_pairs_are_kept_in_distinct_public_rows(): void
    {
        $settings = SiteContentDefaults::settings();
        $settings['creator_reels']['clips'] = array_reverse($settings['creator_reels']['clips']);
        foreach ($settings['creator_reels']['clips'] as $index => &$clip) {
            $clip['video_source_url'] = 'https://media.example.com/ordered-'.$index.'.mp4';
            $clip['link_url'] = 'https://www.youtube.com/watch?v=ordered-'.$index;
        }
        unset($clip);
        $admin = User::factory()->create(['role' => 'admin']);
        app(SiteContent::class)->publish($admin, 'settings', $settings);
        $published = app(SiteContent::class)->getPublished('settings');
        $this->assertSame($settings['creator_reels']['clips'], $published['creator_reels']['clips']);
        $html = Blade::render("@include('site.blocks.creator-reels', ['data' => \$data])", ['data' => $published['creator_reels']]);
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//*[@data-reel-row]');
        $positions = [];
        foreach ($rows as $rowIndex => $row) {
            $originals = $xpath->query('.//article[not(@aria-hidden) and not(ancestor::*[@aria-hidden="true"])]', $row);
            $this->assertSame(3, $originals->length);
            foreach ($originals as $articleIndex => $article) {
                $index = $rowIndex * 3 + $articleIndex;
                $positions[] = (int) $article->getAttribute('data-reel-position');
                $this->assertSame($settings['creator_reels']['clips'][$index]['video_source_url'], $xpath->query('.//video', $article)->item(0)->getAttribute('data-src'));
                $this->assertSame($settings['creator_reels']['clips'][$index]['link_url'], $xpath->query('.//a[@data-reel-link]', $article)->item(0)->getAttribute('href'));
            }
        }
        $this->assertSame(range(1, 9), $positions);
        $this->assertCount(9, array_unique(array_column($settings['creator_reels']['clips'], 'sample_clip')));
        foreach ($xpath->query('//*[@data-reel-clone]//a[@data-reel-link]') as $link) {
            $this->assertSame('-1', $link->getAttribute('tabindex'));
            $this->assertSame(0, $xpath->query('ancestor::*[@inert]', $link)->length);
        }
    }

    public function test_brand_spotlight_respects_draft_publication_and_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SiteContentDefaults::settings();
        $settings['brand_spotlight']['title_line_1'] = 'An authored brand headline';
        app(SiteContent::class)->saveDraft($admin, 'settings', $settings);
        $this->get('/')->assertOk()->assertSee('Brands meet')->assertDontSee('An authored brand headline');

        app(SiteContent::class)->publish($admin, 'settings');
        $page = $this->get('/')->assertOk()->assertSee('An authored brand headline')
            ->assertSee('data-creator-orbit', false);
        $document = new \DOMDocument();
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//section[@id="top"]//*[@data-creator-orbit]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-creator-orbit]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="platform"]//*[@data-creator-orbit]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-brand-spotlight]//img[contains(@class,"brand-spotlight-photo")]')->length);
        $this->assertSame(4, $xpath->query('//section[@id="network"]//article[contains(@class,"network-stat-card")]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " hero-stage ")]/*[contains(concat(" ", normalize-space(@class), " "), " geo-strip ")]')->length);

        $settings['creator_orbit']['enabled'] = false;
        app(SiteContent::class)->publish($admin, 'settings', $settings);
        $this->get('/')->assertOk()->assertSee('/images/brand-spotlight/streamer.jpg')
            ->assertSee('Partnership example')->assertDontSee('data-creator-orbit')
            ->assertSee('data-rope-statistics', false)->assertDontSee('network-after-hero');

        $settings['brand_spotlight']['enabled'] = false;
        app(SiteContent::class)->publish($admin, 'settings', $settings);
        $this->get('/')->assertOk()->assertDontSee('data-brand-spotlight')
            ->assertSee('Your growth. Our game plan.')->assertSee('For agencies')->assertSee('For creators');
    }
}
