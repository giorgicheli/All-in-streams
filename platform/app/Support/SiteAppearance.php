<?php

namespace App\Support;

class SiteAppearance
{
    public const THEMES = ['light', 'dark'];

    public const HEX_PATTERN = '/\A#[0-9a-fA-F]{6}\z/';

    public const DEFAULTS = [
        'default_theme' => 'dark',
        'light_accent' => '#0a5cff',
        'dark_accent' => '#c5f65c',
    ];

    /** Resolve saved settings without trusting them as CSS or HTML. */
    public static function defaultTheme(array $settings): string
    {
        $appearance = is_array($settings['appearance'] ?? null) ? $settings['appearance'] : [];
        $theme = $appearance['default_theme'] ?? null;

        return in_array($theme, self::THEMES, true) ? $theme : self::DEFAULTS['default_theme'];
    }

    /** Only validated, opaque hex colors reach the public layout. */
    public static function cssVariables(array $settings): array
    {
        $appearance = is_array($settings['appearance'] ?? null) ? $settings['appearance'] : [];
        $light = self::safeColor($appearance['light_accent'] ?? null, self::DEFAULTS['light_accent']);
        $dark = self::safeColor($appearance['dark_accent'] ?? null, self::DEFAULTS['dark_accent']);

        return [
            '--site-accent-light' => $light,
            '--site-accent-dark' => $dark,
            '--site-accent-light-ink' => self::contrastingInk($light),
            '--site-accent-dark-ink' => self::contrastingInk($dark),
        ];
    }

    private static function safeColor(mixed $color, string $fallback): string
    {
        return is_string($color) && preg_match(self::HEX_PATTERN, $color) === 1 ? strtolower($color) : $fallback;
    }

    /** Choose the greater WCAG contrast ratio against black or white. */
    private static function contrastingInk(string $color): string
    {
        $linear = static function (int $offset) use ($color): float {
            $channel = hexdec(substr($color, $offset, 2)) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $linear(1) + 0.7152 * $linear(3) + 0.0722 * $linear(5);
        $blackContrast = ($luminance + 0.05) / 0.05;
        $whiteContrast = 1.05 / ($luminance + 0.05);

        return $blackContrast >= $whiteContrast ? '#000000' : '#ffffff';
    }
}
