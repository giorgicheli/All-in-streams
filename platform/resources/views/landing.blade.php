@php
    $visibleBlocks = array_values(array_filter($home['blocks'], fn (array $block) => $block['data']['enabled'] ?? true));
    $hasHeroHeading = count(array_filter($visibleBlocks, fn (array $block) => $block['type'] === 'hero')) > 0;
    $hasNetworkShowcase = ($site['creator_reels']['enabled'] ?? false) && count($site['creator_reels']['clips'] ?? []) >= 2;
    $hasHeroNetwork = ($site['network_stats']['enabled'] ?? false)
        && ! ($site['creator_orbit']['enabled'] ?? false)
        && ! $hasNetworkShowcase
        && $hasHeroHeading;
    $hasHeroStage = ($visibleBlocks[0]['type'] ?? null) === 'hero'
        && ($visibleBlocks[1]['type'] ?? null) === 'geo_strip';
    $hasHeroStatistics = $hasHeroStage && ($site['network_stats']['enabled'] ?? false)
        && count($site['network_stats']['cards'] ?? []) > 0;
    $creatorReelAnchor = array_search('geo_strip', array_column($visibleBlocks, 'type'), true);
    $creatorReelAnchor = $creatorReelAnchor === false ? 0 : $creatorReelAnchor;
@endphp
<x-layout :title="$home['seo']['title']" :description="$home['seo']['description']" :light-preview="$lightPreview ?? false">
    @include('site.navigation')
    <main class="home-redesign">
        @unless($hasHeroHeading)<h1 class="sr-only">{{ $home['seo']['title'] }}</h1>@endunless
        @if($hasHeroStage)
            <div class="hero-stage">
                @include('site.blocks.hero', ['data' => $visibleBlocks[0]['data'], 'suppressHeroNetwork' => $hasNetworkShowcase || $hasHeroStatistics])
                @if($hasHeroStatistics)
                    @include('site.blocks.hero-statistics', ['data' => $site['network_stats']])
                @endif
            </div>
            @include('site.blocks.geo_strip', ['data' => $visibleBlocks[1]['data']])
            @if($hasNetworkShowcase)
                @include('site.blocks.network-showcase', ['reels' => $site['creator_reels'], 'stats' => null])
            @elseif(($site['network_stats']['enabled'] ?? false) && ! $hasHeroNetwork && ! $hasHeroStatistics)
                @include('site.blocks.network-stats', ['data' => $site['network_stats'], 'compact' => true, 'afterHero' => true])
            @endif
        @endif
        @foreach($visibleBlocks as $block)
            @continue($hasHeroStage && $loop->index < 2)
            @include('site.blocks.'.$block['type'], ['data' => $block['data'], 'suppressHeroNetwork' => $hasNetworkShowcase])
            @if(! $hasHeroStage && $loop->index === $creatorReelAnchor && $hasNetworkShowcase)
                @include('site.blocks.network-showcase', ['reels' => $site['creator_reels'], 'stats' => ($site['network_stats']['enabled'] ?? false) ? $site['network_stats'] : null])
            @endif
            @if(! $hasNetworkShowcase && $block['type'] === 'geo_strip' && ($site['network_stats']['enabled'] ?? false) && ! $hasHeroNetwork)
                @include('site.blocks.network-stats', ['data' => $site['network_stats']])
            @endif
            @if($block['type'] === 'audiences' && ($site['creator_network']['enabled'] ?? false))
                @include('site.blocks.creator-network', ['data' => $site['creator_network']])
            @endif
        @endforeach
    </main>
    @include('site.footer')
</x-layout>
