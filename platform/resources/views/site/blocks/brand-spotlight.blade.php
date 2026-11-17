@php
    $spotlightImage = ! empty($data['image_path'])
        ? Storage::disk('public')->url($data['image_path'])
        : '/images/brand-spotlight/streamer.jpg';
@endphp
<div class="brand-spotlight" data-brand-spotlight data-motion-group>
    <div class="brand-spotlight-copy" data-motion-reveal>
        <h2 id="brand-spotlight-title"><span>{{ $data['title_line_1'] }}</span><span>{{ $data['title_line_2'] }}</span></h2>
        <p>{{ $data['body'] }}</p>
        <div class="brand-spotlight-actions">
            <a class="btn btn-primary" href="{{ $data['primary_url'] }}">{{ $data['primary_label'] }}<x-ui-icon name="arrow" size="18"/></a>
            <a class="btn btn-secondary" href="{{ $data['secondary_url'] }}">{{ $data['secondary_label'] }}<x-ui-icon name="chevron" size="18"/></a>
        </div>
    </div>
    <div class="brand-spotlight-visual" data-motion-reveal>
        <img class="brand-spotlight-photo" src="{{ $spotlightImage }}" alt="{{ $data['image_alt'] }}" width="1240" height="820" loading="lazy" decoding="async">
        <svg class="brand-spotlight-connector" viewBox="0 0 120 132" fill="none" aria-hidden="true" focusable="false"><path d="M15 2C18 32 1 40 1 63C1 81 7 86 24 86H46"/><circle cx="46" cy="86" r="5"/></svg>
        <aside class="brand-spotlight-match" aria-label="Partnership example">
            <span class="brand-spotlight-match-icon" aria-hidden="true"><x-ui-icon name="briefcase" size="29"/></span>
            <div class="brand-spotlight-match-copy">
                <strong>{{ $data['card_title'] }}</strong>
                <span><i aria-hidden="true"><x-ui-icon name="check" size="12"/></i>{{ $data['card_status'] }}</span>
            </div>
        </aside>
    </div>
</div>
