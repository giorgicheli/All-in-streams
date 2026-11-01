<x-portal-layout title="Delivery evidence">
    <div class="page-heading"><div><span class="eyebrow">YOUR WORK. REVIEWED BY OUR TEAM.</span><h1>Delivery evidence<span class="lime">.</span></h1><p>Submit your completed streams and supporting evidence to All In Streams.</p></div></div>
    <div class="privacy-note"><x-ui-icon name="shield"/><span>Only you and All In Streams can access your evidence files here. Audience figures you submit are self-reported and require review.</span></div>
    @if($errors->any())<div class="alert error" role="alert">{{ $errors->first() }}</div>@endif

    @foreach($opportunities as $opportunity)
        <section class="panel mb-6" style="padding:1.5rem">
            <span class="eyebrow">SELECTED OPPORTUNITY</span><h2>{{ $opportunity['code'] }}</h2>
            <p class="prose-note whitespace-pre-line">{{ $opportunity['deliverables'] }}</p>
            <form method="POST" action="{{ route('provider.deliveries.store', $opportunity['id']) }}" enctype="multipart/form-data" class="mt-6">
                @csrf
                <div class="form-grid">
                    <div class="form-field"><label for="delivery-title-{{ $opportunity['id'] }}">Delivery title</label><input id="delivery-title-{{ $opportunity['id'] }}" name="title" required maxlength="200" placeholder="Stream 1 - completed" value="{{ old('title') }}"></div>
                    <div class="form-field"><label for="delivery-date-{{ $opportunity['id'] }}">Delivery date</label><input id="delivery-date-{{ $opportunity['id'] }}" name="delivered_on" type="date" required max="{{ today()->toDateString() }}" value="{{ old('delivered_on', today()->toDateString()) }}"></div>
                    <div class="form-field"><label for="delivery-url-{{ $opportunity['id'] }}">Stream, VOD or content link</label><input id="delivery-url-{{ $opportunity['id'] }}" name="content_url" type="url" required maxlength="2048" placeholder="https://" value="{{ old('content_url') }}"></div>
                    <div class="form-field"><label for="delivery-file-{{ $opportunity['id'] }}">Evidence file (optional)</label><input id="delivery-file-{{ $opportunity['id'] }}" name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif"><small>PDF or image, up to 10 MB.</small></div>
                    @foreach(['reported_impressions' => 'Reported impressions', 'reported_clicks' => 'Reported clicks', 'reported_conversions' => 'Reported conversions'] as $field => $label)
                        <div class="form-field"><label for="{{ $field }}-{{ $opportunity['id'] }}">{{ $label }} (optional)</label><input id="{{ $field }}-{{ $opportunity['id'] }}" name="{{ $field }}" type="number" min="0" max="2147483647" step="1" value="{{ old($field) }}"></div>
                    @endforeach
                </div>
                <div class="form-field"><label for="delivery-notes-{{ $opportunity['id'] }}">Notes (optional)</label><textarea id="delivery-notes-{{ $opportunity['id'] }}" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea></div>
                <button class="btn btn-primary" type="submit">Submit for review <x-ui-icon name="right" size="16"/></button>
            </form>
        </section>
    @endforeach

    <div class="invite-list">
        @forelse($submissions as $submission)
            <section class="panel invite-card">
                <div class="invite-head"><div><span class="eyebrow">{{ $submission['invitation']['code'] }}</span><h2>{{ $submission['title'] }}</h2><p>Delivered {{ $submission['delivered_on'] }}</p></div><x-status :value="$submission['status']"/></div>
                <a href="{{ $submission['content_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-small">View content <x-ui-icon name="right" size="14"/></a>
                @if($submission['has_file'])<a href="{{ route('provider.deliveries.download', $submission['id']) }}" class="btn btn-secondary btn-small">Download evidence</a>@endif
                @if($submission['notes'])<p class="prose-note mt-4 whitespace-pre-line">{{ $submission['notes'] }}</p>@endif
                <p class="prose-note mt-4">Self-reported: {{ $submission['reported_impressions'] ?? '—' }} impressions · {{ $submission['reported_clicks'] ?? '—' }} clicks · {{ $submission['reported_conversions'] ?? '—' }} conversions. These figures do not automatically become approved campaign results.</p>
                @if($submission['review_notes'])<div class="privacy-note mt-4"><x-ui-icon name="message"/><span>{{ $submission['review_notes'] }}</span></div>@endif
                @if($submission['can_resubmit'])
                    <form method="POST" action="{{ route('provider.deliveries.store', $submission['invitation']['id']) }}" enctype="multipart/form-data" class="invite-form">
                        @csrf<input type="hidden" name="submission_id" value="{{ $submission['id'] }}">
                        <h3>Update the evidence requested by our team</h3>
                        <div class="form-grid mt-4">
                            <div class="form-field"><label for="resubmit-title-{{ $submission['id'] }}">Delivery title</label><input id="resubmit-title-{{ $submission['id'] }}" name="title" required maxlength="200" value="{{ $submission['title'] }}"></div>
                            <div class="form-field"><label for="resubmit-date-{{ $submission['id'] }}">Delivery date</label><input id="resubmit-date-{{ $submission['id'] }}" name="delivered_on" type="date" required max="{{ today()->toDateString() }}" value="{{ $submission['delivered_on'] }}"></div>
                            <div class="form-field"><label for="resubmit-url-{{ $submission['id'] }}">Content link</label><input id="resubmit-url-{{ $submission['id'] }}" name="content_url" type="url" required maxlength="2048" value="{{ $submission['content_url'] }}"></div>
                            <div class="form-field"><label for="resubmit-file-{{ $submission['id'] }}">Replace evidence file (optional)</label><input id="resubmit-file-{{ $submission['id'] }}" name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif"><small>Leave empty to keep your current file.</small></div>
                            @foreach(['reported_impressions' => 'Reported impressions', 'reported_clicks' => 'Reported clicks', 'reported_conversions' => 'Reported conversions'] as $field => $label)
                                <div class="form-field"><label for="resubmit-{{ $field }}-{{ $submission['id'] }}">{{ $label }}</label><input id="resubmit-{{ $field }}-{{ $submission['id'] }}" name="{{ $field }}" type="number" min="0" max="2147483647" step="1" value="{{ $submission[$field] }}"></div>
                            @endforeach
                        </div>
                        <div class="form-field"><label for="resubmit-notes-{{ $submission['id'] }}">Your notes</label><textarea id="resubmit-notes-{{ $submission['id'] }}" name="notes" rows="3" maxlength="5000">{{ $submission['notes'] }}</textarea></div>
                        <button type="submit" class="btn btn-primary">Resubmit for review</button>
                    </form>
                @endif
            </section>
        @empty
            <section class="panel empty-state"><x-ui-icon name="file"/><h3>Your delivery records will appear here.</h3><p>Evidence can be submitted after All In Streams selects your accepted offer and launches the campaign.</p></section>
        @endforelse
    </div>
</x-portal-layout>
