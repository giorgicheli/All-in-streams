<?php

namespace Tests\Unit;

use App\Support\SiteAppearance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SiteAppearanceTest extends TestCase
{
    public function test_missing_or_malformed_settings_have_safe_theme_and_color_defaults(): void
    {
        foreach ([[], ['appearance' => null], ['appearance' => 'invalid'], ['appearance' => ['default_theme' => 'system', 'light_accent' => 'red', 'dark_accent' => '#abc;display:none']]] as $settings) {
            $this->assertSame('dark', SiteAppearance::defaultTheme($settings));
            $this->assertSame([
                '--site-accent-light' => '#0a5cff',
                '--site-accent-dark' => '#c5f65c',
                '--site-accent-light-ink' => '#ffffff',
                '--site-accent-dark-ink' => '#000000',
            ], SiteAppearance::cssVariables($settings));
        }
    }

    #[DataProvider('accentColors')]
    public function test_authored_colors_receive_the_more_readable_black_or_white_ink(string $color, string $ink): void
    {
        $settings = ['appearance' => ['default_theme' => 'light', 'light_accent' => $color, 'dark_accent' => $color]];
        $this->assertSame('light', SiteAppearance::defaultTheme($settings));
        $this->assertSame([
            '--site-accent-light' => strtolower($color),
            '--site-accent-dark' => strtolower($color),
            '--site-accent-light-ink' => $ink,
            '--site-accent-dark-ink' => $ink,
        ], SiteAppearance::cssVariables($settings));
    }

    public static function accentColors(): array
    {
        return [
            'white' => ['#FFFFFF', '#000000'],
            'black' => ['#000000', '#ffffff'],
            'bright red' => ['#FF0000', '#000000'],
            'gray above contrast crossover' => ['#777777', '#000000'],
            'gray below contrast crossover' => ['#757575', '#ffffff'],
        ];
    }
}
