<section class="section audience-section" id="platform"><div class="container">
    @if($site['brand_spotlight']['enabled'] ?? false)
        @include('site.blocks.brand-spotlight', ['data' => $site['brand_spotlight'], 'cards' => $data['cards']])
    @else
        @include('site.section-heading', ['hideEyebrow' => true])
        <div class="audience-cards">@foreach($data['cards'] as $card)<a href="{{ $card['url'] }}" class="audience-card">
            @if($card['image_path'])<img class="content-card-image" src="{{ Storage::disk('public')->url($card['image_path']) }}" alt="{{ $card['image_alt'] }}" loading="lazy">@endif
            <div class="card-top"><x-ui-icon :name="$card['icon']" size="26"/><span class="audience-card-arrow" aria-hidden="true"><x-ui-icon name="arrow" size="15"/></span></div>
            <h3>{{ $card['title'] }}</h3><p>{{ $card['description'] }}</p><span class="text-link">{{ $card['label'] }} <x-ui-icon name="arrow" size="15"/></span>
        </a>@endforeach</div>
    @endif
</div></section>
