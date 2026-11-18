@props(['compact' => false, 'homeUrl' => '/'])
<a href="{{ $homeUrl }}" {{ $attributes->class(['brand']) }} aria-label="{{ $site['brand_name'] }} home">
    @if($site['logo_path'])
        <img class="brand-upload" src="{{ Storage::disk('public')->url($site['logo_path']) }}" alt="{{ $site['brand_name'] }}" width="38" height="38">
    @else
        <x-brand-mark />
    @endif
    @unless($compact)
        <span>@if($site['brand_name'] === 'All In Streams')all in <b>streams</b>@else{{ $site['brand_name'] }}@endif<small>{{ $site['brand_tagline'] }}</small></span>
    @endunless
</a>
