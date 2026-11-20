<?php

namespace Tests\Feature;

use App\Models\SitePage;
use App\Models\SitePageRevision;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class ContactCopyRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const FIELDS = ['eyebrow', 'title', 'description', 'name_label', 'email_field_label', 'message_label', 'submit_label'];

    public function test_dry_run_never_creates_settings_revisions_or_accounts(): void
    {
        $this->artisan('site:refresh-contact-copy')->expectsOutputToContain('Dry run only.')->assertSuccessful();
        $this->assertDatabaseCount('site_pages', 0);
        $this->assertDatabaseCount('site_page_revisions', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_refresh_is_scoped_audited_and_idempotent_while_preserving_a_distinct_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_demo' => false]);
        $service = app(SiteContent::class);
        $published = SiteContentDefaults::settings();
        $published['brand_name'] = 'Previously authored brand';
        $published['contact']['email'] = 'keep-published@example.test';
        $published['contact']['title'] = 'An old contact heading';
        $published['contact']['submit_label'] = 'Old submit';
        $service->publish($admin, 'settings', $published);
        $draft = $published;
        $draft['header']['cta_label'] = 'Keep pending button';
        $draft['contact']['email'] = 'keep-pending@example.test';
        $draft['contact']['privacy_note'] = 'Keep the unpublished privacy note.';
        $service->saveDraft($admin, 'settings', $draft);
        $service->publish($admin, 'home', SiteContentDefaults::home());
        $home = SitePage::where('slug', 'home')->firstOrFail()->toArray();
        $before = SitePage::all()->toArray();
        $beforeRevisions = SitePageRevision::all()->toArray();
        $this->artisan('site:refresh-contact-copy')->assertSuccessful();
        $this->assertSame($before, SitePage::all()->toArray());
        $this->assertSame($beforeRevisions, SitePageRevision::all()->toArray());

        $this->artisan('site:refresh-contact-copy', ['--publish' => true, '--actor' => $admin->id])->assertSuccessful();
        $copy = Arr::only(SiteContentDefaults::settings()['contact'], self::FIELDS);
        $published['contact'] = array_replace($published['contact'], $copy);
        $draft['contact'] = array_replace($draft['contact'], $copy);
        $this->assertSame($published, $service->getPublished('settings'));
        $this->assertSame($draft, $service->getDraft('settings'));
        $this->assertSame($home, SitePage::where('slug', 'home')->firstOrFail()->toArray());
        $page = SitePage::where('slug', 'settings')->firstOrFail();
        $this->assertSame($admin->id, $page->updated_by);
        $this->assertSame($published, $page->revisions()->latest('id')->firstOrFail()->content);
        $this->assertSame($admin->id, $page->revisions()->latest('id')->firstOrFail()->published_by);

        $pages = SitePage::all()->toArray();
        $revisions = SitePageRevision::all()->toArray();
        $this->artisan('site:refresh-contact-copy', ['--publish' => true])
            ->expectsOutputToContain('0 contact copy publication(s), 0 draft refresh(es).')->assertSuccessful();
        $this->assertSame($pages, SitePage::all()->toArray());
        $this->assertSame($revisions, SitePageRevision::all()->toArray());
    }

    public function test_only_outdated_draft_copy_changes_without_creating_a_publication_revision(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_demo' => false]);
        $service = app(SiteContent::class);
        $current = SiteContentDefaults::settings();
        $service->publish($admin, 'settings', $current);
        $draft = $current;
        $draft['contact']['description'] = 'An outdated description.';
        $draft['contact']['email'] = 'preserve-draft@example.test';
        $service->saveDraft($admin, 'settings', $draft);
        $revisions = SitePageRevision::all()->toArray();
        $this->artisan('site:refresh-contact-copy', ['--publish' => true])->assertSuccessful();
        $this->assertSame($current, $service->getPublished('settings'));
        $this->assertSame('preserve-draft@example.test', $service->getDraft('settings')['contact']['email']);
        $this->assertSame($current['contact']['description'], $service->getDraft('settings')['contact']['description']);
        $this->assertSame($revisions, SitePageRevision::all()->toArray());
    }

    public function test_publish_requires_a_real_administrator_and_rejects_invalid_actor_ids(): void
    {
        $business = User::factory()->create(['role' => 'business', 'is_demo' => false]);
        User::factory()->create(['role' => 'admin', 'is_demo' => true]);
        $this->artisan('site:refresh-contact-copy', ['--publish' => true])->assertFailed();
        $this->artisan('site:refresh-contact-copy', ['--publish' => true, '--actor' => $business->id])->assertFailed();
        $this->artisan('site:refresh-contact-copy', ['--publish' => true, '--actor' => 'invalid'])->assertFailed();
        $this->assertDatabaseCount('site_pages', 0);
        $this->assertDatabaseCount('site_page_revisions', 0);
    }
}
