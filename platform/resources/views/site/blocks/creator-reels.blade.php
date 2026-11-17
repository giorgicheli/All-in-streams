@if(($data['enabled'] ?? false) && count($data['clips'] ?? []) >= 2)
    @php
        $clips = collect($data['clips'])->values()->map(function (array $clip): array {
            $preset = in_array($clip['sample_clip'] ?? '', ['studio', 'gaming', 'creator', 'setup', 'live', 'community', 'camera', 'portrait', 'focus'], true) ? $clip['sample_clip'] : 'studio';
            return [
                'src' => ! empty($clip['video_source_url']) ? $clip['video_source_url'] : (! empty($clip['video_path']) ? Storage::disk('public')->url($clip['video_path']) : '/videos/creator-reels/'.$preset.'.mp4'),
                'poster' => ! empty($clip['poster_path']) ? Storage::disk('public')->url($clip['poster_path']) : '/images/creator-reels/'.$preset.'.jpg',
                'title' => $clip['title'], 'format' => $clip['format'], 'link' => $clip['link_url'] ?? '',
            ];
        })->all();
    @endphp
    <section class="creator-reel-section" id="creator-content" data-creator-reels data-reel-selected="1" aria-labelledby="creator-reels-title">
        <div class="container creator-reel-layout">
            <div class="creator-reel-header" data-motion-reveal>
                <h2 id="creator-reels-title">{{ $data['title'] }}</h2>
                <div class="creator-reel-navigation"><button type="button" data-reel-previous aria-label="Previous creator previews" disabled><x-ui-icon name="left" size="20"/></button><button type="button" data-reel-next aria-label="Next creator previews"><x-ui-icon name="right" size="20"/></button></div>
            </div>
            <div class="creator-reel-ribbon" data-reel-ribbon tabindex="0" role="region" aria-label="Creator video previews. Swipe or use the arrow keys to explore." data-motion-reveal>
                @foreach($clips as $clip)
                    <article class="creator-reel-panel" data-reel-panel data-reel-position="{{ $loop->iteration }}" aria-label="{{ $clip['title'] }}">
                        <img src="{{ $clip['poster'] }}" alt="" width="640" height="440" loading="lazy" decoding="async">
                        <video data-reel-video data-src="{{ $clip['src'] }}" poster="{{ $clip['poster'] }}" muted loop playsinline preload="none" width="640" height="440" aria-hidden="true" disablepictureinpicture></video>
                        @if($clip['link'])<a class="creator-reel-card-link" data-reel-link href="{{ $clip['link'] }}" aria-label="Open {{ $clip['title'] }}" draggable="false" @if(str_starts_with($clip['link'], 'https://')) target="_blank" rel="noopener noreferrer" @endif><span class="sr-only">Open {{ $clip['title'] }}</span></a>@endif
                    </article>
                @endforeach
            </div>
            <span class="sr-only" data-reel-announcement role="status" aria-live="polite"></span>
        </div>
        <script type="application/json" data-reel-clips>{!! json_encode($clips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    </section>
@endif
