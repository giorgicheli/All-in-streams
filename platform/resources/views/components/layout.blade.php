@props(['title' => 'Creator partnerships. All in.', 'description' => null])
<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? $site['site_description'] }}">
    <meta name="theme-color" content="#090d0c"><title>{{ $title }} — {{ $site['brand_name'] }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css','resources/js/app.js'])@livewireStyles
</head><body>
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
    @livewireScripts
</body></html>
