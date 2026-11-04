@props(['code' => null, 'name' => null, 'size' => 24, 'decorative' => false, 'eager' => false])
@php
    $resolvedCode = \App\Support\Countries::code($code ?: $name);
    $label = $name ?: \App\Support\Countries::name($resolvedCode) ?: strtoupper((string) $code);
    $url = \App\Support\Countries::flagUrl($resolvedCode);
    $width = max(12, min(96, (int) $size));
    $height = (int) round($width * 2 / 3);
@endphp
@if($url)
    <img src="{{ $url }}" width="{{ $width }}" height="{{ $height }}" alt="{{ $decorative ? '' : 'Flag of '.$label }}" @if($decorative) aria-hidden="true" @endif loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" {{ $attributes->class('country-flag')->style('width:'.$width.'px;height:'.$height.'px;object-fit:contain;object-position:center;display:inline-block;vertical-align:middle;flex-shrink:0;border-radius:2px') }}>
@else
    <span {{ $attributes->class('country-flag-fallback') }} @if($decorative) aria-hidden="true" @else aria-label="{{ $label }}" @endif>{{ $resolvedCode ?: strtoupper((string) $code) }}</span>
@endif
