@props(['clips' => [], 'data' => []])
@php
    $intro = $data + [
        'accessible_label' => 'All In Streams introduction', 'eyebrow' => 'WHERE BRANDS MEET CREATORS',
        'skip_label' => 'Skip intro', 'progress_label' => 'Intro progress',
        'headlines' => [['line_1' => 'Great', 'line_2' => 'brands.'], ['line_1' => 'Real', 'line_2' => 'creators.'], ['line_1' => 'Better', 'line_2' => 'together.']],
        'card_labels' => [['label' => 'Made for gaming'], ['label' => 'A creator perspective'], ['label' => 'The live moment'], ['label' => 'Community in motion'], ['label' => 'Shared passions'], ['label' => 'Made for connection']],
    ];
    $introClips = [];
    $introSources = [];
    foreach ($clips as $clip) {
        $media = \App\Support\CreatorPreviewClips::media($clip);
        $src = $media['src'];
        if (isset($introSources[$src])) continue;
        $introSources[$src] = true;
        $introClips[] = $media;
    }
    if (! $introClips) {
        foreach (array_slice(\App\Support\CreatorPreviewClips::defaults(), 0, 6) as $clip) $introClips[] = \App\Support\CreatorPreviewClips::media($clip);
    }
@endphp
<dialog class="site-intro" data-site-intro data-state="waiting" aria-label="{{ $intro['accessible_label'] }}">
    <div class="site-intro-top">
        <button type="button" class="site-intro-skip" data-site-intro-skip data-site-intro-focus>{{ $intro['skip_label'] }}<x-ui-icon name="arrow" size="16"/></button>
    </div>
    <div class="site-intro-deck" aria-hidden="true">
        @for($index = 0; $index < 6; $index++)
            @php $clip = $introClips[$index % count($introClips)]; @endphp
            <figure class="site-intro-card">
                <div class="site-intro-card-inner">
                    <img data-site-intro-poster alt="" width="640" height="405" decoding="async">
                    <video data-site-intro-video data-clip-index="{{ $index % count($introClips) }}" muted loop playsinline preload="none" disablepictureinpicture aria-hidden="true"></video>
                    <figcaption><i aria-hidden="true">✧</i>{{ $intro['card_labels'][$index % max(1, count($intro['card_labels']))]['label'] ?? '' }}</figcaption>
                </div>
            </figure>
        @endfor
    </div>
    <div class="site-intro-copy">
        <p class="site-intro-eyebrow" aria-hidden="true">{{ $intro['eyebrow'] }}</p>
        <div class="site-intro-headings" aria-hidden="true">
            @foreach($intro['headlines'] as $headline)
                <div class="site-intro-heading" data-site-intro-heading><span class="site-intro-line"><span class="site-intro-word">{{ $headline['line_1'] }}</span></span><span class="site-intro-line"><span class="site-intro-word">{{ $headline['line_2'] }}</span></span></div>
            @endforeach
        </div>
        <div class="site-intro-meter">
            <div class="site-intro-progress" data-site-intro-progress role="progressbar" aria-label="{{ $intro['progress_label'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="site-intro-progress-fill" data-site-intro-fill></span></div>
            <span class="site-intro-count" aria-hidden="true"><span data-site-intro-count>0</span><small>%</small></span>
        </div>
    </div>
    <script type="application/json" data-site-intro-clips>{!! json_encode($introClips, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
</dialog>
