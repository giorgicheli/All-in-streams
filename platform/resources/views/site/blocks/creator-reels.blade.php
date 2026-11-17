@if(($data['enabled'] ?? false) && count($data['clips'] ?? []) >= 2)
    @php
        $clips = collect($data['clips'])->values()->map(function (array $clip, int $index): array {
            $preset = in_array($clip['sample_clip'] ?? '', ['studio', 'gaming', 'creator', 'setup', 'live', 'community', 'camera', 'portrait', 'focus'], true) ? $clip['sample_clip'] : 'studio';
            return $clip + [
                'video_url' => ! empty($clip['video_source_url']) ? $clip['video_source_url'] : (! empty($clip['video_path']) ? Storage::disk('public')->url($clip['video_path']) : '/videos/creator-reels/'.$preset.'.mp4'),
                'poster_url' => ! empty($clip['poster_path']) ? Storage::disk('public')->url($clip['poster_path']) : '/images/creator-reels/'.$preset.'.jpg',
                'position' => $index + 1,
            ];
        })->all();
        if (count($clips) === 2) {
            $rows = [[$clips[0]], [], [$clips[1]]];
        } else {
            $topCount = (int) ceil(count($clips) / 3);
            $middleCount = (int) ceil((count($clips) - $topCount) / 2);
            $rows = [array_slice($clips, 0, $topCount), array_slice($clips, $topCount, $middleCount), array_slice($clips, $topCount + $middleCount)];
        }
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
                    @php
                        $visibleClips = $row;
                        if ($rowIndex !== 1 && $row) {
                            while (count($row) < 6) foreach ($visibleClips as $clip) $row[] = $clip + ['is_repeat' => true];
                        }
                    @endphp
                    <div class="creator-reel-row{{ $rowIndex === 1 ? ' creator-reel-row--still' : '' }}" data-reel-row="{{ $rowIndex === 1 ? 'still' : ($rowIndex === 0 ? 'forward' : 'reverse') }}">
                        <div class="creator-reel-track">
                            @foreach($rowIndex === 1 ? [false] : [false, true] as $duplicate)
                                <div class="creator-reel-group" @if($duplicate) aria-hidden="true" data-reel-clone @endif>
                                    @foreach($row as $clip)
                                        <article class="creator-reel-card" data-reel-position="{{ $clip['position'] }}" @if($clip['is_repeat'] ?? false) aria-hidden="true" data-reel-clone @endif>
                                            <video data-reel-video data-src="{{ $clip['video_url'] }}" poster="{{ $clip['poster_url'] }}" muted loop playsinline preload="none" width="640" height="360" aria-hidden="true" disablepictureinpicture></video>
                                            <div class="creator-reel-card-caption"><span class="creator-reel-card-mark" aria-hidden="true"><x-ui-icon name="spark" size="12"/></span><div><h3>{{ $clip['title'] }}</h3><span>{{ $clip['format'] }}</span></div></div>
                                            @if(! empty($clip['link_url']))
                                                <a class="creator-reel-card-link" data-reel-link href="{{ $clip['link_url'] }}" aria-label="Open {{ $clip['title'] }}" @if($duplicate || ($clip['is_repeat'] ?? false)) tabindex="-1" @endif @if(str_starts_with($clip['link_url'], 'https://')) target="_blank" rel="noopener noreferrer" @endif><span class="sr-only">Open {{ $clip['title'] }}</span><x-ui-icon name="arrow" size="15" aria-hidden="true"/></a>
                                            @endif
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
