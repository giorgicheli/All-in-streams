<section class="panel invite-card">
    <div class="invite-head">
        <div><span class="eyebrow">YOUR PRIVATE {{ $offer['recipient_type'] === 'agency' ? 'AGENCY' : 'CREATOR' }} OFFER</span><h2>{{ $offer['opportunity_title'] }}</h2><p>{{ $offer['code'] }} · Offer version {{ $offer['offer_version'] }} · All In Streams</p></div>
        <div><x-status :value="$offer['status']"/>@if($offer['selection_status'] === 'selected')<span class="tag">Selected by staff</span>@elseif($offer['selection_status'] === 'not_selected')<span class="tag">Closed</span>@endif</div>
    </div>
    <div class="invite-grid">
        <div>
            <div class="invite-field"><small><x-ui-icon name="game" size="17"/> Campaign type</small><strong>{{ $offer['vertical'] }}</strong></div>
            <div class="invite-field"><small><x-ui-icon name="globe" size="17"/> Audience markets</small>@foreach($offer['geos'] ?? [] as $geo)<span class="tag">{{ $geo }}</span>@endforeach</div>
            <div class="invite-field"><small><x-ui-icon name="layers" size="17"/> Your deliverables</small><strong class="whitespace-pre-line">{{ $offer['deliverables'] }}</strong></div>
            <div class="invite-field"><small><x-ui-icon name="calendar" size="17"/> Proposed schedule</small><strong>{{ $offer['start_date'] ?: 'To be agreed' }} — {{ $offer['end_date'] ?: 'To be agreed' }}</strong></div>
        </div>
        <div>
            <div class="invite-field"><small><x-ui-icon name="wallet" size="17"/> Your proposed compensation</small><strong class="compensation">{{ $offer['currency'] }} {{ number_format((float)$offer['compensation'], 2) }}</strong>@if($offer['cpa_rate'] !== null)<p class="prose-note mt-2">+ {{ $offer['currency'] }} {{ number_format((float)$offer['cpa_rate'], 2) }} per validated acquisition</p>@endif<p class="prose-note mt-2">Total fixed fee for this package. Acceptance is subject to final staff selection and separate agreements.</p></div>
            <div class="invite-field"><small><x-ui-icon name="message" size="17"/> Language & platform</small><strong>{{ $offer['language'] }} · {{ $offer['platform'] }}</strong></div>
            @if($offer['requirements'])<div class="invite-field"><small><x-ui-icon name="shield" size="17"/> Requirements</small><p class="prose-note whitespace-pre-line">{{ $offer['requirements'] }}</p></div>@endif
            @if($offer['notes'])<div class="invite-field"><small><x-ui-icon name="message" size="17"/> Note from All In Streams</small><p class="prose-note whitespace-pre-line">{{ $offer['notes'] }}</p></div>@endif
        </div>
    </div>
    @if($offer['can_respond'])
        <form method="POST" action="{{ route($responseRoute, $offer['id']) }}" class="invite-form" x-data="{ response: 'interested' }">
            @csrf<input type="hidden" name="offer_version" value="{{ $offer['offer_version'] }}">
            @if($offer['recipient_type'] === 'agency')
                <fieldset class="form-field mb-5"><legend class="field-label">Propose your approved roster talent</legend><p class="prose-note mb-3">Choose the people who would deliver this package. Only your reviewed roster is available.</p><div class="choice-grid">@foreach($talents ?? collect() as $talent)<label class="choice-label"><input type="checkbox" name="proposed_talent_ids[]" value="{{ $talent->id }}" @checked(in_array($talent->id, $offer['proposed_talent_ids']))><span>{{ $talent->public_name }} · {{ $talent->country }} · {{ $talent->platform }}</span></label>@endforeach</div></fieldset>
            @endif
            <div class="form-grid">
                <div class="form-field"><label for="response-{{ $offer['id'] }}">Your response</label><select id="response-{{ $offer['id'] }}" name="status" x-model="response"><option value="interested">Accept these terms</option><option value="changes_requested">Propose changes</option><option value="declined">Decline</option></select></div>
                <div class="form-field" x-show="response === 'changes_requested'" x-cloak><label for="counter-{{ $offer['id'] }}">Requested fixed fee ({{ $offer['currency'] }}) <span>(optional)</span></label><input id="counter-{{ $offer['id'] }}" name="counter_amount" type="number" min="0" max="999999999" step="0.01" inputmode="decimal" :disabled="response !== 'changes_requested'" placeholder="Total fee for the package"></div>
                <div class="form-field" x-show="response === 'changes_requested'" x-cloak><label for="cpa-{{ $offer['id'] }}">Requested CPA rate ({{ $offer['currency'] }}) <span>(optional)</span></label><input id="cpa-{{ $offer['id'] }}" name="counter_cpa_rate" type="number" min="0" max="999999999" step="0.01" inputmode="decimal" :disabled="response !== 'changes_requested'"></div>
                <div class="form-field span-two"><label for="notes-{{ $offer['id'] }}">A note for All In Streams <span x-text="response === 'changes_requested' ? '(required for changes)' : '(optional)'"></span></label><textarea id="notes-{{ $offer['id'] }}" name="notes" rows="3" maxlength="3000" :required="response === 'changes_requested'" placeholder="Explain your requested price, schedule or scope changes."></textarea></div>
            </div>
            <button class="btn btn-primary mt-5" type="submit">Send response <x-ui-icon name="right" size="16"/></button>
            <p class="prose-note mt-4">Your response is private between you and All In Streams. Staff will confirm one final provider. Revised terms need your acceptance again.</p>
        </form>
    @else
        <div class="invite-form"><p class="prose-note">{{ $offer['selection_status'] === 'selected' ? 'All In Streams selected you for this package. Agreements and campaign readiness are coordinated separately.' : ($offer['selection_status'] === 'not_selected' ? 'This opportunity is closed. Your response history remains available.' : match($offer['status']) { 'interested' => 'You accepted this version of the terms. The final staff selection is pending.', 'changes_requested' => 'Your proposed changes are with All In Streams. A revised offer will appear here for your acceptance.', 'declined' => 'You declined this offer.', default => 'This offer is currently unavailable for a response. Contact All In Streams for the next step.' }) }}</p></div>
    @endif
    @if(count($offer['history']))
        <details class="mt-5"><summary class="text-link">Offer & response history</summary><div class="mt-4">@foreach($offer['history'] as $event)<article class="activity-item"><div><strong>{{ $event['actor'] }} · {{ ucfirst(str_replace('_', ' ', $event['kind'])) }}</strong><p class="prose-note">{{ \Carbon\Carbon::parse($event['created_at'])->format('d M Y, H:i') }}@if($event['amount'] !== null) · {{ $offer['currency'] }} {{ number_format((float)$event['amount'], 2) }}@endif @if($event['cpa_rate'] !== null) + {{ $offer['currency'] }} {{ number_format((float)$event['cpa_rate'], 2) }} CPA @endif</p>@if($event['notes'])<p class="prose-note whitespace-pre-line">{{ $event['notes'] }}</p>@endif</div></article>@endforeach</div></details>
    @endif
</section>
