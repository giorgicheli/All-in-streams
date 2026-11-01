<?php

namespace Tests\Feature;

use App\Models\SitePage;
use App\Models\User;
use App\Rules\SiteLink;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_default_reads_preserve_existing_design_without_writing_records(): void
    {
        $content = app(SiteContent::class);
        $this->assertSame(SiteContentDefaults::home(), $content->getPublished());
        $this->assertSame(SiteContentDefaults::settings(), $content->getDraft('settings'));
        $this->assertDatabaseCount('site_pages', 0);
        $this->assertSame(SiteContentDefaults::home(), $content->validate('home', SiteContentDefaults::home()));
        $this->assertSame(SiteContentDefaults::settings(), $content->validate('settings', SiteContentDefaults::settings()));
    }

    public function test_draft_changes_are_private_until_explicit_publication(): void
    {
        $service = app(SiteContent::class);
        $admin = $this->admin();
        $draft = SiteContentDefaults::home();
        $draft['blocks'][0]['data']['title_line_1'] = 'Your next partnership';
        $page = $service->saveDraft($admin, 'home', $draft);

        $this->assertSame($admin->id, $page->updated_by);
        $this->assertNull($page->published_at);
        $this->assertSame($draft, $service->getDraft());
        $this->assertSame(SiteContentDefaults::home(), $service->getPublished());

        $service->publish($admin, 'home');
        $this->assertSame($draft, $service->getPublished());
        $this->assertNotNull($page->refresh()->published_at);
        $this->assertSame(SiteContentDefaults::settings(), $service->getPublished('settings'));
        $this->assertSame(2, $page->revisions()->count());
    }

    public function test_restoring_an_audited_version_changes_only_the_draft(): void
    {
        $admin = $this->admin();
        $service = app(SiteContent::class);
        $original = SiteContentDefaults::settings();
        $changed = $original;
        $changed['brand_name'] = 'A new brand';
        $page = $service->publish($admin, 'settings', $changed);
        $baseline = $page->revisions()->oldest('id')->firstOrFail();

        $service->restoreRevision($admin, $baseline->id);
        $this->assertSame($original, $service->getDraft('settings'));
        $this->assertSame($changed, $service->getPublished('settings'));

        $service->publish($admin, 'settings');
        $this->assertSame($original, $service->getPublished('settings'));
        $this->assertSame(3, $page->revisions()->count());
    }

    public function test_non_staff_cannot_save_publish_or_restore_content(): void
    {
        $service = app(SiteContent::class);
        foreach (['business', 'creator'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['save', 'publish', 'restore'] as $operation) {
                try {
                    match ($operation) {
                        'save' => $service->saveDraft($user, 'home', SiteContentDefaults::home()),
                        'publish' => $service->publish($user, 'home'),
                        'restore' => $service->restoreRevision($user, 999),
                    };
                    $this->fail('Non-staff content modification was accepted.');
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }
        }
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_block_order_and_visibility_are_published_as_authored(): void
    {
        $draft = SiteContentDefaults::home();
        $draft['blocks'] = [$draft['blocks'][7], $draft['blocks'][0], $draft['blocks'][6]];
        $draft['blocks'][0]['data']['enabled'] = false;
        app(SiteContent::class)->publish($this->admin(), 'home', $draft);

        $published = app(SiteContent::class)->getPublished();
        $this->assertSame(['cta', 'hero', 'faq'], array_column($published['blocks'], 'type'));
        $this->assertFalse($published['blocks'][0]['data']['enabled']);
    }

    #[DataProvider('unsafeLinks')]
    public function test_unsafe_links_are_rejected_before_any_content_is_changed(string $url): void
    {
        $draft = SiteContentDefaults::home();
        $draft['blocks'][0]['data']['primary_url'] = $url;
        try {
            app(SiteContent::class)->saveDraft($this->admin(), 'home', $draft);
            $this->fail('Unsafe website link was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('blocks.0.data.primary_url', $exception->errors());
        }
        $this->assertDatabaseCount('site_pages', 0);
    }

    public static function unsafeLinks(): array
    {
        return [['javascript:alert(1)'], ['//evil.example/'], ['data:text/html,<script>alert(1)</script>'], ['https://admin:password@example.com/'], ["/\\evil.example"], ['/path%0d%0aLocation:evil'], ['http://insecure.example/']];
    }

    public function test_safe_local_and_external_link_variants_are_supported(): void
    {
        foreach (['/register?account_type=agency', '#how-it-works', 'https://example.com/partners', 'mailto:team@example.com', 'tel:+995555123456'] as $url) {
            $this->assertTrue(SiteLink::allowed($url), $url);
        }
    }

    public function test_media_paths_are_canonicalized_and_cannot_reference_private_documents(): void
    {
        $service = app(SiteContent::class);
        $settings = SiteContentDefaults::settings();
        $settings['logo_path'] = '/storage/site-content/brand-logo.png';
        $validated = $service->validate('settings', $settings);
        $this->assertSame('site-content/brand-logo.png', $validated['logo_path']);

        foreach (['campaign-documents/private.txt', '/storage/../../private/secrets.png', 'site-content/image.svg', 'https://example.com/image.png'] as $media) {
            $settings['logo_path'] = $media;
            try {
                $service->validate('settings', $settings);
                $this->fail('Unmanaged or executable media path was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('logo_path', $exception->errors());
            }
        }
    }

    public function test_unknown_blocks_duplicate_blocks_and_markup_are_rejected(): void
    {
        $service = app(SiteContent::class);
        $unknown = SiteContentDefaults::home();
        $unknown['blocks'][0]['type'] = 'raw_html';
        $duplicate = SiteContentDefaults::home();
        $duplicate['blocks'][1] = $duplicate['blocks'][0];
        $markup = SiteContentDefaults::home();
        $markup['blocks'][0]['data']['body'] = '<script>alert(1)</script>';
        $malformed = SiteContentDefaults::home();
        $malformed['blocks'] = 'not an array';
        foreach ([$unknown, $duplicate, $markup, $malformed] as $draft) {
            try {
                $service->saveDraft($this->admin(), 'home', $draft);
                $this->fail('Unsupported content tree was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_unknown_settings_fields_cannot_be_smuggled_into_the_content_tree(): void
    {
        $settings = SiteContentDefaults::settings();
        $settings['auth']['login']['executable_html'] = '<script>alert(1)</script>';
        $this->expectException(ValidationException::class);
        app(SiteContent::class)->validate('settings', $settings);
    }
}
