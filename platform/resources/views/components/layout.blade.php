@props(['title' => 'Creator partnerships. All in.', 'description' => null, 'lightPreview' => false])
<!doctype html>
<html lang="en" @if($lightPreview) class="light-preview" @endif><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? $site['site_description'] }}">
    <meta name="theme-color" content="{{ $lightPreview ? '#ffffff' : '#090d0c' }}"><title>{{ $title }} — {{ $site['brand_name'] }}</title>
    @if($lightPreview)<meta name="robots" content="noindex, nofollow">@endif
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" href="/favicon-32x32.png?v=2" type="image/png" sizes="32x32">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2" sizes="180x180">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @unless($lightPreview)
        @livewireStyles
    @endunless
</head><body @if($lightPreview) class="theme-white" @endif>
    {{ $slot }}
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
    @unless($lightPreview)
        @livewireScripts
    @endunless
</body></html>
