<article class="creator-card">
    <div class="creator-card-top"><span>{{ $creator['code'] }}</span>@if($creator['selection_status'] === 'selected')<span class="tag">Final provider</span>@endif<x-ui-icon name="lock" size="14"/></div>
    <div class="creator-identity"><span class="avatar"><x-ui-icon name="users" size="25"/></span><div><h3>{{ $creator['country'] ?: ucfirst($creator['recipient_type']) }}</h3><p>{{ $creator['platform'] }} · {{ $creator['recipient_type'] === 'agency' ? 'Agency' : ucfirst($creator['creator_type'] ?? 'streamer') }}</p></div></div>
    @if($creator['recipient_type'] === 'agency')
        <div class="creator-metrics"><div><small>Approved roster</small><strong>{{ $creator['roster_count'] }} talent</strong></div><div><small>Delivery</small><strong>Agency package</strong></div></div>
    @else
        <div class="creator-metrics"><div><small>Followers</small><strong>{{ Number::abbreviate($creator['followers'],precision:1) }}</strong></div><div><small>Average viewers</small><strong>{{ Number::abbreviate($creator['average_viewers'],precision:1) }}</strong></div></div>
    @endif
    <p>{{ $creator['deliverables'] }}</p>
    <p class="mt-3"><x-ui-icon name="shield" class="inline mr-1" size="12"/> Identity and contacts are private to All In Streams.</p>
    @if(in_array($creator['brief_status'],['proposal','contracts']))
        <form method="POST" action="{{ route(auth()->user()->role === 'agency' ? 'agency.proposals.request' : 'proposals.request', $creator['id']) }}" class="mt-5">@csrf<label for="preference-{{ $creator['id'] }}" class="sr-only">Note for All In Streams</label><textarea id="preference-{{ $creator['id'] }}" name="notes" rows="2" maxlength="3000" placeholder="Your preference or a question (optional)"></textarea><button class="btn btn-primary btn-small btn-block mt-3">Request this {{ $creator['recipient_type'] }} <x-ui-icon name="right" size="14"/></button></form>
    @endif
</article>