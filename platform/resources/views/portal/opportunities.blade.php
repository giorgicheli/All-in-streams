<x-portal-layout title="Opportunities">
    <div class="page-heading">
        <div>
            <span class="eyebrow">GOOD CONTENT. GREAT CONNECTIONS.</span>
            <h1>Your next good match<span class="lime">.</span></h1>
            <p>Campaign invitations selected for your content, community, and ambition.</p>
        </div>
        @if($profile && !$profile->trashed())<a href="{{ route('creator.apply') }}" class="btn btn-secondary"><x-ui-icon name="user" size="15"/> Your creator profile</a>@endif
    </div>

    @if($creatorStats['contains_demo'] && !auth()->user()->is_demo)
        <div class="privacy-note"><x-ui-icon name="eye"/><span><strong>Sample data included.</strong> This workspace contains sample campaign invitations.</span></div>
    @endif

    @if($profile)
        <div class="stats-grid">
            @foreach([['Profile followers',number_format($creatorStats['followers']),'users','Self-reported profile figure'],['Average live viewers',number_format($creatorStats['average_viewers']),'eye','Self-reported profile figure'],['Awaiting your response',$creatorStats['pending'],'mail','Your pending invitations'],['Accepted offers',$creatorStats['interested'],'star','Terms accepted, including offer history']] as [$label,$value,$icon,$note])
                <div class="stat-card"><div class="stat-label">{{ $label }}<x-ui-icon :name="$icon"/></div><div class="stat-value">{{ $value }}</div><div class="stat-footnote">{{ $note }}</div></div>
            @endforeach
        </div>
        <p class="prose-note mb-6">Audience figures come from your creator profile. They are separate from campaign performance reports.</p>
    @endif

    @if($errors->any())
        <div class="alert error" role="alert"><x-ui-icon name="message"/><span>{{ $errors->first() }}</span></div>
    @endif

    @if(!$profile)
        <section class="panel empty-state"><x-ui-icon name="spark"/><h3>Share your creator profile.</h3><p>Add your channels, audience, availability and rates for staff review.</p><a href="{{ route('creator.apply') }}" class="btn btn-primary">Complete your profile</a></section>
    @else
        @if($profile->trashed())
            <div class="privacy-note"><x-ui-icon name="shield"/><span>Your creator profile was archived by staff. Past offers remain available. Contact All In Streams to restore your profile.</span></div>
        @elseif($profile->status !== 'approved')
            <div class="privacy-note"><x-ui-icon name="clock"/><span>Your profile needs staff approval before you can accept invitations. Keep your channels and audience details up to date.</span></div>
        @else
            <div class="privacy-note"><x-ui-icon name="shield"/><span>Review your own terms and respond to All In Streams. Business identity, contacts and total campaign budgets remain private.</span></div>
        @endif
        <div class="invite-list">
            @forelse($invitations as $offer)
                @include('portal.partials.provider-offer', ['responseRoute' => 'creator.respond'])
            @empty
                <section class="panel empty-state"><x-ui-icon name="star"/><h3>Your next good match is on its way.</h3><p>Suitable invitations will appear here after staff reviews your profile.</p></section>
            @endforelse
        </div>
    @endif
</x-portal-layout>