@props(['defaultTheme' => 'dark'])
@php $isDarkTheme = $defaultTheme === 'dark'; @endphp
<button type="button" class="theme-toggle" data-theme-toggle aria-pressed="{{ $isDarkTheme ? 'true' : 'false' }}" aria-label="Dark theme" title="{{ $isDarkTheme ? 'Switch to light theme' : 'Switch to dark theme' }}">
    <svg class="theme-toggle-icon" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">
        <g class="theme-toggle-rays"><path d="M16 3v3M16 26v3M3 16h3M26 16h3M6.8 6.8l2.1 2.1M23.1 23.1l2.1 2.1M6.8 25.2l2.1-2.1M23.1 8.9l2.1-2.1"/></g>
        <circle class="theme-toggle-core" cx="16" cy="16" r="6.8"/>
        <circle class="theme-toggle-mask" cx="20" cy="12" r="6.8"/>
        <ellipse class="theme-toggle-orbit" cx="16" cy="16" rx="13.8" ry="8.8"/>
        <g class="theme-toggle-sparks"><path d="m7 5 .8 2.2L10 8l-2.2.8L7 11l-.8-2.2L4 8l2.2-.8L7 5Zm18 17 .6 1.8 1.9.7-1.9.6L25 27l-.6-1.9-1.9-.6 1.9-.7L25 22Z"/></g>
    </svg>
</button>
