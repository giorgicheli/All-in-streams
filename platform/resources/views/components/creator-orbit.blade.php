@props(['clips' => [], 'brand' => [], 'data' => [], 'minimal' => false])
@php
    $orbitLabels = $data + [
        'accessible_label' => 'Brands and creators connected through video',
        'top_title' => 'Gaming creators', 'main_title' => 'Live streamers',
        'main_subtitle' => 'Gaming & entertainment', 'bottom_title' => 'Brands + creators',
        'bottom_subtitle' => 'A shared connection.', 'tag' => 'Better together',
        'preview_label' => 'CREATOR PREVIEW', 'pause_label' => 'Pause motion', 'play_label' => 'Play motion',
    ];
    $orbitClips = [];
    foreach ($clips as $clip) {
        $orbitClips[] = \App\Support\CreatorPreviewClips::media($clip) + [
            'title' => $clip['title'] ?? $orbitLabels['main_title'],
        ];
    }
    if (! $orbitClips) {
        foreach (array_slice(\App\Support\CreatorPreviewClips::defaults(), 0, 3) as $clip) {
            $orbitClips[] = \App\Support\CreatorPreviewClips::media($clip) + ['title' => $orbitLabels['main_title']];
        }
    }
    $orbitCards = [
        ['type' => 'top', 'depth' => '.6', 'label' => $orbitLabels['top_title'], 'subtitle' => null],
        ['type' => 'main', 'depth' => '1', 'label' => $orbitLabels['main_title'], 'subtitle' => $orbitLabels['main_subtitle']],
        ['type' => 'bottom', 'depth' => '.4', 'label' => $orbitLabels['bottom_title'], 'subtitle' => $orbitLabels['bottom_subtitle']],
    ];
    if ($minimal) $orbitCards = array_slice($orbitCards, 0, 2);
@endphp
<section {{ $attributes->class(['creator-orbit', 'creator-orbit--minimal' => $minimal]) }} data-creator-orbit data-orbit-running="false" aria-label="{{ $orbitLabels['accessible_label'] }}">
    <div class="creator-orbit-stage-wrap">
        <div class="creator-orbit-stage" data-orbit-stage>
            <div class="creator-orbit-orbit" aria-hidden="true"></div>
            <svg class="creator-orbit-routes" data-orbit-routes viewBox="0 0 600 508" aria-hidden="true" focusable="false">
                @foreach(['M255 269C255 260 198 260 198 250', 'M255 269C255 215 218 215 218 140', 'M255 269C255 238 288 238 288 218', 'M255 269C255 322 294 322 294 376'] as $route)
                    @if(! $minimal || ! $loop->last)<path data-orbit-route="{{ $loop->index }}" d="{{ $route }}"/><circle data-orbit-flow="{{ $loop->index }}" r="3.2"/>@endif
                @endforeach
            </svg>
            @foreach($orbitCards as $card)
                @php $initialClip = $orbitClips[$loop->index % count($orbitClips)]; @endphp
                <div class="creator-orbit-float-anchor creator-orbit-creator-{{ $card['type'] }}" data-orbit-anchor="{{ $card['type'] }}" data-orbit-depth="{{ $card['depth'] }}">
                    <div class="creator-orbit-float-card{{ $card['type'] !== 'bottom' ? ' creator-orbit-creator-card' : '' }}">
                        <div class="creator-orbit-video-reel {{ $card['type'] === 'bottom' ? 'creator-orbit-portrait' : 'creator-orbit-photo' }}" data-orbit-reel data-orbit-card="{{ $card['type'] }}" data-orbit-start="{{ $loop->index % count($orbitClips) }}" role="img" aria-label="{{ $card['label'] }} — illustrative creator video previews">
                            <img class="creator-orbit-poster" data-orbit-poster src="{{ $initialClip['poster'] }}" alt="" loading="lazy" decoding="async" width="640" height="360">
                            <video data-orbit-video muted loop playsinline preload="none" aria-hidden="true" disablepictureinpicture></video>
                            <video data-orbit-video muted loop playsinline preload="none" aria-hidden="true" disablepictureinpicture></video>
                            @if($card['type'] === 'main' && ! $minimal)<span class="creator-orbit-live-pill"><i aria-hidden="true"></i>{{ $orbitLabels['preview_label'] }}</span>@endif
                        </div>
                        @if($card['type'] === 'bottom')
                            <div class="creator-orbit-bottom-copy"><strong>{{ $card['label'] }}</strong><p>{{ $card['subtitle'] }}</p></div>
                            <span class="creator-orbit-check" aria-hidden="true">✓</span>
                        @else
                            <div class="creator-orbit-card-label"><div><strong>{{ $card['label'] }}</strong>@if($card['subtitle'] && ! $minimal)<p>{{ $card['subtitle'] }}</p>@endif</div>@unless($minimal)<span class="creator-orbit-check" aria-hidden="true">✓</span>@endunless</div>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="creator-orbit-float-anchor creator-orbit-brand-card" data-orbit-anchor="brand" data-orbit-depth="-.4">
                <div class="creator-orbit-float-card"><div class="creator-orbit-brief-icon" aria-hidden="true"></div><strong>{{ $brand['card_title'] ?? 'Your campaign' }}</strong><p>{{ $brand['card_status'] ?? 'Find your people.' }}</p></div>
            </div>
            <div class="creator-orbit-hub" data-orbit-hub aria-hidden="true"><x-brand-mark class="creator-orbit-mark" /></div>
            @unless($minimal)<div class="creator-orbit-match-tag"><i aria-hidden="true"></i>{{ $orbitLabels['tag'] }}</div>@endunless
        </div>
    </div>
    <script type="application/json" data-orbit-clips>{!! json_encode($orbitClips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
</section>
