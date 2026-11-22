<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class NetworkShowcaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('n', 32))]);
        $this->withoutVite();
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($document);
    }

    public function test_home_ends_the_hero_with_statistics_then_flags_without_duplicate_counts(): void
    {
        $html = $this->get('/')->assertOk()->assertSee('Our creator network')->getContent();
        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//*[@data-network-showcase]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="network"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="creator-content"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-network-showcase]//*[@data-network-count]')->length);
        $this->assertSame(4, $xpath->query('//*[@data-network-count]')->length);
        $this->assertSame(4, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " hero-statistics ")]//*[@data-network-count]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " hero-stage ")]/*[last()][contains(concat(" ", normalize-space(@class), " "), " hero-statistics ")]')->length);
        $this->assertSame(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " hero-stage ")]/following-sibling::*[1][@data-geo-carousel]')->length);
        $this->assertSame(9, $xpath->query('//*[@data-network-panel]')->length);
        $this->assertSame(3, $xpath->query('//*[@data-network-slide]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-creator-reels]')->length);
        $this->assertSame(0, $xpath->query('//video[@data-network-video and (@src or @autoplay)]')->length);
        $this->assertSame(18, $xpath->query('//video[@data-network-video and @preload="none" and @muted and @playsinline]')->length);
    }

    public function test_all_ordered_cms_clips_and_links_are_retained_including_the_last_slide(): void
    {
        $reels = SiteContentDefaults::settings()['creator_reels'];
        $reels['clips'] = array_merge($reels['clips'], array_slice($reels['clips'], 0, 3));
        foreach ($reels['clips'] as $index => &$clip) {
            $clip['title'] = 'Ordered preview '.($index + 1);
            $clip['link_url'] = '/talk-to-us?type=brand&clip='.($index + 1);
        }
        unset($clip);
        $html = Blade::render("@include('site.blocks.network-showcase', ['reels' => \$reels, 'stats' => null])", compact('reels'));
        $xpath = $this->xpath($html);
        $this->assertSame(4, $xpath->query('//*[@data-network-slide]')->length);
        $this->assertSame(12, $xpath->query('//*[@data-network-panel]')->length);
        $pool = json_decode($xpath->query('//script[@data-network-clips]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        foreach ($xpath->query('//*[@data-network-panel]') as $index => $panel) {
            $this->assertSame((string) ($index + 1), $panel->getAttribute('data-network-position'));
            $this->assertSame($reels['clips'][$index]['title'], $pool[$index]['title']);
            $this->assertSame($reels['clips'][$index]['link_url'], $xpath->query('.//a[@data-network-link]', $panel)->item(0)->getAttribute('href'));
        }
    }

    public function test_random_pane_weights_are_bounded_normalized_and_rotate_the_widest_position(): void
    {
        $defaults = SiteContentDefaults::settings()['creator_reels'];
        $pool = array_merge($defaults['clips'], array_slice($defaults['clips'], 0, 3));
        foreach ([2, 4, 5, 9, 10, 11, 12] as $clipCount) {
            $reels = array_replace($defaults, ['clips' => array_slice($pool, 0, $clipCount)]);
            $html = Blade::render("@include('site.blocks.network-showcase', ['reels' => \$reels, 'stats' => null])", compact('reels'));
            $xpath = $this->xpath($html);
            $slides = $xpath->query('//*[@data-network-slide]');
            $this->assertSame((int) ceil($clipCount / 3), $slides->length);
            $dominantPositions = [];
            foreach ($slides as $slideIndex => $slide) {
                $panels = $xpath->query('./*[@data-network-panel]', $slide);
                $this->assertSame(min(3, $clipCount - $slideIndex * 3), $panels->length);
                $weights = [];
                foreach ($panels as $panel) {
                    $this->assertSame(1, preg_match('/(?:^|;)--network-panel-weight:(\d+)(?:;|$)/', $panel->getAttribute('style'), $match));
                    $weights[] = (int) $match[1];
                    $this->assertSame(1, $xpath->query('.//*[@data-network-clip-path]', $panel)->length);
                }
                $this->assertSame(100, array_sum($weights));
                if (count($weights) === 3) {
                    $largest = max($weights);
                    $this->assertGreaterThanOrEqual(46, $largest);
                    $this->assertLessThanOrEqual(52, $largest);
                    $this->assertGreaterThanOrEqual(22, min($weights));
                    $this->assertLessThanOrEqual(25, min($weights));
                    $other = array_sum($weights) - $largest - min($weights);
                    $this->assertGreaterThanOrEqual(23, $other);
                    $this->assertLessThanOrEqual(32, $other);
                    $dominantPositions[] = array_search($largest, $weights, true);
                } elseif (count($weights) === 2) {
                    $this->assertGreaterThanOrEqual(56, max($weights));
                    $this->assertLessThanOrEqual(64, max($weights));
                    $this->assertGreaterThanOrEqual(36, min($weights));
                    $this->assertLessThanOrEqual(44, min($weights));
                } else {
                    $this->assertSame([100], $weights);
                }
            }
            if (count($dominantPositions) >= 3) {
                $firstThree = array_slice($dominantPositions, 0, 3);
                sort($firstThree);
                $this->assertSame([0, 1, 2], $firstThree);
            }
        }
    }

    public function test_published_title_and_figures_remain_editable_without_exposing_drafts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SiteContentDefaults::settings();
        $settings['creator_reels']['title'] = 'A private network heading';
        $settings['network_stats']['cards'][0]['count'] = 333;
        app(SiteContent::class)->saveDraft($admin, 'settings', $settings);
        $this->get('/')->assertDontSee('A private network heading')->assertDontSee('data-network-count="333"', false);
        app(SiteContent::class)->publish($admin, 'settings');
        $this->get('/')->assertOk()->assertSee('A private network heading')->assertSee('data-network-count="333"', false);
    }

    public function test_visibility_controls_keep_video_and_statistics_independent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SiteContentDefaults::settings();
        $settings['network_stats']['enabled'] = false;
        app(SiteContent::class)->publish($admin, 'settings', $settings);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, $this->xpath($html)->query('//*[@data-network-showcase]')->length);
        $this->assertSame(0, $this->xpath($html)->query('//*[@data-network-count]')->length);
        $settings['network_stats']['enabled'] = true;
        $settings['creator_reels']['enabled'] = false;
        app(SiteContent::class)->publish($admin, 'settings', $settings);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(0, $this->xpath($html)->query('//*[@data-network-showcase]')->length);
        $this->assertSame(4, $this->xpath($html)->query('//*[@data-network-count]')->length);
    }

    public function test_hero_fallback_and_reordered_blocks_do_not_duplicate_the_network(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SiteContentDefaults::settings();
        $settings['creator_orbit']['enabled'] = false;
        app(SiteContent::class)->publish($admin, 'settings', $settings);
        $home = SiteContentDefaults::home();
        [$home['blocks'][0], $home['blocks'][3]] = [$home['blocks'][3], $home['blocks'][0]];
        app(SiteContent::class)->publish($admin, 'home', $home);
        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//*[@data-network-showcase]')->length);
        $this->assertSame(4, $xpath->query('//*[@data-network-count]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-rope-statistics]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="network"]')->length);
        $settings['creator_reels']['enabled'] = false;
        app(SiteContent::class)->publish($admin, 'settings', $settings);
        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//*[@data-rope-statistics]')->length);
        $this->assertSame(4, $xpath->query('//*[@data-network-count]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="network"]')->length);
    }
}
