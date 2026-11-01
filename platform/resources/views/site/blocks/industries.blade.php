<section class="section industries-section line-top" id="industries"><div class="container">
    @include('site.section-heading')
    <div class="audience-cards">@foreach($data['cards'] as $card)<article class="industry-card">
        @if($card['image_path'])<img class="content-card-image" src="{{ Storage::disk('public')->url($card['image_path']) }}" alt="{{ $card['image_alt'] }}" loading="lazy">@endif
        <div class="industry-pattern"><x-ui-icon :name="$card['icon']"/></div><x-ui-icon :name="$card['icon']"/>
        <h3>{{ $card['title'] }}</h3><p>{{ $card['description'] }}</p>
    </article>@endforeach</div>
</div></section>
