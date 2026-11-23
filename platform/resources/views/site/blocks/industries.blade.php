<section class="section industries-section line-top" id="industries"><div class="container">
    @include('site.section-heading', ['hideEyebrow' => true, 'headingLevel' => 3])
    <div class="audience-cards">@foreach($data['cards'] as $card)<article class="industry-card">
        @if($card['image_path'])<img class="content-card-image" src="{{ Storage::disk('public')->url($card['image_path']) }}" alt="{{ $card['image_alt'] }}" loading="lazy">@endif
        <div class="industry-pattern"><x-ui-icon :name="$card['icon']"/></div><x-ui-icon :name="$card['icon']"/>
        <h4>{{ $card['title'] }}</h4><p>{{ $card['description'] }}</p>
    </article>@endforeach</div>
</div></section>
