@props(['compact' => false])
<a href="/" {{ $attributes->class(['brand']) }} aria-label="{{ $site['brand_name'] }} home">
    @if($site['logo_path'])
        <img class="brand-upload" src="{{ Storage::disk('public')->url($site['logo_path']) }}" alt="{{ $site['brand_name'] }}" width="38" height="38">
    @else
        <svg width="38" height="38" viewBox="0 0 44 44" fill="none" aria-hidden="true"><path d="M4 34 19 5h9L13 34H4Z" fill="currentColor"/><path d="m24 17 16 17H29L19 24l5-7Z" fill="currentColor"/><path d="m16 37 4-8h19l-4 8H16Z" fill="currentColor" opacity=".5"/></svg>
    @endif
    @unless($compact)
        <span>@if($site['brand_name'] === 'All In Streams')all in <b>streams</b>@else{{ $site['brand_name'] }}@endif<small>{{ $site['brand_tagline'] }}</small></span>
    @endunless
</a>
