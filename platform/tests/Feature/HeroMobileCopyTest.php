<?php

namespace Tests\Feature;

use App\Support\SiteContentDefaults;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class HeroMobileCopyTest extends TestCase
{
    public function test_shared_hero_copy_is_authored_once_escaped_and_stable_with_or_without_video_artwork(): void
    {
        $base = SiteContentDefaults::home()['blocks'][0]['data'];
        $copies = [
            $base['body'],
            'შენს ბრენდს სტრიმერებსა და ინფლუენსერებთან ვაკავშირებთ. კამპანიას ჩვენი გუნდი მართავს.',
            "შენი\u{00A0}ბრენდი, შენი აუდიტორია. Create together — worldwide 🌍.",
            'An authored <strong>description</strong> & “creator community” must remain plain text.',
        ];

        foreach ([true, false] as $orbitEnabled) {
            $site = SiteContentDefaults::settings();
            $site['creator_orbit']['enabled'] = $orbitEnabled;
            foreach ($copies as $copy) {
                $data = array_replace($base, [
                    'title_line_1' => 'Meet <your>', 'title_line_2' => 'next & creator.',
                    'body' => $copy, 'mobile_body' => 'A separate legacy mobile description must stay hidden.',
                    'headlines' => [['line_1' => 'A rotating legacy headline', 'line_2' => 'must stay hidden.']],
                ]);
                $html = Blade::render("@include('site.blocks.hero', ['data' => \$data, 'site' => \$site, 'suppressHeroNetwork' => true])", compact('data', 'site'));
                $document = new \DOMDocument();
                $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($document);
                $heroCopy = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " hero-copy ")]')->item(0);
                $paragraphs = $xpath->query('./p', $heroCopy);

                $this->assertSame(1, $paragraphs->length);
                $this->assertSame($copy, $paragraphs->item(0)->textContent);
                $hasEmphasis = str_contains($copy, $base['body_emphasis']);
                $this->assertSame($hasEmphasis ? 1 : 0, $xpath->query('./*', $paragraphs->item(0))->length, 'Only the configured phrase may receive formatting; authored markup must be escaped.');
                if ($hasEmphasis) {
                    $strong = $xpath->query('./strong', $paragraphs->item(0));
                    $this->assertSame(1, $strong->length);
                    $this->assertSame($base['body_emphasis'], $strong->item(0)->textContent);
                }
                $this->assertSame(0, $xpath->query('//h1')->length);
                $this->assertSame(1, $xpath->query('//h2[contains(concat(" ", normalize-space(@class), " "), " hero-headline ")]')->length);
                $headlineLines = $xpath->query('//h2/span');
                $this->assertSame(2, $headlineLines->length);
                $this->assertSame($data['title_line_1'], $headlineLines->item(0)->textContent);
                $this->assertSame($data['title_line_2'], $headlineLines->item(1)->textContent);
                $this->assertStringNotContainsString('A separate legacy mobile description', $html);
                $this->assertStringNotContainsString('A rotating legacy headline', $html);
                $this->assertSame(0, $xpath->query('.//ul', $heroCopy)->length, 'The simplified hero omits the extra checklist.');
            }
        }
    }
}
