@php
    $showNetworkVideos = ($reels['enabled'] ?? false) && count($reels['clips'] ?? []) >= 2;
    $networkStats = is_array($stats ?? null) && ($stats['enabled'] ?? true) ? ($stats['cards'] ?? []) : [];
    $networkClips = $showNetworkVideos ? collect($reels['clips'])->take(12)->values()->map(function (array $clip, int $index): array {
        $preset = in_array($clip['sample_clip'] ?? '', ['studio', 'gaming', 'creator', 'setup', 'live', 'community', 'camera', 'portrait', 'focus'], true) ? $clip['sample_clip'] : 'studio';
        return [
            'src' => ! empty($clip['video_source_url']) ? $clip['video_source_url'] : (! empty($clip['video_path']) ? Storage::disk('public')->url($clip['video_path']) : '/videos/creator-reels/'.$preset.'.mp4'),
            'poster' => ! empty($clip['poster_path']) ? Storage::disk('public')->url($clip['poster_path']) : '/images/creator-reels/'.$preset.'.jpg',
            'title' => $clip['title'] ?? 'Creator preview', 'link' => $clip['link_url'] ?? '', 'position' => $index + 1,
        ];
    })->all() : [];
    $networkSlides = array_chunk($networkClips, 3);
    $networkDominantSlots = [0, 1, 2];
    shuffle($networkDominantSlots);
    $networkClipPrefix = 'network-clip-'.Illuminate\Support\Str::uuid();
    $networkClipFallbacks = [
        'first' => 'M0 .04 Q0 0 .04 0 H.96 Q1 0 .997 .04 L.923 .96 Q.92 1 .88 1 H.04 Q0 1 0 .96 Z',
        'middle' => 'M.173 .04 Q.18 0 .22 0 H.96 Q1 0 .993 .04 L.827 .96 Q.82 1 .78 1 H.04 Q0 1 .007 .96 Z',
        'last' => 'M.173 .04 Q.18 0 .22 0 H.96 Q1 0 1 .04 V.96 Q1 1 .96 1 H.04 Q0 1 .007 .96 Z',
        'single' => 'M.04 0 H.96 Q1 0 1 .04 V.96 Q1 1 .96 1 H.04 Q0 1 0 .96 V.04 Q0 0 .04 0 Z',
    ];
