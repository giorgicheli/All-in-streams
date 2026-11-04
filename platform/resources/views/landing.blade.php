<x-layout :title="$home['seo']['title']" :description="$home['seo']['description']">
    @include('site.navigation')
    <main>
        @foreach($home['blocks'] as $block)
            @if($block['data']['enabled'] ?? true)
                @include('site.blocks.'.$block['type'], ['data' => $block['data']])
                @if($block['type'] === 'geo_strip' && ($site['network_stats']['enabled'] ?? false))
                    @include('site.blocks.network-stats', ['data' => $site['network_stats']])
                @endif
            @endif
        @endforeach
    </main>
    @include('site.footer')
</x-layout>
