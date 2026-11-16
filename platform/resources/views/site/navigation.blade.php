@php
    $siteNavUrl = fn (string $url) => str_starts_with($url, '#') && !isset($home) ? '/'.$url : $url;
    $platformMenu = $site['platform_menu'] ?? [
        ['label' => 'For brands', 'description' => 'Find creators for your next campaign', 'url' => '/talk-to-us?type=brand'],
        ['label' => 'For agencies', 'description' => 'Expand your creator partnerships', 'url' => '/talk-to-us?type=agency'],
        ['label' => 'For creators', 'description' => 'Join our creator network', 'url' => '/talk-to-us?type=creator'],
    ];
@endphp
<header class="site-header" data-mobile-navigation>
    <div class="container site-nav"><x-brand :home-url="($lightPreview ?? false) ? route('home.white') : '/'"/>
        <nav class="desktop-site-navigation" aria-label="Main navigation">
            @foreach($site['navigation'] as $link)
                @if(parse_url($link['url'], PHP_URL_FRAGMENT) === 'platform')
                    <details class="platform-dropdown" data-platform-dropdown>
                        <summary>{{ $link['label'] }}<x-ui-icon name="chevron" size="12"/></summary>
                        <div class="platform-dropdown-panel">
                            @foreach($platformMenu as $item)<a href="{{ $item['url'] }}"><strong>{{ $item['label'] }}</strong><span>{{ $item['description'] }}</span></a>@endforeach
                        </div>
                    </details>
                @else
                    <a href="{{ $siteNavUrl($link['url']) }}">{{ $link['label'] }}</a>
                @endif
            @endforeach
        </nav>
        <div class="header-actions">
            @auth<a href="/account" class="sign-in">{{ $site['header']['workspace_label'] }}</a>@else<a href="/login" class="sign-in">{{ $site['header']['sign_in_label'] }}</a>@endauth
            <a href="{{ $site['header']['cta_url'] }}" class="btn btn-primary btn-small">{{ $site['header']['cta_label'] }} <x-ui-icon name="arrow" size="14"/></a>
            <button type="button" class="icon-button mobile-toggle site-menu-toggle" data-mobile-menu-open aria-controls="site-mobile-menu" aria-expanded="false" aria-label="Open navigation"><span class="site-menu-lines" aria-hidden="true"><i></i><i></i><i></i></span></button>
        </div>
    </div>
</header>
<div class="site-reading-progress" aria-hidden="true"></div>
<div id="site-mobile-menu" class="mobile-nav site-mobile-menu" data-mobile-menu role="dialog" aria-modal="true" aria-label="Main navigation" tabindex="-1" data-lenis-prevent hidden inert>
    <div class="site-mobile-menu-shell">
        <div class="site-mobile-menu-top"><x-brand :home-url="($lightPreview ?? false) ? route('home.white') : '/'"/><button type="button" class="icon-button site-menu-close" data-mobile-menu-close aria-label="Close navigation"><span aria-hidden="true"><i></i><i></i></span></button></div>
        <nav class="site-mobile-menu-links" aria-label="Mobile navigation">
            @foreach($site['navigation'] as $link)
                @php $fragment = parse_url($link['url'], PHP_URL_FRAGMENT); $menuIcon = match ($fragment) { 'platform' => 'layers', 'how-it-works' => 'target', 'creators' => 'users', 'about' => 'globe', default => 'globe' }; @endphp
                @if($fragment === 'platform')
                    <details class="mobile-platform-dropdown">
                        <summary class="site-mobile-menu-link" style="--menu-index:{{ $loop->index }}"><span class="site-mobile-menu-icon"><x-ui-icon :name="$menuIcon" size="22"/></span><span>{{ $link['label'] }}</span><x-ui-icon name="chevron" size="19" class="site-mobile-menu-arrow"/></summary>
                        <div class="mobile-platform-links">@foreach($platformMenu as $item)<a href="{{ $item['url'] }}"><span>{{ $item['label'] }}</span><x-ui-icon name="arrow" size="15"/></a>@endforeach</div>
                    </details>
                @else
                    <a href="{{ $siteNavUrl($link['url']) }}" class="site-mobile-menu-link" style="--menu-index:{{ $loop->index }}"><span class="site-mobile-menu-icon"><x-ui-icon :name="$menuIcon" size="22"/></span><span>{{ $link['label'] }}</span><x-ui-icon name="arrow" size="19" class="site-mobile-menu-arrow"/></a>
                @endif
            @endforeach
            @auth<a href="/account" class="site-mobile-menu-link site-mobile-menu-account" style="--menu-index:{{ count($site['navigation']) }}"><span class="site-mobile-menu-icon"><x-ui-icon name="user" size="22"/></span><span>{{ $site['header']['workspace_label'] }}</span><x-ui-icon name="arrow" size="19" class="site-mobile-menu-arrow"/></a>
            @else<a href="/login" class="site-mobile-menu-link site-mobile-menu-account" style="--menu-index:{{ count($site['navigation']) }}"><span class="site-mobile-menu-icon"><x-ui-icon name="user" size="22"/></span><span>{{ $site['header']['sign_in_label'] }}</span><x-ui-icon name="arrow" size="19" class="site-mobile-menu-arrow"/></a>@endauth
        </nav>
        <div class="site-mobile-menu-bottom"><a href="{{ $site['header']['cta_url'] }}" class="btn btn-primary">{{ $site['header']['cta_label'] }} <x-ui-icon name="arrow" size="18"/></a><span><i class="live-dot"></i>{{ $site['brand_tagline'] }}</span></div>
    </div>
</div>
