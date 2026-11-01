<x-portal-layout title="Opportunities">
    <div class="page-heading">
        <div>
            <span class="eyebrow">GOOD CONTENT. GREAT CONNECTIONS.</span>
            <h1>Your next good match<span class="lime">.</span></h1>
            <p>Campaign invitations selected for your content, community, and ambition.</p>
        </div>
        @if($profile)<a href="{{ route('creator.apply') }}" class="btn btn-secondary"><x-ui-icon name="user" size="15"/> Your creator profile</a>@endif
    </div>

    @if($errors->any())
        <div class="alert error" role="alert"><x-ui-icon name="message"/><span>{{ $errors->first() }}</span></div>
    @endif

    @if(!$profile)
        <section class="panel empty-state">
            <x-ui-icon name="spark"/>
            <h3>Let’s get to know your world.</h3>
            <p>Share your channels, audience, and partnership preferences. Our team will review your profile and find opportunities that fit.</p>
            <a href="{{ route('creator.apply') }}" class="btn btn-primary">Complete your creator profile <x-ui-icon name="right" size="15"/></a>
        </section>
    @else
        @if($profile->status === 'pending')
            <div class="privacy-note"><x-ui-icon name="clock"/><span>Your profile is with our team for review. We will review your details before proposing your profile to a brand.</span></div>
        @elseif($profile->status === 'declined')
            <div class="privacy-note"><x-ui-icon name="message"/><span>Your profile needs a little more attention. Update your details to submit it for another review.</span></div>
        @else
            <div class="privacy-note"><x-ui-icon name="shield"/><span>Your partnerships are coordinated by All In Streams. Respond here and your account manager will handle introductions, agreements, and next steps.</span></div>
        @endif

        <div class="invite-list">
            @forelse($invitations as $invitation)
                <section class="panel invite-card">
                    <div class="invite-head">
                        <div>
                            <span class="eyebrow">{{ $invitation->status === 'pending' ? 'A NEW OPPORTUNITY' : 'YOUR CAMPAIGN INVITATION' }}</span>
                            <h2>{{ $invitation->brief->title }}</h2>
                            <p>{{ $invitation->brief->code }} · Coordinated by All In Streams</p>
                        </div>
                        <x-status :value="$invitation->status"/>
                    </div>

                    <div class="invite-grid">
                        <div>
                            <div class="invite-field"><small><x-ui-icon name="game" size="17"/> Campaign type</small><strong>{{ $invitation->brief->vertical }}</strong></div>
                            <div class="invite-field"><small><x-ui-icon name="globe" size="17"/> Audience markets</small>@foreach($invitation->brief->geos as $geo)<span class="tag">{{ $geo }}</span>@endforeach</div>
                            <div class="invite-field"><small><x-ui-icon name="layers" size="17"/> Your deliverables</small><strong class="whitespace-pre-line">{{ $invitation->deliverables }}</strong></div>
                            <div class="invite-field"><small><x-ui-icon name="calendar" size="17"/> Campaign schedule</small><strong>{{ $invitation->brief->start_date->format('d M Y') }} — {{ $invitation->brief->end_date->format('d M Y') }}</strong></div>
                        </div>
                        <div>
                            <div class="invite-field"><small><x-ui-icon name="wallet" size="17"/> Your proposed compensation</small><strong class="compensation">{{ $invitation->brief->currency }} {{ number_format($invitation->compensation, 0) }}</strong><p class="prose-note mt-2">Your proposed creator fee. Your account manager coordinates final terms.</p></div>
                            <div class="invite-field"><small><x-ui-icon name="message" size="17"/> Language & platform</small><strong>{{ $invitation->brief->language }} · {{ $invitation->brief->platform }}</strong></div>
                            @if($invitation->brief->kpis)<div class="invite-field"><small><x-ui-icon name="target" size="17"/> Campaign objectives</small><p class="prose-note whitespace-pre-line">{{ $invitation->brief->kpis }}</p></div>@endif
                            @if($invitation->notes)<div class="invite-field"><small><x-ui-icon name="shield" size="17"/> Requirements & your terms</small><p class="prose-note whitespace-pre-line">{{ $invitation->notes }}</p></div>@endif
                        </div>
                    </div>

                    @if($invitation->status === 'pending' && in_array($invitation->brief->status, ['matching','proposal']))
                        <form method="POST" action="{{ route('creator.respond', $invitation) }}" class="invite-form">
                            @csrf
                            <div class="form-field">
                                <label for="invitation-notes-{{ $invitation->id }}">A note for your account manager <span class="muted">(optional)</span></label>
                                <textarea id="invitation-notes-{{ $invitation->id }}" name="notes" rows="2" maxlength="3000" placeholder="Questions, availability, or changes you would like to discuss…">{{ old('notes') }}</textarea>
                            </div>
                            <div class="invite-actions">
                                <button type="submit" name="status" value="interested" class="btn btn-primary"><x-ui-icon name="star" size="17"/> I’m interested</button>
                                <button type="submit" name="status" value="changes_requested" class="btn btn-secondary"><x-ui-icon name="message" size="17"/> Propose changes</button>
                                <button type="submit" name="status" value="declined" class="btn btn-ghost"><x-ui-icon name="close" size="17"/> Decline</button>
                            </div>
                            <p class="prose-note mt-4">Expressing interest starts a conversation. All In Streams confirms agreements and campaign readiness before launch.</p>
                        </form>
                    @else
                        <div class="invite-form">
                            <p class="prose-note">{{ match($invitation->status) { 'interested' => 'You expressed interest. Your account manager is coordinating the next steps.', 'changes_requested' => 'Your requested changes are with our team. Your account manager will follow up.', 'declined' => 'You declined this invitation. We will keep looking for opportunities that fit.', default => 'This campaign has moved forward. Your account manager can help with any questions.' } }}</p>
                            @if($invitation->response_notes)<p class="prose-note mt-3 whitespace-pre-line"><strong>Your note:</strong> {{ $invitation->response_notes }}</p>@endif
                        </div>
                    @endif
                </section>
            @empty
                <section class="panel empty-state">
                    <x-ui-icon name="star"/>
                    <h3>Good partnerships start with the right fit.</h3>
                    <p>Your campaign invitations will appear here when our team finds a match for your audience and content.</p>
                    <a href="{{ route('creator.apply') }}" class="btn btn-secondary btn-small">Review your creator profile <x-ui-icon name="right" size="14"/></a>
                </section>
            @endforelse
        </div>
    @endif
</x-portal-layout>
