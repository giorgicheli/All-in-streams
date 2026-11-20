@php
    $headlines = array_merge(
        [['line_1' => $data['title_line_1'], 'line_2' => $data['title_line_2']]],
        $data['headlines'] ?? []
    );
    $hasCreatorOrbit = $site['creator_orbit']['enabled'] ?? false;
    $hasNetworkArtwork = ($site['network_stats']['enabled'] ?? false) && ! ($suppressHeroNetwork ?? false);
    $mobileWords = preg_split('/\s+/u', trim($data['mobile_body'] ?? $data['body']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $mobileWordCount = count($mobileWords);
    $mobileTailLength = min($mobileWordCount, min(12, max(6, (int) ceil($mobileWordCount * .35))));
    for ($wordIndex = $mobileWordCount - 2; $wordIndex >= 0; $wordIndex--) {
        if (preg_match('/[.!?。！？…][\p{Pe}\p{Pf}"\']*$/u', $mobileWords[$wordIndex])) {
            $sentenceLength = $mobileWordCount - $wordIndex - 1;
            if ($sentenceLength >= 6 && $sentenceLength <= 12) $mobileTailLength = $sentenceLength;
            break;
        }
    }
    $mobileLead = implode(' ', array_slice($mobileWords, 0, $mobileWordCount - $mobileTailLength));
    $mobileTailWords = array_slice($mobileWords, $mobileWordCount - $mobileTailLength);
    $mobileTailCount = count($mobileTailWords);
    $mobileTailSplit = max(1, intdiv($mobileTailCount, 2));
    $mobileTailMinimum = max(1, (int) ceil($mobileTailCount * .3));
    $mobileTailDistance = INF;
    for ($wordIndex = $mobileTailMinimum; $wordIndex <= $mobileTailCount - $mobileTailMinimum; $wordIndex++) {
        $distance = abs($wordIndex - $mobileTailCount / 2);
        if (preg_match('/[,;:，；：、—–][\p{Pe}\p{Pf}"\']*$/u', $mobileTailWords[$wordIndex - 1]) && $distance < $mobileTailDistance) {
            $mobileTailSplit = $wordIndex;
            $mobileTailDistance = $distance;
        }
    }
    $mobileTailLines = $mobileTailCount > 1
        ? [implode(' ', array_slice($mobileTailWords, 0, $mobileTailSplit)), implode(' ', array_slice($mobileTailWords, $mobileTailSplit))]
        : [implode(' ', $mobileTailWords)];
@endphp
<section class="hero hero-reference" id="top">
    <div class="container">
        <div class="hero-grid{{ $hasCreatorOrbit ? ' hero-grid--orbit' : '' }}{{ ! $hasCreatorOrbit && ! $hasNetworkArtwork && ! $data['image_path'] ? ' hero-grid--copy-only' : '' }}" data-motion-group>
            @if($hasCreatorOrbit)
                <div class="hero-art hero-orbit-art">
                    <x-creator-orbit :clips="$site['creator_reels']['clips']" :brand="$site['brand_spotlight']" :data="$site['creator_orbit']"/>
                </div>
            @endif
            <div class="hero-copy">
                <div class="hero-market-label">{{ $data['market_label'] }}</div>
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
                <p class="hero-desktop-description">{{ $data['body'] }}</p>
                <p class="hero-mobile-description">@if($mobileLead !== '')<span class="hero-mobile-lead">{{ $mobileLead }}</span>{{ ' ' }}@endif<span class="hero-mobile-tail">@foreach($mobileTailLines as $mobileTailLine)<span class="hero-mobile-tail-line">{{ $mobileTailLine }}</span>@unless($loop->last){{ ' ' }}@endunless @endforeach</span></p>
                <div class="hero-buttons">
                    <a href="{{ $data['primary_url'] }}" class="btn btn-primary">{{ $data['primary_label'] }}<x-ui-icon name="arrow" size="17"/></a>
                    <a href="{{ $data['secondary_url'] }}" class="btn btn-secondary">{{ $data['secondary_label'] }}<x-ui-icon name="chevron" size="16"/></a>
                </div>
                <ul class="hero-benefits" aria-label="Partnership benefits">
                    @foreach($data['benefits'] as $benefit)
                        <li><x-ui-icon name="check" size="16"/><span>{{ $benefit['text'] }}</span></li>
                    @endforeach
                </ul>
                <span class="hero-footnote"><x-ui-icon name="check" size="14"/>{{ $data['footnote'] }}</span>
            </div>
            @if(! $hasCreatorOrbit && $hasNetworkArtwork)
                <div class="hero-art hero-network-art" data-motion-reveal>
                    <x-rope-statistics :data="$site['network_stats']"/>
                </div>
            @elseif(! $hasCreatorOrbit && $data['image_path'])
                <div class="hero-art" data-motion-reveal>
                    <img class="hero-art-image" src="{{ Storage::disk('public')->url($data['image_path']) }}" alt="{{ $data['image_alt'] }}" width="660" height="660" fetchpriority="high">
                </div>
            @endif
        </div>
        <div class="hero-signoff"><span>{{ $data['bottom_statement'] }}</span><a href="#platform">{{ $data['scroll_label'] }}<x-ui-icon name="chevron" size="14"/></a></div>
    </div>
</section>
