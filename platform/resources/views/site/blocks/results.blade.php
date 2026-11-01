<section class="section industries-section line-top" id="results"><div class="container">
    @include('site.section-heading')
    <div class="gallery-tabs" role="tablist" aria-label="Campaign region">@foreach($data['regions'] as $region)<button type="button" data-geo="{{ $region['label'] }}" class="gallery-tab {{ $loop->first?'selected':'' }}" role="tab" aria-selected="{{ $loop->first?'true':'false' }}">{{ $region['label'] }}</button>@endforeach</div>
    <div class="gallery-empty" role="tabpanel"><x-ui-icon name="globe"/><h3>{{ $data['empty_title'] }}</h3><p><span data-geo-label>{{ $data['regions'][0]['label'] }}</span> · {{ $data['empty_description'] }}</p></div>
    <div class="gallery-privacy"><x-ui-icon name="shield" size="13"/> {{ $data['privacy_note'] }}</div>
</div></section>
