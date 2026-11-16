<?php

namespace App\Console\Commands;

use App\Models\CreatorShowcase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

class SeedCreatorShowcases extends Command
{
    protected $signature = 'showcase:creators {--force : Publish the requested demo catalogue on production}';
    protected $description = 'Add three explicitly labelled anonymous demo profiles for each of eight audience regions';

    public function handle(): int
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Production publication requires --force.');
            return self::FAILURE;
        }
        $admin = User::where('role', 'admin')->orderBy('is_demo')->first();
        if (!$admin) {
            $this->error('Create an administrator before publishing profiles.');
            return self::FAILURE;
        }
        $actor = Auth::user();
        Auth::setUser($admin);
        try {
            DB::transaction(function (): void {
                $regions = [
                    'Europe' => [['Germany', 'German'], ['Portugal', 'Portuguese'], ['Poland', 'Polish']],
                    'CIS' => [['Georgia', 'Georgian'], ['Kazakhstan', 'Russian'], ['Kyrgyzstan', 'Kyrgyz']],
                    'MENA' => [['United Arab Emirates', 'Arabic'], ['Saudi Arabia', 'Arabic'], ['Turkey', 'English']],
                    'Africa' => [['South Africa', 'English'], ['Nigeria', 'English'], ['Kenya', 'Swahili']],
                    'North America' => [['Canada', 'English'], ['United States', 'English'], ['Mexico', 'Spanish']],
                    'LATAM' => [['Brazil', 'Portuguese'], ['Argentina', 'Spanish'], ['Colombia', 'Spanish']],
                    'Asia' => [['India', 'Hindi'], ['Japan', 'Japanese'], ['South Korea', 'Korean']],
                    'Oceania' => [['Australia', 'English'], ['New Zealand', 'English'], ['Fiji', 'English']],
                ];
                $types = ['streamer', 'influencer', 'both'];
                $platforms = [['Kick', 'Twitch'], ['Instagram', 'TikTok'], ['Twitch', 'YouTube']];
                $images = ['gaming-studio', 'creator-microphone', 'streaming-desk'];
                $order = 0;
                foreach ($regions as $region => $markets) {
                    foreach ($markets as $index => [$country, $language]) {
                        $key = 'creator-catalogue-demo-v1-'.str($region)->slug().'-'.$index;
                        $existing = CreatorShowcase::where('demo_key', $key)->first();
                        if ($existing) {
                            if (!$existing->is_demo) throw new LogicException('An actual public profile uses a reserved demo key.');
                            $order++;
                            continue;
                        }
                        CreatorShowcase::create([
                            'demo_key' => $key, 'internal_title' => 'DEMO '.$region.' '.$country.' '.$types[$index],
                            'region' => $region, 'country' => $country, 'language' => $language,
                            'creator_type' => $types[$index], 'platforms' => $platforms[$index], 'image_theme' => $images[$index],
                            'followers' => [40200, 85000, 61800][$index] + $order * 240,
                            'average_views' => [1200, 24000, 1600][$index] + $order * 35,
                            'sort_order' => $order++, 'is_demo' => true, 'is_verified' => true, 'is_published' => true,
                        ]);
                    }
                }
            });
        } finally {
            if ($actor) Auth::setUser($actor); else Auth::forgetUser();
        }
        $this->info('The anonymous demo catalogue is ready. Existing edits and publication choices were preserved.');
        return self::SUCCESS;
    }
}