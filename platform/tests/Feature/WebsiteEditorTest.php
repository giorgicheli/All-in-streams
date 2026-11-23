<?php

namespace Tests\Feature;

use App\Filament\Pages\WebsiteEditor;
use App\Models\SitePage;
use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebsiteEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('e', 32))]);
        $this->withoutVite();
    }

    private function staff(): User
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        return $staff;
    }

    public function test_editor_is_available_only_to_administrators(): void
    {
        foreach (['business', 'creator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(WebsiteEditor::getUrl())->assertForbidden();
        }
        $this->staff();
        $this->get(WebsiteEditor::getUrl())->assertOk()->assertSee('Draft preview')->assertSee('Publish changes');
    }

    public function test_saving_draft_updates_preview_without_changing_published_content(): void
    {
        $this->staff();
        Livewire::test(WebsiteEditor::class)->set('data.seo.title', 'Private preview title')
            ->call('saveDraft')->assertHasNoErrors()->assertSet('previewVersion', 1);

        $service = app(SiteContent::class);
        $this->assertSame('Private preview title', $service->getDraft('home')['seo']['title']);
        $this->assertSame(SiteContentDefaults::home()['seo']['title'], $service->getPublished('home')['seo']['title']);
    }

    public function test_publish_and_restore_keep_a_previous_version_as_a_draft(): void
    {
        $this->staff();
        $editor = Livewire::test(WebsiteEditor::class)->set('data.seo.title', 'Published website title')
            ->call('publish')->assertHasNoErrors();
        $page = SitePage::where('slug', 'home')->firstOrFail();
        $baseline = $page->revisions()->oldest('id')->firstOrFail();
        $this->assertSame('Published website title', app(SiteContent::class)->getPublished('home')['seo']['title']);

        $editor->set('revisionId', (string) $baseline->id)->call('restoreRevision')->assertHasNoErrors();

        $this->assertSame(SiteContentDefaults::home()['seo']['title'], app(SiteContent::class)->getDraft('home')['seo']['title']);
        $this->assertSame('Published website title', app(SiteContent::class)->getPublished('home')['seo']['title']);
    }

    public function test_site_settings_can_be_edited_and_published_independently(): void
    {
        $this->staff();
        Livewire::test(WebsiteEditor::class)->call('selectPage', 'settings')
            ->set('data.brand_name', 'Partner Studio')->call('publish')->assertHasNoErrors()
            ->assertSet('editingPage', 'settings');

        $this->assertSame('Partner Studio', app(SiteContent::class)->getPublished('settings')['brand_name']);
        $this->assertSame(SiteContentDefaults::home(), app(SiteContent::class)->getPublished('home'));
    }

    public function test_main_colors_can_be_previewed_then_published_by_an_administrator(): void
    {
        $this->staff();
        $appearance = ['default_theme' => 'light', 'light_accent' => '#d93025', 'dark_accent' => '#ffffff'];
        $editor = Livewire::test(WebsiteEditor::class)->call('selectPage', 'settings')
            ->assertSee('Main colors')->assertSee('Light background accent')->assertSee('Dark background accent')
            ->set('data.appearance.default_theme', $appearance['default_theme'])
            ->set('data.appearance.light_accent', $appearance['light_accent'])
            ->set('data.appearance.dark_accent', $appearance['dark_accent'])
            ->call('saveDraft')->assertHasNoErrors();

        $service = app(SiteContent::class);
        $this->assertSame($appearance, $service->getDraft('settings')['appearance']);
        $this->assertSame(SiteContentDefaults::settings()['appearance'], $service->getPublished('settings')['appearance']);
        $editor->call('publish')->assertHasNoErrors();
        $this->assertSame($appearance, $service->getPublished('settings')['appearance']);
        $this->assertSame(SiteContentDefaults::home(), $service->getPublished('home'));
    }

    public function test_editor_rejects_invalid_color_picker_input_before_publication(): void
    {
        $this->staff();
        Livewire::test(WebsiteEditor::class)->call('selectPage', 'settings')
            ->set('data.appearance.light_accent', '#abc')->call('publish')
            ->assertHasErrors(['data.appearance.light_accent']);
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_color_publishing_rechecks_the_administrator_role(): void
    {
        $staff = $this->staff();
        $editor = Livewire::test(WebsiteEditor::class)->call('selectPage', 'settings')
            ->set('data.appearance.dark_accent', '#ffffff');
        $staff->update(['role' => 'business']);
        $editor->call('publish')->assertForbidden();
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_editor_rejects_executable_links_before_saving(): void
    {
        $this->staff();
        $editor = Livewire::test(WebsiteEditor::class);
        $first = array_key_first($editor->get('data')['blocks']);
        $editor->set('data.blocks.'.$first.'.data.primary_url', 'javascript:alert(1)')->call('saveDraft')->assertHasErrors();
        $this->assertDatabaseCount('site_pages', 0);
    }

    public function test_editor_actions_recheck_role_after_the_initial_page_load(): void
    {
        $staff = $this->staff();
        $editor = Livewire::test(WebsiteEditor::class);
        $staff->update(['role' => 'business']);
        $editor->call('saveDraft')->assertForbidden();
        $this->assertDatabaseCount('site_pages', 0);
    }
}
