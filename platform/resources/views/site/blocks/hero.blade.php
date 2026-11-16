@php
    $headlines = array_merge(
        [['line_1' => $data['title_line_1'], 'line_2' => $data['title_line_2']]],
        $data['headlines'] ?? []
    );
@endphp
<section class="hero hero-reference" id="top">
    <div class="container">
        <div class="hero-grid" data-motion-group>
            <div class="hero-copy">
                <span class="eyebrow"><span class="live-dot"></span>{{ $data['eyebrow'] }}</span>
                <h1 class="hero-headline" data-hero-headlines>
                    <span class="sr-only">{{ $data['title_line_1'] }} {{ $data['title_line_2'] }}</span>
                    <span class="hero-headline-visual" aria-hidden="true">
                        @foreach($headlines as $headline)
                            <span class="hero-headline-item" data-hero-headline @if(! $loop->first) hidden @endif>
                                <span class="hero-headline-line">{{ $headline['line_1'] }}</span>
                                <span class="hero-headline-line">{{ $headline['line_2'] }}</span>
                            </span>
                        @endforeach
                    </span>
                </h1>
                <p>{{ $data['body'] }}</p>
                <div class="hero-buttons">
                    <a href="{{ $data['primary_url'] }}" class="btn btn-primary">{{ $data['primary_label'] }}<x-ui-icon name="arrow" size="17"/></a>
                    <a href="{{ $data['secondary_url'] }}" class="btn btn-secondary">{{ $data['secondary_label'] }}<x-ui-icon name="chevron" size="16"/></a>
                </div>
                <span class="hero-footnote"><x-ui-icon name="check" size="14"/>{{ $data['footnote'] }}</span>
            </div>
            <div class="hero-art" data-motion-reveal>
                @if($data['image_path'])
                    <img class="hero-art-image" src="{{ Storage::disk('public')->url($data['image_path']) }}" alt="{{ $data['image_alt'] }}" width="660" height="660" fetchpriority="high">
                @else
                    <x-hero-signal/>
                @endif
                <span class="hero-art-label"><span class="live-dot"></span>{{ $data['float_top'] }}</span>
                <span class="hero-art-caption"><x-ui-icon name="spark" size="17"/>{{ $data['preview_pill'] }}</span>
            </div>
        </div>
        <div class="hero-signoff"><span>{{ $data['bottom_statement'] }}</span><a href="#platform">{{ $data['scroll_label'] }}<x-ui-icon name="chevron" size="14"/></a></div>
    </div>
</section>
