@php
    $networkProfiles = $creatorShowcases ?? \App\Models\CreatorShowcase::published()->orderBy('sort_order')->orderBy('id')->get()->map->publicData();
    $networkRegions = array_keys(\App\Models\CreatorShowcase::COUNTRIES_BY_REGION);
    $networkGroups = collect($networkProfiles)->groupBy('region')->map(fn ($profiles) => $profiles->take(3));
@endphp
<section class="creator-network section" id="creators" data-creator-network data-request-url="{{ $data['request_url'] }}" aria-labelledby="creator-network-title">
    <div class="container">
        <div class="section-heading creator-network-heading">
            <div><span class="eyebrow">{{ $data['eyebrow'] }}</span><h2 id="creator-network-title">{{ $data['title_line_1'] }}<br><em>{{ $data['title_line_2'] }}</em></h2></div>
            <p>{{ $data['description'] }}</p>
        </div>
        <div class="creator-network-toolbar">
            <div class="creator-region-tabs" role="tablist" aria-label="Creator audience region">
                @foreach($networkRegions as $region)
                    <button type="button" class="creator-region-tab {{ $loop->first ? 'is-selected' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="creator-region-{{ $loop->index }}" id="creator-tab-{{ $loop->index }}" data-creator-region="{{ $region }}" tabindex="{{ $loop->first ? '0' : '-1' }}">{{ $region }}</button>
                @endforeach
            </div>
            <button type="button" class="creator-shortlist-trigger" data-shortlist-open aria-haspopup="dialog">Your shortlist <span data-shortlist-count>(0)</span><x-ui-icon name="arrow" size="14"/></button>
        </div>
        @foreach($networkRegions as $region)
            <div class="creator-region-panel" id="creator-region-{{ $loop->index }}" role="tabpanel" aria-labelledby="creator-tab-{{ $loop->index }}" data-creator-panel="{{ $region }}" @unless($loop->first) hidden @endunless>
                <div class="creator-network-grid" data-motion-group>
                    @forelse($networkGroups->get($region, collect()) as $profile)
                        <article class="creator-public-card" data-public-profile="{{ $profile['public_id'] }}" data-profile-caption="{{ $profile['caption'] }}" data-profile-market="{{ $profile['country'] }}" data-profile-type="{{ $profile['type_label'] }}">
                            <div class="creator-public-image">
                                <img src="{{ $profile['image_url'] }}" alt="{{ $profile['image_alt'] }}" width="660" height="450" loading="lazy">
                                <span class="creator-market-label">{{ $profile['country'] }} &middot; {{ $profile['type_label'] }}</span>
                                <div class="creator-public-image-copy">@if($profile['is_demo'])<span class="creator-demo-label">Demo profile</span>@endif<h3>{{ $profile['caption'] }}</h3></div>
                            </div>
                            <div class="creator-public-details">
                                <div class="creator-public-platforms"><span>{{ implode(' / ', $profile['platforms']) }}</span><span>{{ $profile['language'] }}</span></div>
                                <dl class="creator-public-metrics"><div><dd>{{ number_format($profile['followers']) }}</dd><dt>Followers</dt></div><div><dd>{{ number_format($profile['average_views']) }}</dd><dt>Avg. content views</dt></div></dl>
                                <div class="creator-protected-details"><x-ui-icon name="lock" size="14"/><span class="sr-only">Name and contacts are protected.</span><span class="creator-identity-blur" aria-hidden="true">Protected identity<br>Contact via All In Streams</span></div>
                                <button type="button" class="creator-add-shortlist" data-add-shortlist aria-pressed="false"><span>Add to shortlist</span><x-ui-icon name="plus" size="15"/></button>
                            </div>
                        </article>
                    @empty
                        <div class="creator-network-empty"><x-ui-icon name="globe" size="28"/><p>Our team can find creators for this region.</p></div>
                    @endforelse
                </div>
                <div class="creator-region-request"><div><h3>Looking for more voices in {{ $region }}?</h3><p>Share your brief to explore creators matched to your audience and budget.</p></div><a class="btn btn-secondary" data-request-profiles href="{{ $data['request_url'] }}">{{ $data['request_label'] }} <x-ui-icon name="arrow" size="15"/></a></div>
            </div>
        @endforeach
        <span class="sr-only" role="status" data-creator-network-status></span>
    </div>
    <dialog class="creator-shortlist-dialog" data-shortlist-dialog aria-labelledby="creator-shortlist-title" data-lenis-prevent>
        <div class="creator-shortlist-header"><h2 id="creator-shortlist-title">Your shortlist</h2><button type="button" class="creator-shortlist-close" data-shortlist-close aria-label="Close shortlist"><x-ui-icon name="close" size="23"/></button></div>
        <p class="creator-shortlist-intro">Send your selection to All In Streams with your brief.</p>
        <ul class="creator-shortlist-list" data-shortlist-list></ul>
        <p class="creator-shortlist-empty" data-shortlist-empty>No creators selected yet.</p>
        <a class="btn btn-primary" data-shortlist-request href="{{ $data['request_url'] }}">Send my shortlist <x-ui-icon name="arrow" size="16"/></a>
    </dialog>
</section>