<div class="wizard-shell">
    <div class="page-heading">
        <div>
            <p class="eyebrow">YOUR NEXT CAMPAIGN</p>
            <h1>Big ambitions start here.</h1>
            <p>Tell us the mission. We’ll bring the right creators, communities, and plan.</p>
        </div>
        <span class="status"><span></span> New campaign brief</span>
    </div>

    <ol class="wizard-steps" aria-label="Brief progress">
        @foreach(['Campaign', 'Audience', 'The details', 'Review'] as $label)
            <li class="wizard-step {{ $step === $loop->iteration ? 'active' : ($step > $loop->iteration ? 'complete' : '') }}" @if($step === $loop->iteration) aria-current="step" @endif>
                <span>@if($step > $loop->iteration)<x-ui-icon name="check" size="13"/>@else{{ $loop->iteration }}@endif</span>{{ $label }}
            </li>
        @endforeach
    </ol>

    <form wire:submit="submit" class="panel wizard-panel">
        <div wire:key="brief-step-{{ $step }}">
            @if($step === 1)
                <h2 class="wizard-section-title">First, the big picture.</h2>
                <p class="wizard-description">Give your campaign a name and let us know the world your brand plays in.</p>
                <div class="form-grid">
                    <div class="form-field span-two">
                        <label for="brief-title">Campaign name</label>
                        <input id="brief-title" wire:model="title" placeholder="e.g. Q2 DACH creator campaign" maxlength="180" autocomplete="off" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}">
                        @error('title')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="brief-brand">Brand name</label>
                        <input id="brief-brand" wire:model="brand" placeholder="Your brand or company" maxlength="180" autocomplete="organization" aria-invalid="{{ $errors->has('brand') ? 'true' : 'false' }}">
                        @error('brand')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="brief-vertical">Industry</label>
                        <select id="brief-vertical" wire:model="vertical"><option>Gaming</option><option>Esports</option><option>iGaming</option></select>
                        @error('vertical')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            @elseif($step === 2)
                <h2 class="wizard-section-title">Find your people.</h2>
                <p class="wizard-description">Audience fit comes first. Choose the markets, language, and platform that matter to you.</p>
                <div class="form-grid">
                    <fieldset class="form-field span-two">
                        <legend class="field-label">Target audience GEOs</legend>
                        <div class="choice-grid">
                            @foreach(['Germany', 'Austria', 'Switzerland', 'France', 'Poland', 'Portugal', 'Spain', 'Italy', 'Netherlands', 'United Kingdom', 'United States', 'Canada', 'Brazil', 'Mexico', 'Argentina', 'Georgia', 'Turkey', 'Australia', 'India', 'Japan', 'South Korea', 'United Arab Emirates'] as $geo)
                                <label class="choice-label"><input type="checkbox" wire:model="geos" value="{{ $geo }}"><span>{{ $geo }}</span></label>
                            @endforeach
                        </div>
                        @error('geos')<p class="field-error" role="alert">Choose at least one audience market.</p>@enderror
                        @error('geos.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="form-field">
                        <label for="brief-language">Campaign language</label>
                        <input id="brief-language" wire:model="language" placeholder="e.g. German" maxlength="80" list="brief-languages">
                        <datalist id="brief-languages"><option value="English"><option value="German"><option value="French"><option value="Spanish"><option value="Portuguese"><option value="Polish"><option value="Georgian"></datalist>
                        @error('language')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="brief-platform">Preferred platform</label>
                        <select id="brief-platform" wire:model="platform">@foreach(['Twitch', 'Kick', 'Facebook', 'Instagram', 'YouTube', 'TikTok', 'Multiple'] as $option)<option>{{ $option }}</option>@endforeach</select>
                        @error('platform')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="brief-provider">Who should deliver this campaign?</label>
                        <select id="brief-provider" wire:model="provider_type"><option value="either">A creator or an agency — let staff recommend</option><option value="streamer">Streamer — Twitch / Kick</option><option value="influencer">Influencer — social media</option><option value="creator">Any individual creator</option><option value="agency">Agency with its own roster</option></select>
                        @error('provider_type')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            @elseif($step === 3)
                <h2 class="wizard-section-title">Make the plan yours.</h2>
                <p class="wizard-description">Share your budget, timing, and what a successful partnership looks like.</p>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="brief-budget">Total campaign budget</label>
                        <input id="brief-budget" wire:model="budget" type="number" min="100" max="999999999" step="0.01" inputmode="decimal" placeholder="18,000">
                        @error('budget')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="brief-currency">Currency</label>
                        <select id="brief-currency" wire:model="currency"><option value="EUR">EUR — Euro</option><option value="USD">USD — US dollar</option><option value="GBP">GBP — British pound</option></select>
                        @error('currency')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="brief-model">Preferred commercial model</label>
                        <select id="brief-model" wire:model="commercial_model">@foreach(['Fixed', 'Fixed + CPA', 'CPA', 'Revenue share', 'Open'] as $option)<option value="{{ $option }}">{{ $option === 'Open' ? 'Open to discussion' : $option }}</option>@endforeach</select>
                        @error('commercial_model')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="brief-start">Campaign start</label>
                        <input id="brief-start" wire:model="start_date" type="date" min="{{ now()->toDateString() }}">
                        @error('start_date')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="brief-end">Campaign end</label>
                        <input id="brief-end" wire:model="end_date" type="date" min="{{ $start_date ?: now()->toDateString() }}">
                        @error('end_date')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="brief-deliverables">Deliverables</label>
                        <textarea id="brief-deliverables" wire:model="deliverables" maxlength="5000" placeholder="e.g. 4 live streams of 90 minutes, 2 VODs, and tracking links in each stream description."></textarea>
                        @error('deliverables')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="brief-kpis">Your goals and KPIs <span>(optional)</span></label>
                        <input id="brief-kpis" wire:model="kpis" maxlength="2000" placeholder="e.g. Reach 50,000 relevant viewers across the DACH region">
                        @error('kpis')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="brief-notes">Anything else we should know? <span>(optional)</span></label>
                        <textarea id="brief-notes" wire:model="notes" maxlength="5000" placeholder="Brand guidelines, creative direction, market requirements, or a little more context."></textarea>
                        @error('notes')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="brief-attachment">Detailed brief <span>(optional PDF, up to 10 MB)</span></label>
                        <input id="brief-attachment" type="file" wire:model="attachment" accept="application/pdf">
                        <p class="prose-note mt-2">Private to you and All In Streams. Providers receive a separate offer with approved scope and their own compensation.</p>
                        <span wire:loading wire:target="attachment" class="prose-note">Uploading…</span>
                        @error('attachment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            @else
                <h2 class="wizard-section-title">Ready to go all in?</h2>
                <p class="wizard-description">Take one last look. Your account manager will review the brief and coordinate the next steps.</p>
                <div class="review-grid">
                    <div><small>Campaign</small><strong>{{ $title }}</strong></div>
                    <div><small>Brand & industry</small><strong>{{ $brand }} · {{ $vertical }}</strong></div>
                    <div><small>Audience markets</small><strong>{{ implode(', ', $geos) }}</strong></div>
                    <div><small>Language & platform</small><strong>{{ $language }} · {{ $platform }}</strong></div>
                    <div><small>Budget</small><strong>{{ number_format((float) $budget, 2) }} {{ $currency }}</strong></div>
                    <div><small>Commercial model</small><strong>{{ $commercial_model === 'Open' ? 'Open to discussion' : $commercial_model }}</strong></div>
                    <div><small>Provider preference</small><strong>{{ match($provider_type) { 'streamer' => 'Streamer', 'influencer' => 'Influencer', 'creator' => 'Individual creator', 'agency' => 'Agency', default => 'Creator or agency' } }}</strong></div>
                    @if($attachment)<div><small>Private brief attachment</small><strong>PDF attached</strong></div>@endif
                    <div><small>Start date</small><strong>{{ $start_date }}</strong></div>
                    <div><small>End date</small><strong>{{ $end_date }}</strong></div>
                    <div class="span-two"><small>Deliverables</small><strong>{{ $deliverables }}</strong></div>
                    @if($kpis)<div class="span-two"><small>Goals & KPIs</small><strong>{{ $kpis }}</strong></div>@endif
                    @if($notes)<div class="span-two"><small>Additional context</small><strong>{{ $notes }}</strong></div>@endif
                </div>
                <div class="privacy-note"><x-ui-icon name="shield"/><span>Your brief is shared privately with All In Streams. Submission starts the review process; a campaign launches after agreements and payment readiness are confirmed.</span></div>
                @if($errors->any())<div class="alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @endif
        </div>

        <div class="wizard-actions">
            @if($step > 1)<button type="button" class="btn btn-secondary" wire:click="back" wire:loading.attr="disabled"><x-ui-icon name="left" size="16"/> Back</button>@else<a href="{{ auth()->user()->role === 'agency' ? '/agency' : '' }}/campaigns" class="btn btn-secondary">Cancel</a>@endif
            <span>Step {{ $step }} of 4 · Managed by All In Streams</span>
            @if($step < 4)
                <button type="button" class="btn btn-primary" wire:click="next" wire:loading.attr="disabled"><span wire:loading.remove wire:target="next">Continue</span><span wire:loading wire:target="next">Checking…</span><x-ui-icon name="right" size="16"/></button>
            @else
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="submit">Submit for review</span><span wire:loading wire:target="submit">Submitting…</span><x-ui-icon name="right" size="16"/></button>
            @endif
        </div>
    </form>
</div>
