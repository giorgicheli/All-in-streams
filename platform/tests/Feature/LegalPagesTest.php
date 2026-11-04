<?php

namespace Tests\Feature;

use App\Filament\Pages\WebsiteEditor;
use App\Models\SitePage;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }

    private function staff(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        return $user;
    }

    private function document(string $text): array
    {
        $linkedText = ['type' => 'text', 'text' => 'Read more', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/privacy', 'target' => '_blank']]]];
        $listItem = ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$linkedText]]]];
        return ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Contact information']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text, 'marks' => [['type' => 'bold']]]]],
            ['type' => 'bulletList', 'content' => [$listItem]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1, 'type' => null], 'content' => [$listItem]],
        ]];
    }

    public function test_policy_pages_start_with_blank_bodies_and_public_reads_do_not_create_records(): void
    {
        foreach (['privacy' => '/privacy-policy', 'terms' => '/terms-and-conditions'] as $slug => $url) {
            $defaults = SiteContentDefaults::for($slug);
            $this->assertSame(['type' => 'doc', 'content' => []], $defaults['body']);
            $this->get($url)->assertOk()->assertSee($defaults['title'])->assertDontSee('Content coming soon')->assertDontSee('Information you provide');
        }
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_visual_editor_supports_private_draft_preview_publish_and_revision_restore_for_each_policy(): void
    {
        $this->staff();
        foreach (['privacy' => '/privacy-policy', 'terms' => '/terms-and-conditions'] as $slug => $url) {
            $text = 'Private policy draft '.$slug;
            $editor = Livewire::test(WebsiteEditor::class)->call('selectPage', $slug)
                ->assertSchemaComponentExists('body')->set('data.body', $this->document($text))
                ->call('saveDraft')->assertHasNoErrors();
            $this->get($url)->assertOk()->assertDontSee($text);
            $this->get('/admin/website-preview/'.$slug)->assertOk()->assertSee($text)
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $editor->call('publish')->assertHasNoErrors();
            $this->get($url)->assertOk()->assertSee($text)->assertSee('<strong>', false)->assertSee('<ul>', false)->assertSee('https://example.com/privacy');
            $page = SitePage::where('slug', $slug)->sole();
            $baseline = $page->revisions()->oldest('id')->firstOrFail();
            $editor->set('revisionId', (string)$baseline->id)->call('restoreRevision')->assertHasNoErrors();
            $this->assertSame([], app(SiteContent::class)->getDraft($slug)['body']['content']);
            $this->get($url)->assertOk()->assertSee($text);
            $editor->call('publish')->assertHasNoErrors();
            $this->get($url)->assertOk()->assertDontSee($text);
        }
    }

    public function test_fresh_blank_editor_can_save_and_publish_each_empty_page(): void
    {
        $this->staff();
        foreach (['privacy', 'terms'] as $slug) {
            Livewire::test(WebsiteEditor::class)->call('selectPage', $slug)
                ->call('saveDraft')->assertHasNoErrors()->call('publish')->assertHasNoErrors();
            $this->assertSame([], app(SiteContent::class)->getPublished($slug)['body']['content']);
        }
    }

    public function test_switching_pages_on_one_editor_preserves_independent_content_and_remounts_the_editor(): void
    {
        $this->staff();
        $privacy = $this->document('Privacy-only content');
        $editor = Livewire::test(WebsiteEditor::class)->call('selectPage', 'privacy')
            ->set('data.body', $privacy)->call('saveDraft')->assertHasNoErrors()
            ->assertSee('website-content-editor-privacy', false);
        $editor->call('selectPage', 'terms')->assertSet('data.title', 'Terms & conditions')
            ->assertSet('data.body.content', [])->assertSee('website-content-editor-terms', false)
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertSame([], app(SiteContent::class)->getDraft('terms')['body']['content']);
        $editor->call('selectPage', 'privacy')->assertSet('data.title', 'Privacy policy')
            ->assertSet('data.body', $privacy)->assertSee('website-content-editor-privacy', false);
        $this->assertSame($privacy, app(SiteContent::class)->getDraft('privacy')['body']);
    }

    public function test_other_roles_cannot_preview_or_publish_policy_content(): void
    {
        foreach (['business', 'creator', 'agency'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get('/admin/website-preview/privacy')->assertForbidden();
            try { app(SiteContent::class)->publish($actor, 'terms', SiteContentDefaults::terms()); $this->fail('Non-admin published policy content.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        }
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_literal_markup_in_editor_text_is_rendered_as_text_and_cannot_create_an_executable_element(): void
    {
        $page = SiteContentDefaults::privacy();
        $page['body'] = $this->document('Literal <img src=x onerror="evilPolicyFunction()"> example');
        app(SiteContent::class)->publish($this->staff(), 'privacy', $page);
        $this->get('/privacy-policy')->assertOk()->assertSee('&lt;img src', false)->assertDontSee('<img src=x', false);
    }

    public function test_rich_content_rejects_executable_links_private_images_and_html_nodes(): void
    {
        $staff = $this->staff();
        $base = SiteContentDefaults::privacy();
        $badNodes = [
            ['type' => 'image', 'attrs' => ['id' => 'campaign-documents/private.pdf']],
            ['type' => 'renderedCustomBlock', 'html' => '<script>alert(1)</script>'],
            ['type' => 'paragraph', 'attrs' => ['style' => 'position:fixed'], 'content' => []],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'unsafe', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
        ];
        foreach ($badNodes as $node) {
            $draft = $base;
            $draft['body']['content'] = [$node];
            try { app(SiteContent::class)->saveDraft($staff, 'privacy', $draft); $this->fail('Unsafe rich content was accepted.'); }
            catch (ValidationException $exception) { $this->assertArrayHasKey('body', $exception->errors()); }
        }
        $this->assertSame($base, app(SiteContent::class)->getDraft('privacy'));
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_legacy_footer_settings_receive_new_labels_without_overwriting_authored_lists_or_writing_records(): void
    {
        $old = SiteContentDefaults::settings();
        unset($old['footer']['privacy_label'], $old['footer']['terms_label']);
        $old['footer']['statement'] = 'Local voices. Global ambition.';
        $old['footer']['tagline'] = 'Authored footer tagline';
        $old['navigation'] = [['label' => 'Custom', 'url' => '#platform']];
        SitePage::create(['slug' => 'settings', 'title' => 'Settings', 'draft_content' => $old, 'published_content' => $old]);
        $service = app(SiteContent::class);
        foreach ([$service->getPublished('settings'), $service->getDraft('settings'), $service->validate('settings', $old)] as $settings) {
            $this->assertArrayNotHasKey('statement', $settings['footer']);
            $this->assertSame('Privacy policy', $settings['footer']['privacy_label']);
            $this->assertSame('Terms & conditions', $settings['footer']['terms_label']);
            $this->assertSame('Authored footer tagline', $settings['footer']['tagline']);
            $this->assertSame($old['navigation'], $settings['navigation']);
        }
        $this->assertSame($old, SitePage::where('slug', 'settings')->sole()->published_content);
    }
}
