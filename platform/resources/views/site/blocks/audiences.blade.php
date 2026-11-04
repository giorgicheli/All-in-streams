<section class="section" id="platform"><div class="container">
    @include('site.section-heading')
    <div class="audience-cards">@foreach($data['cards'] as $card)<a href="{{ $card['url'] }}" class="audience-card">
        @if($card['image_path'])<img class="content-card-image" src="{{ Storage::disk('public')->url($card['image_path']) }}" alt="{{ $card['image_alt'] }}" loading="lazy">@endif
        <div class="card-top"><x-ui-icon :name="$card['icon']" size="26"/></div>
        <h3>{{ $card['title'] }}</h3><p>{{ $card['description'] }}</p><span class="text-link">{{ $card['label'] }} <x-ui-icon name="arrow" size="15"/></span>
    </a>@endforeach</div>
</div></section>
