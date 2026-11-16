@php $signalId = 'hero-signal-'.\Illuminate\Support\Str::random(8); @endphp
<div {{ $attributes->class('hero-signal') }} data-hero-scene aria-hidden="true">
    <svg class="hero-signal-fallback" viewBox="0 0 480 480" width="480" height="480" fill="none" focusable="false" aria-hidden="true">
        <defs>
            <linearGradient id="{{ $signalId }}-emerald" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#0B6373"/><stop offset=".3" stop-color="#43D3DC"/><stop offset=".45" stop-color="#168394"/><stop offset=".7" stop-color="#0A4356"/><stop offset="1" stop-color="#279FB0"/>
            </linearGradient>
            <linearGradient id="{{ $signalId }}-depth" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#072D3C"/><stop offset="1" stop-color="#041723"/>
            </linearGradient>
            <linearGradient id="{{ $signalId }}-lime" x1="0" y1="0" x2="1" y2="1">
                <stop stop-color="#CED4FF"/><stop offset=".35" stop-color="#A9B5FF"/><stop offset=".75" stop-color="#7887D6"/><stop offset="1" stop-color="#BECAFF"/>
            </linearGradient>
            <filter id="{{ $signalId }}-shadow" x="-50%" y="-200%" width="200%" height="500%"><feGaussianBlur stdDeviation="10"/></filter>
        </defs>
        <g transform="translate(-24 -20) scale(1.1)" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="247" cy="410" rx="126" ry="13" fill="#24CBD8" opacity=".09" filter="url(#{{ $signalId }}-shadow)"/>
            <path d="M116 265C115 163 218 112 322 168" stroke="url(#{{ $signalId }}-lime)" stroke-width="31"/>
            <path d="M165 108C232 57 328 86 329 151C330 203 258 226 210 258C146 301 142 345 178 369C225 399 287 360 281 306C277 258 211 223 160 209C104 194 109 145 165 108Z" transform="translate(7 8)" stroke="url(#{{ $signalId }}-depth)" stroke-width="65"/>
            <path d="M165 108C232 57 328 86 329 151C330 203 258 226 210 258C146 301 142 345 178 369C225 399 287 360 281 306C277 258 211 223 160 209C104 194 109 145 165 108Z" stroke="url(#{{ $signalId }}-emerald)" stroke-width="57"/>
            <path d="M165 108C232 57 328 86 329 151C330 203 258 226 210 258C146 301 142 345 178 369C225 399 287 360 281 306C277 258 211 223 160 209C104 194 109 145 165 108Z" transform="translate(-5 -6)" stroke="#C6F8FF" stroke-opacity=".35" stroke-width="3"/>
            <path d="M322 168C385 223 354 317 258 348C186 373 126 337 116 265" transform="translate(3 5)" stroke="#333A72" stroke-opacity=".45" stroke-width="34"/>
            <path d="M322 168C385 223 354 317 258 348C186 373 126 337 116 265" stroke="url(#{{ $signalId }}-lime)" stroke-width="30"/>
            <path d="M322 168C385 223 354 317 258 348C186 373 126 337 116 265" transform="translate(-3 -4)" stroke="#E4E7FF" stroke-opacity=".45" stroke-width="2"/>
        </g>
    </svg>
    <canvas class="hero-signal-canvas" aria-hidden="true"></canvas>
</div>
