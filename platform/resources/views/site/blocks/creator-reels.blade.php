@if(($data['enabled'] ?? false) && count($data['clips'] ?? []) >= 2)
    @php
        $clips = collect($data['clips'])->map(function (array $clip): array {
            $preset = in_array($clip['sample_clip'] ?? '', ['studio', 'gaming', 'creator', 'setup', 'live', 'community'], true) ? $clip['sample_clip'] : 'studio';
            return $clip + [
                'video_url' => ! empty($clip['video_path']) ? Storage::disk('public')->url($clip['video_path']) : '/videos/creator-reels/'.$preset.'.mp4',
                'poster_url' => ! empty($clip['poster_path']) ? Storage::disk('public')->url($clip['poster_path']) : '/images/creator-reels/'.$preset.'.jpg',
            ];
        })->all();
        $loopClips = $clips;
        while (count($loopClips) < 6) {
            foreach ($clips as $clip) $loopClips[] = $clip + ['is_repeat' => true];
        }
        $rows = [$loopClips, array_slice($clips, 0, 3), array_reverse($loopClips)];
    @endphp
    <section class="creator-reel-section" id="creator-content" data-creator-reels aria-labelledby="creator-reels-title">
        <div class="container creator-reel-layout">
            <div class="creator-reel-copy" data-motion-reveal>
                <span class="creator-reel-eyebrow">{{ $data['eyebrow'] }}</span>
                <h2 id="creator-reels-title">{{ $data['title'] }}</h2>
                <p>{{ $data['description'] }}</p>
                <div class="creator-reel-actions">
                    <a class="btn creator-reel-link" href="{{ $data['url'] }}">{{ $data['label'] }}<x-ui-icon name="arrow" size="16"/></a>
                    <button class="creator-reel-control" type="button" data-reel-control hidden aria-pressed="false" aria-label="Pause video previews">
                        <svg viewBox="0 0 18 18" width="16" height="16" aria-hidden="true"><path data-reel-pause d="M6 4v10M12 4v10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path data-reel-play d="m6 3 9 6-9 6Z" fill="currentColor" hidden/></svg>
                        <span data-reel-control-label>Pause previews</span>
                    </button>
                </div>
            </div>
            <div class="creator-reel-wall" data-reel-wall>
                @foreach($rows as $rowIndex => $row)
                    <div class="creator-reel-row{{ $rowIndex === 1 ? ' creator-reel-row--still' : '' }}" data-reel-row="{{ $rowIndex === 1 ? 'still' : ($rowIndex === 0 ? 'forward' : 'reverse') }}">
                        <div class="creator-reel-track">
                            @foreach($rowIndex === 1 ? [false] : [false, true] as $duplicate)
                                <div class="creator-reel-group" @if($duplicate) aria-hidden="true" inert @endif>
                                    @foreach($row as $clip)
                                        <article class="creator-reel-card" @if($clip['is_repeat'] ?? false) aria-hidden="true" @endif>
                                            <video data-reel-video data-src="{{ $clip['video_url'] }}" poster="{{ $clip['poster_url'] }}" muted loop playsinline preload="none" width="640" height="360" aria-hidden="true" disablepictureinpicture></video>
                                            <div class="creator-reel-card-caption"><span class="creator-reel-card-mark" aria-hidden="true"><x-ui-icon name="spark" size="12"/></span><div><h3>{{ $clip['title'] }}</h3><span>{{ $clip['format'] }}</span></div></div>
                                        </article>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
