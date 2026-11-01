<div class="geo-strip"><div class="container geo-inner"><strong>{{ $data['label'] }}</strong>
    @foreach($data['countries'] as $country)<span class="geo-item"><x-country-flag :code="$country['code']" :name="$country['name']"/>{{ $country['name'] }}</span>@endforeach
    <strong class="teal">{{ $data['end_label'] }} ↗</strong>
</div></div>
