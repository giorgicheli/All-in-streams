@php $siteNavUrl = fn (string $url) => str_starts_with($url, '#') && !isset($home) ? '/'.$url : $url; @endphp
<header class="site-header" data-mobile-navigation>
    <div class="container site-nav"><x-brand :home-url="($lightPreview ?? false) ? route('home.white') : '/'"/>
        <nav aria-label="Main navigation">@foreach($site['navigation'] as $link)<a href="{{ $siteNavUrl($link['url']) }}">{{ $link['label'] }}</a>@endforeach</nav>
        <div class="header-actions">
            @auth<a href="/account" class="sign-in">{{ $site['header']['workspace_label'] }}</a>@else<a href="/login" class="sign-in">{{ $site['header']['sign_in_label'] }}</a>@endauth
            <a href="{{ $site['header']['cta_url'] }}" class="btn btn-primary btn-small">{{ $site['header']['cta_label'] }} <x-ui-icon name="arrow" size="14"/></a>
            <button type="button" class="icon-button mobile-toggle site-menu-toggle" data-mobile-menu-open aria-controls="site-mobile-menu" aria-expanded="false" aria-label="Open navigation">
                <span class="site-menu-lines" aria-hidden="true"><i></i><i></i><i></i></span>
            </button>
        </div>
    </div>
</header>
<div id="site-mobile-menu" class="mobile-nav site-mobile-menu" data-mobile-menu role="dialog" aria-modal="true" aria-label="Main navigation" tabindex="-1" data-lenis-prevent hidden inert>
    <div class="site-mobile-menu-shell">
        <div class="site-mobile-menu-top"><x-brand :home-url="($lightPreview ?? false) ? route('home.white') : '/'"/>
            <button type="button" class="icon-button site-menu-close" data-mobile-menu-close aria-label="Close navigation"><span aria-hidden="true"><i></i><i></i></span></button>
        </div>
        <nav class="site-mobile-menu-links" aria-label="Mobile navigation">
            @foreach($site['navigation'] as $link)
                @php $menuIcon = match (parse_url($link['url'], PHP_URL_FRAGMENT)) { 'platform' => 'layers', 'industries' => 'game', 'how-it-works' => 'target', default => 'globe' }; @endphp
                <a href="{{ $siteNavUrl($link['url']) }}" class="site-mobile-menu-link" style="--menu-index:{{ $loop->index }}"><span class="site-mobile-menu-icon"><x-ui-icon :name="$menuIcon" size="22"/></span><span>{{ $link['label'] }}</span><x-ui-icon name="arrow" size="19" class="site-mobile-menu-arrow"/></a>
            @endforeach
            @auth
                <a href="/account" class="site-mobile-menu-link site-mobile-menu-account" style="--menu-index:{{ count($site['navigation']) }}"><span class="site-mobile-menu-icon"><x-ui-icon name="user" size="22"/></span><span>{{ $site['header']['workspace_label'] }}</span><x-ui-icon name="arrow" size="19" class="site-mobile-menu-arrow"/></a>
            @else
                <a href="/login" class="site-mobile-menu-link site-mobile-menu-account" style="--menu-index:{{ count($site['navigation']) }}"><span class="site-mobile-menu-icon"><x-ui-icon name="user" size="22"/></span><span>{{ $site['header']['sign_in_label'] }}</span><x-ui-icon name="arrow" size="19" class="site-mobile-menu-arrow"/></a>
            @endauth
        </nav>
        <div class="site-mobile-menu-bottom">
            <a href="{{ $site['header']['cta_url'] }}" class="btn btn-primary">{{ $site['header']['cta_label'] }} <x-ui-icon name="arrow" size="18"/></a>
            <span><i class="live-dot"></i>{{ $site['brand_tagline'] }}</span>
        </div>
    </div>
</div>
