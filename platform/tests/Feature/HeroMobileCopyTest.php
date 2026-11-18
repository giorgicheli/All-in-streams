<?php

namespace Tests\Feature;

use App\Support\SiteContentDefaults;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class HeroMobileCopyTest extends TestCase
{
    private function normalized(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    public function test_mobile_tail_preserves_every_authored_word_and_splits_without_changing_desktop_copy(): void
    {
        $base = SiteContentDefaults::home()['blocks'][0]['data'];
        $copies = [
            $base['mobile_body'],
            'ჩვენ ვაკავშირებთ ბრენდებს შემქმნელებთან. კამპანიის მართვას, სრულ მხარდაჭერასა და ანგარიშგებას ჩვენი გუნდი უზრუნველყოფს.',
            'Find experienced creators for your next campaign with introductions delivery and reporting handled by our team.',
            'Create together.',
            'Hello, this is our next campaign.',
            "შენი\u{00A0}ბრენდი, შენი აუდიტორია. ერთად ვქმნით კამპანიებს, ვმართავთ პროცესს და გიზიარებთ შედეგებს.",
            'Our network works globally. “We handle introductions, delivery and reporting for your team.”',
            'Together.',
        ];
        foreach ([true, false] as $orbitEnabled) {
            $site = SiteContentDefaults::settings();
            $site['creator_orbit']['enabled'] = $orbitEnabled;
            foreach ($copies as $copy) {
                $data = array_replace($base, ['body' => 'Desktop copy remains exactly as authored.', 'mobile_body' => $copy]);
                $html = Blade::render("@include('site.blocks.hero', ['data' => \$data, 'site' => \$site, 'suppressHeroNetwork' => true])", compact('data', 'site'));
                $document = new \DOMDocument();
                $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($document);
                $mobile = $xpath->query('//p[@class="hero-mobile-description"]');
                $this->assertSame(1, $mobile->length);
                $this->assertSame($this->normalized($copy), $this->normalized($mobile->item(0)->textContent));
                $this->assertSame($data['body'], $xpath->query('//p[@class="hero-desktop-description"]')->item(0)->textContent);
                $tail = $xpath->query('.//span[@class="hero-mobile-tail"]', $mobile->item(0));
                $this->assertSame(1, $tail->length);
                $lines = $xpath->query('./span[@class="hero-mobile-tail-line"]', $tail->item(0));
                $words = preg_split('/\s+/u', $copy, -1, PREG_SPLIT_NO_EMPTY);
                $this->assertSame(count($words) >= 2 ? 2 : 1, $lines->length);
                foreach ($lines as $line) $this->assertNotSame('', trim($line->textContent));
                if (count($words) >= 6) {
                    $tailWords = preg_split('/\s+/u', trim($tail->item(0)->textContent), -1, PREG_SPLIT_NO_EMPTY);
                    $this->assertGreaterThanOrEqual(6, count($tailWords));
                    $this->assertLessThanOrEqual(12, count($tailWords));
                    foreach ($lines as $line) {
                        $this->assertGreaterThanOrEqual(2, count(preg_split('/\s+/u', $line->textContent, -1, PREG_SPLIT_NO_EMPTY)));
                    }
                }
                if ($copy === $base['mobile_body']) {
                    $this->assertSame('We handle introductions,', $lines->item(0)->textContent);
                    $this->assertSame('campaign delivery and reporting.', $lines->item(1)->textContent);
                }
            }
        }
    }
}
