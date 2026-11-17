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
        $first = $clips[0];
    @endphp
    <section class="creator-reel-section" id="creator-content" data-creator-reels data-reel-selected="1" aria-labelledby="creator-reels-title">
        <div class="container creator-reel-layout">
            <div class="creator-reel-header" data-motion-reveal>
                <h2 id="creator-reels-title">{{ $data['title'] }}</h2>
                <div class="creator-reel-introduction"><p>{{ $data['description'] }}</p><a class="btn creator-reel-link" href="{{ $data['url'] }}">{{ $data['label'] }}<x-ui-icon name="arrow" size="16"/></a></div>
            </div>
            <div class="creator-reel-room" data-reel-room data-motion-reveal>
                <article class="creator-reel-feature" data-reel-feature aria-labelledby="creator-reel-feature-title">
                    <div class="creator-reel-screen" data-reel-screen aria-hidden="true">
                        @for($slot = 0; $slot < 2; $slot++)
                            <div class="creator-reel-layer{{ $slot === 0 ? ' is-active' : '' }}" data-reel-layer>
                                <img data-reel-poster src="{{ $first['poster'] }}" alt="" width="1280" height="720" loading="lazy" decoding="async">
                                <video data-reel-video muted loop playsinline preload="none" width="1280" height="720" aria-hidden="true" disablepictureinpicture></video>
                            </div>
                        @endfor
                    </div>
                    <div class="creator-reel-feature-footer">
                        <div class="creator-reel-caption"><span data-reel-format>{{ $first['format'] }}</span><h3 id="creator-reel-feature-title" data-reel-title>{{ $first['title'] }}</h3></div>
                        <div class="creator-reel-feature-actions">
                            <a class="creator-reel-watch text-link" data-reel-link @if($first['link']) href="{{ $first['link'] }}" @else hidden @endif aria-label="Open {{ $first['title'] }}" @if(str_starts_with($first['link'], 'https://')) target="_blank" rel="noopener noreferrer" @endif><span>Watch clip</span><x-ui-icon name="arrow" size="16"/></a>
                            <div class="creator-reel-navigation"><span data-reel-counter aria-hidden="true">1 / {{ count($clips) }}</span><button type="button" data-reel-previous aria-label="Previous creator preview"><x-ui-icon name="left" size="18"/></button><button type="button" data-reel-next aria-label="Next creator preview"><x-ui-icon name="right" size="18"/></button></div>
                        </div>
                    </div>
                </article>
                <aside class="creator-reel-collection" aria-label="Creator preview collection">
                    <div class="creator-reel-collection-heading"><span>Browse clips</span><span>{{ count($clips) }} clips</span></div>
                    <div class="creator-reel-playlist" data-reel-playlist role="group" aria-label="Choose a creator preview">
                        @foreach($clips as $clip)
                            <button type="button" class="creator-reel-choice{{ $loop->first ? ' is-selected' : '' }}" data-reel-select="{{ $loop->index }}" data-reel-position="{{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-label="Preview {{ $clip['title'] }}">
                                <span class="creator-reel-choice-image"><img src="{{ $clip['poster'] }}" alt="" width="160" height="100" loading="lazy" decoding="async"><span class="creator-reel-choice-mark" aria-hidden="true"><x-ui-icon name="right" size="12"/></span></span>
                                <span class="creator-reel-choice-copy"><strong>{{ $clip['title'] }}</strong><span>{{ $clip['format'] }}</span></span>
                            </button>
                        @endforeach
                    </div>
                </aside>
                <span class="sr-only" data-reel-announcement role="status" aria-live="polite"></span>
            </div>
        </div>
        <script type="application/json" data-reel-clips>{!! json_encode($clips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    </section>
@endif
