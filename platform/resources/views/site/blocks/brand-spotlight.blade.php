@php
    $audienceStyles = ['brands', 'agencies', 'creators'];
@endphp
<div class="ais-hero" data-brand-spotlight aria-labelledby="brand-spotlight-title">
    <div class="ais-hero__inner" data-motion-group>
        <div class="ais-hero__content">
            <h2 class="ais-hero__title" id="brand-spotlight-title" data-motion-reveal><span>{{ $data['title_line_1'] }}</span><span class="ais-hero__accent">{{ $data['title_line_2'] }}</span></h2>
            <p class="ais-hero__intro" data-motion-reveal>{{ $data['body'] }}</p>
            <div class="ais-hero__audiences" data-motion-group>
                @foreach($cards as $card)
                    <a class="ais-hero__audience ais-hero__audience--{{ $audienceStyles[$loop->index % 3] }}" href="{{ $card['url'] }}" aria-label="{{ $card['title'] }} — {{ $card['label'] }}" data-motion-reveal>
                        <span class="ais-hero__icon" aria-hidden="true"><x-ui-icon :name="$card['icon']" size="34"/></span>
                        <div class="ais-hero__audience-copy"><h3>{{ $card['title'] }}</h3><p>{{ $card['description'] }}</p></div>
                        <svg class="ais-hero__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 18 18 6M6 6h12v12"/></svg>
                    </a>
                @endforeach
            </div>
        </div>
        <figure class="ais-hero__visual ais-hero__visual--motion" data-motion-reveal>
            <x-motion-graphic />
            <figcaption class="sr-only">How All In Streams connects brands, agencies, streamers and influencers through creator campaigns and reporting.</figcaption>
        </figure>
    </div>
</div>
