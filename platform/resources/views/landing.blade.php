@php
    $visibleBlocks = array_values(array_filter($home['blocks'], fn (array $block) => $block['data']['enabled'] ?? true));
    $hasHeroStage = ($visibleBlocks[0]['type'] ?? null) === 'hero'
        && ($visibleBlocks[1]['type'] ?? null) === 'geo_strip'
        && ($site['network_stats']['enabled'] ?? false);
@endphp
<x-layout :title="$home['seo']['title']" :description="$home['seo']['description']" :light-preview="$lightPreview ?? false">
    @include('site.navigation')
    <main class="home-redesign">
        @if($hasHeroStage)
            <div class="hero-stage">
                @include('site.blocks.hero', ['data' => $visibleBlocks[0]['data']])
                @include('site.blocks.network-stats', ['data' => $site['network_stats'], 'compact' => true])
                @include('site.blocks.geo_strip', ['data' => $visibleBlocks[1]['data']])
            </div>
        @endif
        @foreach($visibleBlocks as $block)
            @continue($hasHeroStage && $loop->index < 2)
            @include('site.blocks.'.$block['type'], ['data' => $block['data']])
            @if($block['type'] === 'geo_strip' && ($site['network_stats']['enabled'] ?? false))
                @include('site.blocks.network-stats', ['data' => $site['network_stats']])
            @endif
            @if($block['type'] === 'audiences' && ($site['creator_network']['enabled'] ?? false))
                @include('site.blocks.creator-network', ['data' => $site['creator_network']])
            @endif
        @endforeach
    </main>
    @include('site.footer')
</x-layout>
