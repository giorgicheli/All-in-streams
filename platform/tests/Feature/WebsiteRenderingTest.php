<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('w', 32))]);
        $this->withoutVite();
    }

    public function test_drafts_render_only_in_staff_preview_until_published(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $content = SiteContentDefaults::home();
        $content['blocks'][0]['data']['title_line_1'] = 'A private draft headline';
        app(SiteContent::class)->saveDraft($admin, 'home', $content);
        $this->get('/')->assertOk()->assertSee('Outplay your')->assertDontSee('A private draft headline');
        $this->actingAs($admin)->get('/admin/website-preview/home')->assertOk()
            ->assertSee('A private draft headline')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        app(SiteContent::class)->publish($admin, 'home');
        $this->get('/')->assertOk()->assertSee('A private draft headline')->assertDontSee('Outplay your');
    }

    public function test_public_sections_follow_authored_order_and_visibility(): void
    {
        $content = SiteContentDefaults::home();
        $content['blocks'][2]['data']['enabled'] = false;
        [$content['blocks'][0], $content['blocks'][3]] = [$content['blocks'][3], $content['blocks'][0]];
        app(SiteContent::class)->publish(User::factory()->create(['role' => 'admin']), 'home', $content);
        $this->get('/')->assertOk()->assertSeeInOrder(['Native to your world.', 'Outplay your'])
            ->assertDontSee('Your next move starts here.');
    }

    public function test_brand_and_auth_copy_are_published_together_but_previewed_privately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SiteContentDefaults::settings();
        $settings['brand_name'] = 'Custom Creator Network';
        $settings['auth']['register']['title'] = 'Make your next connection';
        $settings['auth']['labels']['agency'] = 'Your talent agency';
        app(SiteContent::class)->saveDraft($admin, 'settings', $settings);
        $this->get('/register')->assertOk()->assertDontSee('Make your next connection');
        $this->actingAs($admin)->get('/admin/website-preview/settings?view=register')->assertOk()
            ->assertSee('Make your next connection')->assertSee('Custom Creator Network');
        app(SiteContent::class)->publish($admin, 'settings');
        auth()->logout();
        $this->get('/register')->assertOk()->assertSee('Make your next connection')->assertSee('Your talent agency');
        $this->get('/')->assertOk()->assertSee('Custom Creator Network');
    }

    public function test_non_staff_cannot_open_private_draft_previews(): void
    {
        $this->get('/admin/website-preview/home')->assertRedirect('/login');
        foreach (['business', 'creator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/admin/website-preview/home')->assertForbidden();
        }
    }

    public function test_flags_have_local_image_sources_and_visible_attribution(): void
    {
        $this->get('/')->assertOk()->assertSee('Germany')->assertSee('France')->assertSee('Flags designed by')
            ->assertSee('Flaticon')->assertSee('Freepik');
    }
}
