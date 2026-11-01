<section class="hero" id="top"><div class="container"><div class="hero-grid">
    <div class="hero-copy">
        <span class="eyebrow"><span class="live-dot"></span> {{ $data['eyebrow'] }}</span>
        <h1>{{ $data['title_line_1'] }}<br><span>{{ $data['title_line_2'] }}</span></h1>
        <div class="hero-subtitle">{{ $data['subtitle'] }} <span class="teal">{{ $data['subtitle_accent'] }}</span></div>
        <p>{{ $data['body'] }}</p>
        <div class="hero-buttons"><a href="{{ $data['primary_url'] }}" class="btn btn-primary">{{ $data['primary_label'] }} <x-ui-icon name="arrow" size="17"/></a><a href="{{ $data['secondary_url'] }}" class="btn btn-secondary">{{ $data['secondary_label'] }}</a></div>
        <span class="hero-footnote"><x-ui-icon name="shield" size="13"/> {{ $data['footnote'] }}</span>
    </div>
    <div class="hero-visual" aria-label="{{ $data['image_alt'] }}">
        @if($data['image_path'])
            <img class="hero-editor-image" data-motion-reveal src="{{ Storage::disk('public')->url($data['image_path']) }}" alt="{{ $data['image_alt'] }}" fetchpriority="high">
        @else
            <div class="visual-orbit"></div><span class="visual-cross">+</span>
            <div class="float-label top"><x-ui-icon name="users" size="17"/> {{ $data['float_top'] }}</div>
            <div class="partnership-card"><div class="preview-top"><span><span class="live-dot"></span> {{ $data['preview_label'] }}</span><x-ui-icon name="layers" size="13"/></div>
                <h2>{{ $data['preview_title_1'] }}<br>{{ $data['preview_title_2'] }}<br><span>{{ $data['preview_highlight'] }}</span></h2>
                <span class="preview-pill"><x-ui-icon name="check" size="11"/> {{ $data['preview_pill'] }}</span>
                <div class="preview-match">@foreach($data['preview_countries'] as $country)<x-country-flag :code="$country['code']" :name="$country['name']"/>@endforeach<span>{{ $data['preview_caption'] }}</span></div>
                <div class="match-bars"><i></i><i></i><i></i></div>
            </div>
            <div class="float-label bottom">{{ $data['float_bottom'] }} <x-ui-icon name="arrow" size="16"/></div><span class="mini-label">{{ $data['vertical_label'] }}</span>
        @endif
    </div>
</div><div class="hero-bottom"><span><span class="live-dot"></span> {{ $data['bottom_statement'] }}</span><span>{{ $data['scroll_label'] }} <x-ui-icon name="chevron" size="12"/></span></div></div></section>
