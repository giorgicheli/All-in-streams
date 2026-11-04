@php $story = $site['auth_story']; @endphp
<aside class="auth-story"><x-brand/>
    <div class="auth-story-content">
        <span class="eyebrow"><span class="live-dot"></span> {{ $story['eyebrow'] }}</span>
        <h1>{{ $story['title_line_1'] }}<br>{{ $story['title_line_2'] }}<br><span class="lime">{{ $story['highlight'] }}</span></h1>
        <p>{{ $story['description'] }}</p>
        <div class="auth-perks">@foreach($story['perks'] as $perk)<span><x-ui-icon :name="$perk['icon']"/> {{ $perk['text'] }}</span>@endforeach</div>
    </div>
    <div class="auth-story-footer">© {{ date('Y') }} {{ $site['brand_name'] }} <span>·</span> {{ $site['footer']['copyright'] }}</div>
</aside>
