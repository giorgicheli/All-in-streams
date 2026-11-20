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
        $this->get('/')->assertOk()->assertSee('Meet your')->assertDontSee('A private draft headline');
        $this->actingAs($admin)->get('/admin/website-preview/home')->assertOk()
            ->assertSee('A private draft headline')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        app(SiteContent::class)->publish($admin, 'home');
        $this->get('/')->assertOk()->assertSee('A private draft headline')->assertDontSee('Meet your');
    }

    public function test_public_sections_follow_authored_order_and_visibility(): void
    {
        $content = SiteContentDefaults::home();
        $content['blocks'][2]['data']['enabled'] = false;
        [$content['blocks'][0], $content['blocks'][3]] = [$content['blocks'][3], $content['blocks'][0]];
        app(SiteContent::class)->publish(User::factory()->create(['role' => 'admin']), 'home', $content);
        $this->get('/')->assertOk()->assertSeeInOrder(['Native to your world.', 'Meet your'])
            ->assertDontSee('Your growth. Our game plan.');
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

    public function test_footer_has_legal_links_without_removed_slogans_or_replaced_asset_credits(): void
    {
        $this->get('/')->assertOk()->assertSee('Germany')->assertSee('Portugal')
            ->assertSee(route('privacy'))->assertSee(route('terms'))
            ->assertSee('Privacy policy')->assertSee('Terms &amp; conditions', false)
            ->assertDontSee('Local voices. Global ambition')->assertDontSee('Flags designed by')
            ->assertDontSee('Flaticon')->assertDontSee('Freepik');
    }

    public function test_white_preview_shares_only_published_content_and_preserves_the_main_theme(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $content = SiteContentDefaults::home();
        $content['blocks'][0]['data']['title_line_1'] = 'UNPUBLISHED_WHITE_PREVIEW_HEADLINE';
        app(SiteContent::class)->saveDraft($admin, 'home', $content);
        $pagesBefore = \App\Models\SitePage::all()->toArray();

        $this->get('/white')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('class="theme-white"', false)->assertSee('content="#f5f6fa"', false)
            ->assertSee('Meet your')->assertSee('site-footer')->assertSee('data-network-count', false)
            ->assertDontSee('UNPUBLISHED_WHITE_PREVIEW_HEADLINE')->assertDontSee('livewire.js')
            ->assertDontSee('network-stat-index')->assertDontSee('card-number')->assertDontSee('step-number');
        $this->get('/')->assertOk()->assertSee('class="theme-white"', false)
            ->assertSee('content="#f5f6fa"', false)->assertDontSee('UNPUBLISHED_WHITE_PREVIEW_HEADLINE');
        $this->assertSame($pagesBefore, \App\Models\SitePage::all()->toArray());
    }

    public function test_requested_faq_and_markets_render_in_both_homepage_versions(): void
    {
        $home = SiteContentDefaults::home();
        $faq = collect($home['blocks'])->firstWhere('type', 'faq')['data'];
        $countries = collect($home['blocks'])->firstWhere('type', 'geo_strip')['data']['countries'];
        $this->assertCount(15, $countries);
        foreach (['/', '/white'] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('Frequently Asked')->assertSee('Questions')
                ->assertSee('class="text-link faq-contact"', false)->assertSee('Talk to us');
            foreach ($faq['items'] as $item) $response->assertSee($item['question'])->assertSee($item['answer']);
            $html = $response->getContent();
            $strip = substr($html, strpos($html, 'data-geo-carousel'));
            $strip = substr($strip, 0, strpos($strip, 'id="geo-carousel-help"'));
            $lastPosition = -1;
            foreach ($countries as $country) {
                $position = strpos($strip, 'geo-country-name">'.$country['name']);
                $this->assertNotFalse($position);
                $this->assertGreaterThan($lastPosition, $position);
                $lastPosition = $position;
            }
        }
        $this->get($faq['url'])->assertOk()->assertSee('Brand')->assertSee('Agency')->assertSee('Creator');
    }

    public function test_public_pages_have_one_page_heading_and_one_site_header(): void
    {
        $routes = ['/', '/white', '/login', '/register', '/talk-to-us',
            '/talk-to-us?type=brand', '/talk-to-us?type=agency',
            '/talk-to-us?type=creator&creator_type=streamer',
            '/talk-to-us?type=creator&creator_type=influencer',
            '/talk-to-us?type=creator&creator_type=both',
            '/forgot-password', '/reset-password/'.str_repeat('a', 64).'?email=qa@example.test',
            '/privacy-policy', '/terms-and-conditions', '/contact'];

        foreach ($routes as $route) {
            $document = new \DOMDocument();
            $document->loadHTML($this->get($route)->assertOk()->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//h1')->length, $route);
            $this->assertSame(1, $xpath->query('//main//h1')->length, $route);
            $this->assertSame('h1', $xpath->query('//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]')->item(0)?->nodeName, $route);
            $this->assertSame(1, $xpath->query('//header')->length, $route);
            $this->assertSame(0, $xpath->query('//main//header')->length, $route);

            $previousLevel = 1;
            foreach ($xpath->query('//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $heading) {
                $level = (int) substr($heading->nodeName, 1);
                $this->assertLessThanOrEqual($previousLevel + 1, $level, $route.' skips a heading level: '.$heading->textContent);
                $previousLevel = $level;
            }
        }
    }

    public function test_hiding_the_hero_keeps_a_single_page_heading(): void
    {
        $home = SiteContentDefaults::home();
        $home['blocks'][0]['data']['enabled'] = false;
        app(SiteContent::class)->publish(User::factory()->create(['role' => 'admin']), 'home', $home);
        $document = new \DOMDocument();
        $document->loadHTML($this->get('/')->assertOk()->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertSame(1, (new \DOMXPath($document))->query('//main//h1')->length);
    }
}
