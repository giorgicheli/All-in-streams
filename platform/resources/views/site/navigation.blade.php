<header class="site-header" x-data="{open:false}">
    <div class="container site-nav"><x-brand/>
        <nav aria-label="Main navigation">@foreach($site['navigation'] as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach</nav>
        <div class="header-actions">
            @auth<a href="/account" class="sign-in">{{ $site['header']['workspace_label'] }}</a>@else<a href="/login" class="sign-in">{{ $site['header']['sign_in_label'] }}</a>@endauth
            <a href="{{ $site['header']['cta_url'] }}" class="btn btn-primary btn-small">{{ $site['header']['cta_label'] }} <x-ui-icon name="arrow" size="14"/></a>
            <button class="icon-button mobile-toggle" @click="open=!open" :aria-expanded="open" aria-label="Toggle navigation"><x-ui-icon name="menu"/></button>
        </div>
    </div>
    <nav class="mobile-nav" x-cloak x-show="open" @click="open=false">
        @foreach($site['navigation'] as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach
        <a href="/login">{{ $site['header']['sign_in_label'] }}</a>
    </nav>
</header>
