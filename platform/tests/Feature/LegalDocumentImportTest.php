<?php

namespace Tests\Feature;

use App\Models\SitePage;
use App\Models\SitePageRevision;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('l', 32))]);
        $this->withoutVite();
    }

    private function source(string $slug): array
    {
        return json_decode(file_get_contents(resource_path('legal/'.$slug.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function text(array $node): string
    {
        if ($node['type'] === 'text') return $node['text'];
        if ($node['type'] === 'hardBreak') return "\n";
        return implode('', array_map(fn (array $child): string => $this->text($child), $node['content'] ?? []));
    }

    public function test_supplied_documents_retain_every_paragraph_line_break_and_safe_link(): void
    {
        // Fingerprints were calculated from all non-empty OOXML paragraphs,
        // including each source title and address line breaks, in source order.
        $expected = [
            'privacy' => [27, 8, '9acfc1f62ea59e853d0936b1b5fface306082beddee48fca339b57d30df37ff1'],
            'terms' => [21, 9, 'b6cbb2f9ad649f5f27213cb8c4dc59d6de26a931c9416aeaeb41f25459c99760'],
        ];
        foreach ($expected as $slug => [$paragraphs, $headings, $fingerprint]) {
            $source = $this->source($slug);
            $blocks = $source['body']['content'];
            $text = implode("\n\n", [$source['title'], ...array_map(fn (array $node): string => $this->text($node), $blocks)]);
            $this->assertSame($fingerprint, hash('sha256', $text), $slug.' differs from the supplied DOCX text');
            $this->assertCount($paragraphs - 1, $blocks);
            $headingNodes = array_filter($blocks, fn (array $node): bool => $node['type'] === 'heading');
            $this->assertCount($headings, $headingNodes);
            foreach ($headingNodes as $node) $this->assertSame(2, $node['attrs']['level']);
            $page = SiteContentDefaults::for($slug);
            $page['title'] = $source['title'];
            $page['body'] = $source['body'];
            $this->assertSame($source['body'], app(SiteContent::class)->validate($slug, $page)['body']);
            $this->assertStringContainsString('mailto:info@streams.com', json_encode($source));
        }
        $this->assertStringContainsString('https://cpdp.bg/', json_encode($this->source('privacy'), JSON_UNESCAPED_SLASHES));
    }

    public function test_default_import_is_read_only_even_without_an_administrator(): void
    {
        $this->artisan('website:import-legal-documents')->expectsOutputToContain('Dry run only.')->assertSuccessful();
        $this->assertDatabaseCount('site_pages', 0);
        $this->assertDatabaseCount('site_page_revisions', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_publication_is_scoped_audited_repeatable_and_preserves_a_separate_legal_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_demo' => false]);
        $service = app(SiteContent::class);
        $service->publish($admin, 'home', SiteContentDefaults::home());
        $settings = SiteContentDefaults::settings();
        $settings['brand_name'] = 'Keep this authored site name';
        $service->saveDraft($admin, 'settings', $settings);
        $unrelated = SitePage::whereIn('slug', ['home', 'settings'])->get()->toArray();
        $draft = SiteContentDefaults::privacy();
        $draft['title'] = 'Separate unpublished policy work';
        $service->saveDraft($admin, 'privacy', $draft);

        $this->artisan('website:import-legal-documents', ['--publish' => true, '--admin' => $admin->id])->assertSuccessful();
        $this->assertSame($unrelated, SitePage::whereIn('slug', ['home', 'settings'])->get()->toArray());
        $this->assertSame($draft, $service->getDraft('privacy'));
        foreach (['privacy' => '/privacy-policy', 'terms' => '/terms-and-conditions'] as $slug => $url) {
            $source = $this->source($slug);
            $published = $service->getPublished($slug);
            $this->assertSame($source['title'], $published['title']);
            $this->assertSame($source['body'], $published['body']);
            $page = SitePage::where('slug', $slug)->firstOrFail();
            $this->assertSame($admin->id, $page->updated_by);
            $this->assertTrue($page->revisions()->where('published_by', $admin->id)->exists());
            $response = $this->get($url)->assertOk()->assertSee($source['title'])->assertSee('207862525');
            $document = new \DOMDocument();
            $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame(0, $xpath->query('//article//h1')->length);
            $this->assertSame(1, $xpath->query('//article//a[@href="mailto:info@streams.com"]')->length);
        }
        $this->get('/privacy-policy')->assertSee('https://cpdp.bg/', false);
        $pages = SitePage::all()->toArray();
        $revisions = SitePageRevision::all()->toArray();
        $this->artisan('website:import-legal-documents', ['--publish' => true])->expectsOutputToContain('0 legal page(s) published.')->assertSuccessful();
        $this->assertSame($pages, SitePage::all()->toArray());
        $this->assertSame($revisions, SitePageRevision::all()->toArray());
    }

    public function test_existing_published_content_needs_explicit_replacement_before_any_page_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(SiteContent::class);
        $existing = SiteContentDefaults::terms();
        $existing['body'] = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Existing authored terms']]]]];
        $service->publish($admin, 'terms', $existing);
        $pages = SitePage::all()->toArray();
        $revisions = SitePageRevision::all()->toArray();
        $this->artisan('website:import-legal-documents', ['--publish' => true])->expectsOutputToContain('Use --replace')->assertFailed();
        $this->assertSame($pages, SitePage::all()->toArray());
        $this->assertSame($revisions, SitePageRevision::all()->toArray());
        $this->artisan('website:import-legal-documents', ['--publish' => true, '--replace' => true])->assertSuccessful();
        $this->assertSame($this->source('terms')['body'], $service->getPublished('terms')['body']);
    }

    public function test_import_cannot_record_a_non_administrator_as_publisher(): void
    {
        $business = User::factory()->create(['role' => 'business']);
        $this->artisan('website:import-legal-documents', ['--publish' => true, '--admin' => $business->id])->assertFailed();
        $this->assertDatabaseCount('site_pages', 0);
    }
}
