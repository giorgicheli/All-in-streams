@php
    $hasCreatorOrbit = $site['creator_orbit']['enabled'] ?? false;
    $hasNetworkArtwork = ($site['network_stats']['enabled'] ?? false) && ! ($suppressHeroNetwork ?? false);
@endphp
<section class="hero hero-reference" id="top">
    <div class="container">
        <div class="hero-grid{{ $hasCreatorOrbit ? ' hero-grid--orbit' : '' }}{{ ! $hasCreatorOrbit && ! $hasNetworkArtwork && ! $data['image_path'] ? ' hero-grid--copy-only' : '' }}" data-motion-group>
            <div class="hero-copy">
                <h1 class="hero-headline"><span class="hero-headline-line">{{ $data['title_line_1'] }}</span> <span class="hero-headline-line">{{ $data['title_line_2'] }}</span></h1>
                <p class="hero-description">{{ $data['body'] }}</p>
                <div class="hero-buttons">
                    <a href="{{ $data['primary_url'] }}" class="btn btn-primary">{{ $data['primary_label'] }}<x-ui-icon name="arrow" size="17"/></a>
                    <a href="{{ $data['secondary_url'] }}" class="btn btn-secondary">{{ $data['secondary_label'] }}<x-ui-icon name="chevron" size="16"/></a>
                </div>
            </div>
            @if($hasCreatorOrbit)
                <div class="hero-art hero-orbit-art">
                    <x-creator-orbit :clips="$site['creator_reels']['clips']" :brand="$site['brand_spotlight']" :data="$site['creator_orbit']" minimal/>
                </div>
            @elseif($hasNetworkArtwork)
                <div class="hero-art hero-network-art" data-motion-reveal><x-rope-statistics :data="$site['network_stats']"/></div>
            @elseif($data['image_path'])
                <div class="hero-art" data-motion-reveal><img class="hero-art-image" src="{{ Storage::disk('public')->url($data['image_path']) }}" alt="{{ $data['image_alt'] }}" width="660" height="660" fetchpriority="high"></div>
            @endif
        </div>
    </div>
</section>
