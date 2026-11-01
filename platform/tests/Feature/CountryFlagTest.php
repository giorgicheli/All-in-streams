<?php

namespace Tests\Feature;

use App\Support\Countries;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CountryFlagTest extends TestCase
{
    private string $originalPublicPath;
    private string $fixturePublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPublicPath = public_path();
        $this->fixturePublicPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ais-flags-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->fixturePublicPath.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'flags');
        $this->app->usePublicPath($this->fixturePublicPath);
    }

    protected function tearDown(): void
    {
        $this->app->usePublicPath($this->originalPublicPath);
        $parent = realpath(dirname($this->fixturePublicPath));
        if ($parent === realpath(sys_get_temp_dir()) && str_starts_with(basename($this->fixturePublicPath), 'ais-flags-')) {
            File::deleteDirectory($this->fixturePublicPath);
        }
        parent::tearDown();
    }

    public function test_codes_and_country_names_resolve_to_the_supported_local_asset_whitelist(): void
    {
        $this->assertSame('DE', Countries::code(' germany '));
        $this->assertSame('FR', Countries::code('fr'));
        $this->assertSame('Mexico', Countries::name('MX'));
        $this->assertCount(6, Countries::options());
        $this->assertNull(Countries::code('../../private'));
        $this->assertNull(Countries::flagUrl('https://untrusted.example/flag.png'));
    }

    public function test_missing_or_unknown_assets_render_a_safe_text_fallback(): void
    {
        $this->assertNull(Countries::flagUrl('DE'));
        $html = Blade::render('<x-country-flag code="DE"/>');
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('DE', $html);
        $unknown = Blade::render('<x-country-flag code="ZZ"/>');
        $this->assertStringContainsString('ZZ', $unknown);
        $this->assertStringNotContainsString('<img', $unknown);
    }

    public function test_local_flag_images_have_accessible_labels_and_constrained_dimensions(): void
    {
        File::put(public_path('images/flags/de.png'), 'isolated asset path fixture');
        $html = Blade::render('<x-country-flag code="DE" size="24"/>');

        $this->assertStringContainsString('/images/flags/de.png', $html);
        $this->assertStringContainsString('alt="Flag of Germany"', $html);
        $this->assertStringContainsString('width="24"', $html);
        $this->assertStringContainsString('height="16"', $html);
        $this->assertStringContainsString('object-fit:cover', $html);
    }

    public function test_decorative_flags_do_not_repeat_the_adjacent_country_name(): void
    {
        File::put(public_path('images/flags/ca.png'), 'isolated asset path fixture');
        $html = Blade::render('<x-country-flag code="CA" :decorative="true"/>');

        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }
}
