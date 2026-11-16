@php
    $headlines = array_merge(
        [['line_1' => $data['title_line_1'], 'line_2' => $data['title_line_2']]],
        $data['headlines'] ?? []
    );
@endphp
<section class="hero hero-reference" id="top">
    <div class="container">
        <div class="hero-grid{{ ! ($site['network_stats']['enabled'] ?? false) && ! $data['image_path'] ? ' hero-grid--copy-only' : '' }}" data-motion-group>
            <div class="hero-copy">
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
            @if($site['network_stats']['enabled'] ?? false)
                <div class="hero-art hero-network-art" data-motion-reveal>
                    <x-rope-statistics :data="$site['network_stats']"/>
                </div>
            @elseif($data['image_path'])
                <div class="hero-art" data-motion-reveal>
                    <img class="hero-art-image" src="{{ Storage::disk('public')->url($data['image_path']) }}" alt="{{ $data['image_alt'] }}" width="660" height="660" fetchpriority="high">
                </div>
            @endif
        </div>
        <div class="hero-signoff"><span>{{ $data['bottom_statement'] }}</span><a href="#platform">{{ $data['scroll_label'] }}<x-ui-icon name="chevron" size="14"/></a></div>
    </div>
</section>
