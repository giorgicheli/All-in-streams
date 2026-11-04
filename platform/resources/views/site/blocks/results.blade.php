@php $results = $publicResults ?? \App\Models\PublicCampaignResult::published()->latest('completed_on')->get(\App\Models\PublicCampaignResult::PUBLIC_COLUMNS); @endphp
<section class="section industries-section line-top" id="results" data-results-gallery><div class="container">
    @include('site.section-heading')
    <div class="gallery-tabs" role="tablist" aria-label="Campaign region">@foreach($data['regions'] as $region)<button type="button" data-result-filter="{{ $loop->first?'*':$region['label'] }}" class="gallery-tab {{ $loop->first?'selected':'' }}" role="tab" aria-controls="published-results" aria-selected="{{ $loop->first?'true':'false' }}">{{ $region['label'] }}</button>@endforeach</div>
    <div id="published-results" role="tabpanel">
        <div class="results-grid" data-motion-group>
            @foreach($results as $result)
                <article class="result-card" data-result-region="{{ $result->region }}">
                    <div class="result-top"><span class="result-market"><x-country-flag :name="$result->country" :decorative="true"/>{{ $result->country }}</span><span class="result-platform">{{ $result->platform }}</span></div>
                    <div class="result-badge {{ $result->is_demo?'is-sample':'' }}"><x-ui-icon :name="$result->is_demo?'layers':'check'" size="13"/>{{ $result->is_demo?'Demo · simulated results':'Staff-reviewed results' }}</div>
                    <h3>{{ $result->country }}<span>On {{ $result->platform }}.</span></h3>
                    <p class="result-delivery">@if($result->streams){{ number_format($result->streams) }} {{ $result->streams===1?'stream':'streams' }}@endif@if($result->streams && $result->posts)<span aria-hidden="true"> · </span>@endif@if($result->posts){{ number_format($result->posts) }} {{ $result->posts===1?'post':'posts' }}@endif<span> completed</span></p>
                    <dl class="result-metrics"><div><dt>Views</dt><dd>{{ number_format($result->views) }}</dd></div><div><dt>Link clicks</dt><dd>{{ number_format($result->clicks) }}</dd></div><div><dt>Conversions</dt><dd>{{ number_format($result->conversions) }}</dd></div></dl>
                    <div class="result-completed"><x-ui-icon name="check" size="14"/><span>Completed {{ $result->completed_on->format('d M Y') }}</span><x-ui-icon name="shield" size="14"/></div>
                </article>
            @endforeach
        </div>
        <div class="gallery-empty" data-results-empty @if($results->isNotEmpty()) hidden @endif><x-ui-icon name="globe"/><h3>{{ $data['empty_title'] }}</h3><p><span data-results-region-label>{{ $data['regions'][0]['label'] }}</span> · {{ $data['empty_description'] }}</p></div>
    </div>
    <span class="sr-only" role="status" data-results-status>{{ $results->count() }} campaign results</span>
    <div class="gallery-privacy"><x-ui-icon name="shield" size="13"/> {{ $data['privacy_note'] }}</div>
</div></section>
