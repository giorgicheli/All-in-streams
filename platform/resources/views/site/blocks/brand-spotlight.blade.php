@php
    $spotlightImage = ! empty($data['image_path'])
        ? Storage::disk('public')->url($data['image_path'])
        : '/images/brand-spotlight/streamer-portrait.webp';
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
        <figure class="ais-hero__visual" data-motion-reveal>
            <div class="ais-hero__photo-frame">
                <img class="ais-hero__photo brand-spotlight-photo" src="{{ $spotlightImage }}" width="1122" height="1402" alt="{{ $data['image_alt'] }}" loading="lazy" decoding="async">
            </div>
            <figcaption class="ais-hero__campaign" aria-label="Partnership example">
                <svg class="ais-hero__connector" viewBox="0 0 67 128" fill="none" aria-hidden="true" focusable="false"><path d="M38 2C47 57 3 73 14 106C19 122 38 128 66 128" pathLength="1" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>
                <div class="ais-hero__campaign-card">
                    <span class="ais-hero__dot" aria-hidden="true"></span>
                    <svg class="ais-hero__briefcase" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 7V4.5h8V7M3 10h26v17H3zM3 15.5c7 4 19 4 26 0M13 17h6v4h-6z"/></svg>
                    <div>
                        <p class="ais-hero__campaign-title">{{ $data['card_title'] }}</p>
                        <div class="ais-hero__campaign-status"><svg class="ais-hero__check" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="10" fill="currentColor"/><path d="m5.8 10 2.7 2.7 5.8-5.6" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg><span>{{ $data['card_status'] }}</span></div>
                    </div>
                </div>
            </figcaption>
        </figure>
    </div>
</div>