@endphp
@if($showNetworkVideos || count($networkStats))
    <section class="network-showcase" id="network" data-network-showcase data-network-paused="false" aria-labelledby="network-showcase-title">
        <div class="container">
            <div class="network-showcase-card" data-motion-reveal>
                <div class="network-showcase-header">
                    <h2 id="network-showcase-title">{{ $reels['title'] ?? 'Our creator network' }}</h2>
                    @if($showNetworkVideos)
                        <div class="network-showcase-controls" aria-label="Creator video navigation">
                            <button type="button" class="network-showcase-nav" data-network-prev aria-label="Previous creator videos" @disabled(count($networkSlides) < 2)><svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button>
                            <button type="button" class="network-showcase-nav" data-network-next aria-label="Next creator videos" @disabled(count($networkSlides) < 2)><svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg></button>
                        </div>
                    @endif
                </div>
                @if($showNetworkVideos)
                    <div class="network-showcase-media" id="creator-content">
                        <div class="network-showcase-track" data-network-track tabindex="0" role="group" aria-label="Creator video previews. Swipe or use the arrow keys to browse.">
                            @foreach($networkSlides as $slideIndex => $slide)
                                @php
                                    $networkPanelWeights = [100];
                                    if (count($slide) === 3) {
                                        $networkWideWeight = random_int(46, 52);
                                        $networkSmallWeight = random_int(22, 25);
                                        $networkPanelWeights = [$networkSmallWeight, 100 - $networkWideWeight - $networkSmallWeight];
                                        shuffle($networkPanelWeights);
                                        array_splice($networkPanelWeights, $networkDominantSlots[$slideIndex % 3], 0, [$networkWideWeight]);
                                    } elseif (count($slide) === 2) {
                                        $networkWideWeight = random_int(56, 64);
                                        $networkPanelWeights = [$networkWideWeight, 100 - $networkWideWeight];
                                        shuffle($networkPanelWeights);
                                    }
                                @endphp
                                <div class="network-showcase-slide network-showcase-slide--{{ count($slide) }}" data-network-slide="{{ $slideIndex }}" role="group" aria-roledescription="slide" aria-label="{{ $slideIndex + 1 }} of {{ count($networkSlides) }}">
                                    @foreach($slide as $clip)
                                        @php
                                            $networkPanelShape = count($slide) === 1 ? 'single' : ($loop->first ? 'first' : ($loop->last ? 'last' : 'middle'));
                                            $networkPanelClipId = $networkClipPrefix.'-'.$slideIndex.'-'.$loop->index;
                                        @endphp
                                        <div class="network-showcase-panel" data-network-panel data-network-shape="{{ $networkPanelShape }}" data-network-index="{{ $clip['position'] - 1 }}" data-network-position="{{ $clip['position'] }}" style="--network-panel-clip:url(#{{ $networkPanelClipId }});--network-panel-weight:{{ $networkPanelWeights[$loop->index] }}" role="group" aria-label="{{ $clip['title'] }}">
                                            <svg class="network-showcase-clip-definitions" width="0" height="0" aria-hidden="true" focusable="false"><defs><clipPath id="{{ $networkPanelClipId }}" clipPathUnits="objectBoundingBox"><path data-network-clip-path d="{{ $networkClipFallbacks[$networkPanelShape] }}"/></clipPath></defs></svg>
                                            <img data-network-poster src="{{ $clip['poster'] }}" alt="" width="640" height="360" loading="lazy" decoding="async" draggable="false">
                                            @for($buffer = 0; $buffer < 2; $buffer++)
                                                <video data-network-video poster="{{ $clip['poster'] }}" muted loop playsinline preload="none" width="640" height="360" aria-hidden="true" disablepictureinpicture></video>
                                            @endfor
                                            <a class="network-showcase-link" data-network-link href="{{ $clip['link'] ?: '#' }}" aria-label="Open {{ $clip['title'] }}" draggable="false" @unless($clip['link']) hidden tabindex="-1" @endunless @if(str_starts_with($clip['link'], 'https://')) target="_blank" rel="noopener noreferrer" @endif><span class="sr-only">Open {{ $clip['title'] }}</span></a>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="network-showcase-toggle" data-network-toggle aria-label="Pause video previews" aria-pressed="false">
                            <svg class="icon network-showcase-pause" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 4h3v12H5zm7 0h3v12h-3z"/></svg>
                            <svg class="icon network-showcase-play" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m6 3 10 7-10 7z"/></svg>
                        </button>
                        <span class="sr-only" data-network-status role="status" aria-live="polite"></span>
                    </div>
                    <script type="application/json" data-network-clips>{!! json_encode($networkClips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
                @endif
                @if(count($networkStats))
                    <div class="network-showcase-stats">
                        @foreach($networkStats as $card)
                            <article class="network-stat-card network-showcase-stat">
                                <p class="network-stat-value"><span class="sr-only">{{ number_format($card['count']) }}{{ $card['suffix'] }}</span><span aria-hidden="true"><span data-network-count="{{ $card['count'] }}">{{ number_format($card['count']) }}</span><span class="network-stat-suffix">{{ $card['suffix'] }}</span></span></p>
                                <h3>{{ $card['label'] }}</h3>
                            </article>
                        @endforeach
                    </div>
                @endif
                @if(isset($geo))
                    @include('site.blocks.geo_strip', ['data' => $geo, 'embedded' => true])
                @endif
            </div>
        </div>
    </section>
@endif
