<div class="geo-strip" data-geo-carousel><div class="container geo-inner">
    <strong class="geo-label">{{ $data['label'] }}</strong>
    <div class="geo-viewport" data-geo-viewport data-lenis-prevent tabindex="0" role="region" aria-label="Audience countries" aria-describedby="geo-carousel-help">
        <div class="geo-track"><div class="geo-items">
            @foreach($data['countries'] as $country)
                <span class="geo-item"><x-country-flag :code="$country['code']" :name="$country['name']"/><span class="geo-country-name">{{ $country['name'] }}</span></span>
            @endforeach
        </div></div>
    </div>
    <strong class="geo-end-label teal">{{ $data['end_label'] }} ↗</strong>
    <span id="geo-carousel-help" class="sr-only">Swipe or use the arrow keys to explore countries. Automatic movement pauses while you interact.</span>
</div></div>
