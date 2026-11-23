<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class CreatorPreviewClips
{
    public const MAX_CLIPS = 15;

    private const PRESETS = [
        'roulette-host' => 'Roulette host',
        'sugar-win' => 'Sugar win',
        'wheel-win' => 'Wheel win',
        'baccarat-host' => 'Baccarat host',
        'poker-host' => 'Poker host',
        'draw-poker-win' => 'Draw poker win',
        'keno-host' => 'Keno host',
    ];

    private const LEGACY_PRESETS = [
        'dragon-slots' => 'sugar-win', 'roulette' => 'roulette-host', 'blackjack' => 'poker-host',
        'baccarat' => 'baccarat-host', 'texas-holdem' => 'poker-host', 'craps' => 'wheel-win',
        'bingo' => 'keno-host', 'lottery-draw' => 'keno-host', 'crash-rocket' => 'sugar-win',
        'plinko' => 'wheel-win', 'mines' => 'draw-poker-win', 'arcade-fishing' => 'poker-host',
        'candy-slots' => 'sugar-win', 'prize-wheel' => 'wheel-win', 'scratch-card' => 'draw-poker-win',
        'studio' => 'sugar-win', 'gaming' => 'roulette-host', 'creator' => 'poker-host',
        'setup' => 'baccarat-host', 'live' => 'poker-host', 'community' => 'wheel-win',
        'camera' => 'keno-host', 'portrait' => 'keno-host', 'focus' => 'sugar-win',
    ];

    public static function options(?string $current = null): array
    {
        $options = self::PRESETS;
        if (isset(self::LEGACY_PRESETS[$current ?? ''])) {
            $options[$current] = self::PRESETS[self::LEGACY_PRESETS[$current]].' (previous preset)';
        }

        return $options;
    }

    public static function allowedKeys(): array
    {
        return [...array_keys(self::PRESETS), ...array_keys(self::LEGACY_PRESETS)];
    }

    public static function defaults(): array
    {
        $clips = [];
        foreach (self::PRESETS as $key => $title) {
            $clips[] = [
                'sample_clip' => $key, 'video_path' => null, 'poster_path' => null,
                'video_source_url' => '', 'link_url' => '', 'title' => $title, 'format' => 'Format preview',
            ];
        }

        return $clips;
    }

    public static function media(array $clip): array
    {
        $key = is_string($clip['sample_clip'] ?? null) ? $clip['sample_clip'] : '';
        $preset = self::LEGACY_PRESETS[$key] ?? (isset(self::PRESETS[$key]) ? $key : 'roulette-host');

        return [
            'src' => ! empty($clip['video_source_url']) ? $clip['video_source_url'] : (! empty($clip['video_path']) ? Storage::disk('public')->url($clip['video_path']) : '/videos/igaming-cards/'.$preset.'.mp4'),
            'poster' => ! empty($clip['poster_path']) ? Storage::disk('public')->url($clip['poster_path']) : '/images/igaming-cards/'.$preset.'.jpg',
        ];
    }
}
