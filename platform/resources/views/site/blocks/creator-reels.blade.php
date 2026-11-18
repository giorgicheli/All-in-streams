@if(($data['enabled'] ?? false) && count($data['clips'] ?? []) >= 2)
    @php
        $clips = collect($data['clips'])->values()->map(function (array $clip, int $index): array {
            $preset = in_array($clip['sample_clip'] ?? '', ['studio', 'gaming', 'creator', 'setup', 'live', 'community', 'camera', 'portrait', 'focus'], true) ? $clip['sample_clip'] : 'studio';
            return [
                'src' => ! empty($clip['video_source_url']) ? $clip['video_source_url'] : (! empty($clip['video_path']) ? Storage::disk('public')->url($clip['video_path']) : '/videos/creator-reels/'.$preset.'.mp4'),
                'poster' => ! empty($clip['poster_path']) ? Storage::disk('public')->url($clip['poster_path']) : '/images/creator-reels/'.$preset.'.jpg',
                'title' => $clip['title'], 'format' => $clip['format'], 'link' => $clip['link_url'] ?? '', 'position' => $index + 1,
            ];
        })->all();
        $split = (int) ceil(count($clips) / 2);
        $rows = [array_slice($clips, 0, $split), array_slice($clips, $split)];
    @endphp
    <section class="creator-reel-section" id="creator-content" data-creator-reels aria-labelledby="creator-reels-title">
        <div class="container creator-reel-layout">
            <h2 id="creator-reels-title" data-motion-reveal>{{ $data['title'] }}</h2>
            <div class="creator-reel-rows" data-motion-reveal>
                @foreach($rows as $row)
                    <div class="creator-reel-ribbon" data-reel-ribbon data-reel-row="{{ $loop->first ? 'forward' : 'reverse' }}" tabindex="0" role="region" aria-label="{{ $loop->first ? 'Creator previews' : 'More creator previews' }}. Swipe or use the arrow keys to explore.">
                        @foreach(count($row) >= 3 ? ['before', 'original', 'after'] : ['original'] as $copy)
                            @foreach($row as $clip)
                                <article class="creator-reel-card" data-reel-card @if($copy === 'original') data-reel-panel data-reel-position="{{ $clip['position'] }}" aria-label="{{ $clip['title'] }}" @else data-reel-clone aria-hidden="true" @endif>
                                    <img src="{{ $clip['poster'] }}" alt="" width="640" height="360" loading="lazy" decoding="async">
                                    <video data-reel-video data-src="{{ $clip['src'] }}" poster="{{ $clip['poster'] }}" muted loop playsinline preload="none" width="640" height="360" aria-hidden="true" disablepictureinpicture></video>
                                    @if($clip['link'])<a class="creator-reel-card-link" data-reel-link href="{{ $clip['link'] }}" aria-label="Open {{ $clip['title'] }}" draggable="false" @if($copy !== 'original') tabindex="-1" @endif @if(str_starts_with($clip['link'], 'https://')) target="_blank" rel="noopener noreferrer" @endif><span class="sr-only">Open {{ $clip['title'] }}</span></a>@endif
                                </article>
                            @endforeach
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <script type="application/json" data-reel-clips>{!! json_encode($clips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    </section>
@endif
