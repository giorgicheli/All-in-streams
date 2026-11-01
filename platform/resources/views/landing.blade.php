<x-layout :title="$home['seo']['title']" :description="$home['seo']['description']">
    @include('site.navigation')
    <main>
        @foreach($home['blocks'] as $block)
            @if($block['data']['enabled'] ?? true)
                @include('site.blocks.'.$block['type'], ['data' => $block['data']])
            @endif
        @endforeach
    </main>
    @include('site.footer')
</x-layout>
