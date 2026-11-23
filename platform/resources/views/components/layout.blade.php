@props(['title' => 'Creator partnerships. All in.', 'description' => null, 'lightPreview' => false, 'lightTheme' => true, 'sensitive' => false])
@php
    $introEligible = $lightTheme && ! request()->attributes->get('website.preview')
        && request()->routeIs('home', 'home.white') && ($site['site_intro']['enabled'] ?? false);
    $defaultColorTheme = \App\Support\SiteAppearance::defaultTheme($site);
    $initialColorTheme = $lightPreview ? 'light' : $defaultColorTheme;
    $themeVariables = collect(\App\Support\SiteAppearance::cssVariables($site))->map(fn ($value, $name) => $name.':'.$value)->implode(';');
@endphp
<!doctype html>
<html lang="en" @if($lightTheme) class="light-preview site-redesign" data-color-theme="{{ $initialColorTheme }}" data-default-theme="{{ $defaultColorTheme }}" data-theme-locked="{{ $lightPreview ? 'true' : 'false' }}" style="{{ $themeVariables }}" @endif><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? $site['site_description'] }}">
    <meta name="theme-color" content="{{ $lightTheme && $initialColorTheme === 'light' ? '#f5f6fa' : '#090a0c' }}"><title>{{ $title }} — {{ $site['brand_name'] }}</title>
    @if($lightTheme)<x-color-theme-script/>@endif
    @if($lightPreview)<meta name="robots" content="noindex, nofollow">@endif
    @if($sensitive)<meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">@endif
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" href="/favicon-32x32.png?v=2" type="image/png" sizes="32x32">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2" sizes="180x180">
    @if($lightTheme && ! $sensitive && ! request()->attributes->get('website.preview'))<x-site-intro-gate :eligible="$introEligible"/>@endif
    @vite(['resources/css/app.css','resources/js/app.js'])
    @unless($lightPreview || $sensitive)
        @livewireStyles
    @endunless
</head><body @if($lightTheme) class="theme-white" @endif>
    {{ $slot }}
    @if($introEligible)<x-site-intro :clips="$site['creator_reels']['clips']" :data="$site['site_intro']"/>@endif
    @if(request()->attributes->get('website.preview'))
        <span class="site-preview-label">Draft preview · Changes are not public</span>
        <script>
            document.addEventListener('submit', event => event.preventDefault(), true);
            document.addEventListener('click', event => {
                const link = event.target.closest('a[href]');
                if (link && !link.getAttribute('href').startsWith('#')) event.preventDefault();
            }, true);
        </script>
    @endif
    @unless($lightPreview || $sensitive)
        @livewireScripts
    @endunless
</body></html>
