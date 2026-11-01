<x-portal-layout title="Payments">
    <div class="page-heading"><div><span class="eyebrow">YOUR AGREED COMPENSATION</span><h1>Payment milestones<span class="lime">.</span></h1><p>Track the milestones recorded by All In Streams for your own work.</p></div></div>
    <div class="privacy-note"><x-ui-icon name="wallet"/><span>This is a manual payment ledger. Pending means recorded, approved means authorized, and paid means our team recorded an external payment. The portal does not transfer money. CPA amounts appear only when our team records validated results.</span></div>
    <div class="invite-list">
        @forelse($milestones as $milestone)
            <section class="panel invite-card">
                <div class="invite-head"><div><span class="eyebrow">{{ $milestone['opportunity']['code'] }}</span><h2>{{ $milestone['label'] }}</h2><p>{{ str($milestone['kind'])->replace('_', ' ')->title() }}</p></div><x-status :value="$milestone['status']"/></div>
                <strong class="compensation">{{ $milestone['currency'] }} {{ number_format((float) $milestone['amount'], 2) }}</strong>
                @if($milestone['paid_at'])<p class="prose-note mt-4">Payment recorded {{ $milestone['paid_at'] }}</p>@endif
                @if($milestone['reference'])<p class="prose-note mt-3">Your payment reference: {{ $milestone['reference'] }}</p>@endif
            </section>
        @empty
            <section class="panel empty-state"><x-ui-icon name="wallet"/><h3>No payment milestones yet.</h3><p>Your own fixed-fee and validated CPA milestones will appear when All In Streams records them.</p></section>
        @endforelse
    </div>
</x-portal-layout>
