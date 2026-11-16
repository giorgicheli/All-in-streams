@php $signalId = 'hero-signal-'.\Illuminate\Support\Str::random(8); @endphp
<div {{ $attributes->class('hero-signal') }} data-hero-scene aria-hidden="true">
    <svg class="hero-signal-fallback" viewBox="0 0 480 480" width="480" height="480" fill="none" focusable="false" aria-hidden="true">
        <defs>
            <linearGradient id="{{ $signalId }}-emerald" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#0B4B3A"/><stop offset=".3" stop-color="#39956D"/><stop offset=".45" stop-color="#15583E"/><stop offset=".7" stop-color="#0B3E30"/><stop offset="1" stop-color="#247553"/>
            </linearGradient>
            <linearGradient id="{{ $signalId }}-depth" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#0A392D"/><stop offset="1" stop-color="#092B22"/>
            </linearGradient>
            <linearGradient id="{{ $signalId }}-lime" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#E2F7AD"/><stop offset=".35" stop-color="#B9E85B"/><stop offset=".75" stop-color="#8ABF36"/><stop offset="1" stop-color="#D1F28A"/>
            </linearGradient>
            <filter id="{{ $signalId }}-shadow" x="-50%" y="-200%" width="200%" height="500%"><feGaussianBlur stdDeviation="10"/></filter>
        </defs>
        <g transform="translate(-24 -20) scale(1.1)" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="247" cy="410" rx="126" ry="13" fill="#0B4B3A" opacity=".13" filter="url(#{{ $signalId }}-shadow)"/>
            <path d="M116 265C115 163 218 112 322 168" stroke="url(#{{ $signalId }}-lime)" stroke-width="31"/>
            <path d="M165 108C232 57 328 86 329 151C330 203 258 226 210 258C146 301 142 345 178 369C225 399 287 360 281 306C277 258 211 223 160 209C104 194 109 145 165 108Z" transform="translate(7 8)" stroke="url(#{{ $signalId }}-depth)" stroke-width="65"/>
            <path d="M165 108C232 57 328 86 329 151C330 203 258 226 210 258C146 301 142 345 178 369C225 399 287 360 281 306C277 258 211 223 160 209C104 194 109 145 165 108Z" stroke="url(#{{ $signalId }}-emerald)" stroke-width="57"/>
            <path d="M165 108C232 57 328 86 329 151C330 203 258 226 210 258C146 301 142 345 178 369C225 399 287 360 281 306C277 258 211 223 160 209C104 194 109 145 165 108Z" transform="translate(-5 -6)" stroke="#FFFFFF" stroke-opacity=".15" stroke-width="3"/>
            <path d="M322 168C385 223 354 317 258 348C186 373 126 337 116 265" transform="translate(3 5)" stroke="#537C2B" stroke-opacity=".22" stroke-width="34"/>
            <path d="M322 168C385 223 354 317 258 348C186 373 126 337 116 265" stroke="url(#{{ $signalId }}-lime)" stroke-width="30"/>
            <path d="M322 168C385 223 354 317 258 348C186 373 126 337 116 265" transform="translate(-3 -4)" stroke="#FFFFFF" stroke-opacity=".36" stroke-width="2"/>
        </g>
    </svg>
    <canvas class="hero-signal-canvas" aria-hidden="true"></canvas>
</div>
