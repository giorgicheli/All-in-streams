<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class CreatorPreviewClips
{
    public const MAX_CLIPS = 15;

    private const PRESETS = [
        'dragon-slots' => 'Dragon slots',
        'roulette' => 'Roulette',
        'blackjack' => 'Blackjack',
        'baccarat' => 'Baccarat',
        'texas-holdem' => "Texas Hold'em",
        'craps' => 'Craps',
        'bingo' => 'Bingo',
        'lottery-draw' => 'Lottery draw',
        'crash-rocket' => 'Crash rocket',
        'plinko' => 'Plinko',
        'mines' => 'Mines',
        'arcade-fishing' => 'Arcade fishing',
        'candy-slots' => 'Candy slots',
        'prize-wheel' => 'Prize wheel',
        'scratch-card' => 'Scratch card',
    ];

    private const LEGACY_PRESETS = [
        'studio' => 'dragon-slots', 'gaming' => 'roulette', 'creator' => 'blackjack',
        'setup' => 'baccarat', 'live' => 'texas-holdem', 'community' => 'craps',
        'camera' => 'bingo', 'portrait' => 'lottery-draw', 'focus' => 'crash-rocket',
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
        $preset = self::LEGACY_PRESETS[$key] ?? (isset(self::PRESETS[$key]) ? $key : 'dragon-slots');

        return [
            'src' => ! empty($clip['video_source_url']) ? $clip['video_source_url'] : (! empty($clip['video_path']) ? Storage::disk('public')->url($clip['video_path']) : '/videos/igaming-loops/'.$preset.'.mp4'),
            'poster' => ! empty($clip['poster_path']) ? Storage::disk('public')->url($clip['poster_path']) : '/images/igaming-loops/'.$preset.'.jpg',
        ];
    }
}
