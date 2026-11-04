<section class="cta-section" id="get-started"><div class="container"><div class="cta-box">
    @if($data['image_path'])<img class="cta-editor-image" src="{{ Storage::disk('public')->url($data['image_path']) }}" alt="{{ $data['image_alt'] }}" loading="lazy">@endif
    <div class="cta-copy"><h2>{{ $data['title'] }}</h2><p>{{ $data['description'] }}</p></div>
    <div class="cta-buttons"><a href="{{ $data['primary_url'] }}" class="btn">{{ $data['primary_label'] }} <x-ui-icon name="arrow" size="17"/></a><a href="{{ $data['secondary_url'] }}">{{ $data['secondary_label'] }} ↗</a></div>
</div></div></section>
