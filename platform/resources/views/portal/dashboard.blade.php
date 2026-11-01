<x-portal-layout title="Overview">
    <div class="page-heading"><div><span class="eyebrow">YOUR PARTNERSHIP HQ</span><h1>Welcome back, {{ explode(' ',auth()->user()->name)[0] }}<span class="lime">.</span></h1><p>Your campaigns, recorded results, and next steps in one private workspace.</p></div><a href="{{ auth()->user()->role === 'agency' ? '/agency' : '' }}/briefs/create" class="btn btn-primary"><x-ui-icon name="plus" size="15"/> New campaign brief</a></div>

    @if($stats['contains_demo'] && !auth()->user()->is_demo)<div class="privacy-note"><x-ui-icon name="eye"/><span><strong>Sample data included.</strong> {{ $stats['demo_briefs'] }} sample briefs are included in your workspace figures.</span></div>@endif

    <div class="overview-banner"><div><span class="eyebrow"><span class="live-dot"></span> YOUR NEXT BIG MOVE</span><h2>The right creators. A world of possibility.</h2><p>Share your goals. We’ll take care of matching, coordination, and delivery.</p></div><a href="{{ auth()->user()->role === 'agency' ? '/agency' : '' }}/briefs/create" class="btn btn-secondary btn-small">Build your campaign <x-ui-icon name="arrow" size="15"/></a></div>

    <div class="stats-grid">
        @foreach([['Active campaigns',$stats['active'],'briefcase','Matching, proposal, contracts and live'],['Needing review',$stats['review'],'clock','Submitted, review and changes requested'],['Completed',$stats['completed'],'check','Campaigns marked completed'],['EUR brief budget','€'.number_format($stats['budget_eur'],0),'wallet','EUR briefs only; declined excluded']] as [$label,$value,$icon,$note])
            <div class="stat-card"><div class="stat-label">{{ $label }}<x-ui-icon :name="$icon"/></div><div class="stat-value">{{ $value }}</div><div class="stat-footnote">{{ $note }}</div></div>
        @endforeach
    </div>

    @if(count($stats['budgets']) > 1 || (count($stats['budgets']) === 1 && !array_key_exists('EUR',$stats['budgets'])))
        <div class="privacy-note"><x-ui-icon name="wallet"/><span>Planned brief budgets, excluding declined: @foreach($stats['budgets'] as $currency=>$amount)<strong>{{ $currency }} {{ number_format($amount,2) }}</strong>{{ !$loop->last ? ' · ' : '' }}@endforeach. Currencies are kept separate; these figures are not spend or revenue.</span></div>
    @endif

    <section class="panel"><div class="panel-header"><h2><x-ui-icon name="briefcase"/> Your campaigns <span class="tag">{{ $briefs->count() }}</span></h2><a href="{{ auth()->user()->role === 'agency' ? '/agency' : '' }}/campaigns" class="text-link">View all campaigns <x-ui-icon name="right" size="13"/></a></div>@include('portal.partials.campaign-table',['briefs'=>$briefs->take(5)])</section>

    @include('portal.partials.performance-summary',['performance'=>$stats['performance']])

    <div class="dashboard-bottom">
        <section class="panel"><div class="panel-header"><h2><x-ui-icon name="layers"/> Your campaign pipeline</h2><span class="tag">{{ $stats['total_briefs'] }} briefs</span></div>
            <div class="brief-details">@foreach($stats['pipeline'] as $stage)<div class="brief-detail-row"><span>{{ $stage['label'] }}</span><strong>{{ number_format($stage['count']) }}</strong></div>@endforeach</div>
            <p class="journey-help">Each of your briefs is counted once at its current stage.</p>
        </section>
        <section class="panel"><div class="panel-header"><h2><x-ui-icon name="message"/> Your team behind the stream</h2></div><div class="support-body"><p>Creator introductions, agreements, campaign updates, and performance reports are coordinated by your All In Streams team.</p><div class="support-person"><span class="avatar">AI</span><div><strong>All In Streams</strong><small>Campaign operations & partnerships</small></div><x-ui-icon name="shield" class="ml-auto muted" size="22"/></div></div></section>
    </div>
</x-portal-layout>
