<div class="wizard-shell">
    <div class="page-heading">
        <div>
            <p class="eyebrow">THE CREATOR NETWORK</p>
            <h1>Your content. New possibilities.</h1>
            <p>Help us find partnerships that fit your voice, your community, and your ambitions.</p>
        </div>
        <span class="status"><span></span> Creator profile</span>
    </div>

    <ol class="wizard-steps" aria-label="Application progress">
        @foreach(['About you', 'Your channel', 'Audience', 'Partnerships', 'Review'] as $label)
            <li class="wizard-step {{ $step === $loop->iteration ? 'active' : ($step > $loop->iteration ? 'complete' : '') }}" @if($step === $loop->iteration) aria-current="step" @endif>
                <span>@if($step > $loop->iteration)<x-ui-icon name="check" size="13"/>@else{{ $loop->iteration }}@endif</span>{{ $label }}
            </li>
        @endforeach
    </ol>

    <form wire:submit="submit" class="panel wizard-panel">
        <div wire:key="creator-step-{{ $step }}">
            @if($step === 1)
                <h2 class="wizard-section-title">Let’s start with you.</h2>
                <p class="wizard-description">A little about the person behind the stream. Your details stay with the All In Streams team until an introduction is authorized.</p>
                <div class="form-grid">
                    <div class="form-field span-two">
                        <label for="creator-name">Creator / public name</label>
                        <input id="creator-name" wire:model="public_name" maxlength="120" autocomplete="nickname" placeholder="The name your community knows" aria-invalid="{{ $errors->has('public_name') ? 'true' : 'false' }}">
                        @error('public_name')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="creator-country">Country of residence</label>
                        <input id="creator-country" wire:model="country" maxlength="80" autocomplete="country-name" placeholder="e.g. Germany">
                        @error('country')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="creator-language">Main content language</label>
                        <input id="creator-language" wire:model="language" maxlength="80" placeholder="e.g. German" list="creator-languages">
                        <datalist id="creator-languages"><option value="English"><option value="German"><option value="French"><option value="Spanish"><option value="Portuguese"><option value="Polish"><option value="Georgian"></datalist>
                        @error('language')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="creator-bio">Your story <span>(optional)</span></label>
                        <textarea id="creator-bio" wire:model="bio" maxlength="3000" placeholder="Tell us about your content, your community, and what you love creating."></textarea>
                        @error('bio')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            @elseif($step === 2)
                <h2 class="wizard-section-title">Where your community comes together.</h2>
                <p class="wizard-description">Add your Twitch or Kick channel. If you stream on both, share both links so our team can review your complete presence.</p>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="creator-platform">Main platform</label>
                        <select id="creator-platform" wire:model.live="platform">@foreach(['Twitch', 'YouTube', 'Kick', 'TikTok'] as $option)<option>{{ $option }}</option>@endforeach</select>
                        @error('platform')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="creator-vertical">Content category</label>
                        <select id="creator-vertical" wire:model="vertical"><option>Gaming</option><option>Esports</option><option>iGaming</option></select>
                        @error('vertical')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="creator-twitch">Twitch channel URL</label>
                        <input id="creator-twitch" wire:model="twitch_url" type="url" maxlength="255" inputmode="url" placeholder="https://www.twitch.tv/your_channel" aria-invalid="{{ $errors->has('twitch_url') ? 'true' : 'false' }}">
                        @error('twitch_url')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="creator-kick">Kick channel URL</label>
                        <input id="creator-kick" wire:model="kick_url" type="url" maxlength="255" inputmode="url" placeholder="https://kick.com/your_channel" aria-invalid="{{ $errors->has('kick_url') ? 'true' : 'false' }}">
                        @error('kick_url')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    @if(in_array($platform, ['YouTube', 'TikTok'], true) || ($channel_url && ! $twitch_url && ! $kick_url))
                    <div class="form-field span-two">
                        <label for="creator-channel">{{ in_array($platform, ['YouTube', 'TikTok'], true) ? 'Primary channel URL' : 'Previously added primary channel' }}</label>
                        <input id="creator-channel" wire:model="channel_url" type="url" maxlength="255" inputmode="url" placeholder="https://www.twitch.tv/your-channel" aria-invalid="{{ $errors->has('channel_url') ? 'true' : 'false' }}">
                    </div>
                    @endif
                    @error('channel_url')<p class="field-error span-two" role="alert">Add a channel link before continuing.</p>@enderror
                </div>
                <div class="privacy-note"><x-ui-icon name="lock"/><span>We review your channel to understand your content and audience. Your channel details stay protected in business shortlists until staff authorizes an introduction.</span></div>
            @elseif($step === 3)
                <h2 class="wizard-section-title">It’s all about your people.</h2>
                <p class="wizard-description">Recent, representative audience figures help us match you with relevant opportunities.</p>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="creator-followers">Total followers / subscribers</label>
                        <input id="creator-followers" wire:model="followers" type="number" min="0" max="2000000000" step="1" inputmode="numeric" placeholder="e.g. 48000">
                        @error('followers')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="creator-viewers">Average live viewers</label>
                        <input id="creator-viewers" wire:model="average_viewers" type="number" min="0" max="100000000" step="1" inputmode="numeric" placeholder="e.g. 1200">
                        @error('average_viewers')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <fieldset class="form-field span-two">
                        <legend class="field-label">Your main audience markets</legend>
                        <div class="choice-grid">
                            @foreach(['Germany', 'Austria', 'Switzerland', 'France', 'Poland', 'Portugal', 'Spain', 'Italy', 'Netherlands', 'United Kingdom', 'United States', 'Canada', 'Brazil', 'Mexico', 'Argentina', 'Georgia', 'Turkey', 'Australia', 'India', 'Japan', 'South Korea', 'United Arab Emirates'] as $geo)
                                <label class="choice-label"><input type="checkbox" wire:model="audience_geos" value="{{ $geo }}"><span>{{ $geo }}</span></label>
                            @endforeach
                        </div>
                        @error('audience_geos')<p class="field-error" role="alert">Choose at least one audience market.</p>@enderror
                        @error('audience_geos.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </fieldset>
                </div>
                <div class="privacy-note"><x-ui-icon name="shield"/><span>Your application statistics help our team find the right fit. They are never added automatically to the public campaign gallery.</span></div>
            @elseif($step === 4)
                <h2 class="wizard-section-title">Partnerships on your terms.</h2>
                <p class="wizard-description">Give us a starting point. You’ll review the scope and compensation of each invitation before deciding.</p>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="creator-rate">Indicative base rate (EUR)</label>
                        <input id="creator-rate" wire:model="rate" type="number" min="0" max="999999999" step="0.01" inputmode="decimal" placeholder="e.g. 750">
                        @error('rate')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field">
                        <label for="creator-model">Preferred commercial model</label>
                        <select id="creator-model" wire:model="commercial_model">@foreach(['Fixed', 'Fixed + CPA', 'CPA', 'Revenue share', 'Open'] as $option)<option value="{{ $option }}">{{ $option === 'Open' ? 'Open to discussion' : $option }}</option>@endforeach</select>
                        @error('commercial_model')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-field span-two">
                        <label for="creator-availability">Availability for brand partnerships</label>
                        <select id="creator-availability" wire:model="availability"><option value="Open">Open to opportunities</option><option value="Opening soon">Opening soon</option><option value="Unavailable">Currently unavailable</option></select>
                        @error('availability')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="privacy-note"><x-ui-icon name="wallet"/><span>Your base rate and individual compensation terms are private between you and All In Streams.</span></div>
            @else
                <h2 class="wizard-section-title">Your next chapter starts here.</h2>
                <p class="wizard-description">Check your details, then send your profile to the team for review.</p>
                <div class="review-grid">
                    <div><small>Creator name</small><strong>{{ $public_name }}</strong></div>
                    <div><small>Country & language</small><strong>{{ $country }} · {{ $language }}</strong></div>
                    <div><small>Main platform</small><strong>{{ $platform }}</strong></div>
                    <div><small>Content category</small><strong>{{ $vertical }}</strong></div>
                    <div class="span-two"><small>Channel URL</small><strong>{{ $channel_url }}</strong></div>
                    @if($twitch_url)<div class="span-two"><small>Twitch channel</small><strong>{{ $twitch_url }}</strong></div>@endif
                    @if($kick_url)<div class="span-two"><small>Kick channel</small><strong>{{ $kick_url }}</strong></div>@endif
                    <div><small>Followers / subscribers</small><strong>{{ number_format((float) $followers) }}</strong></div>
                    <div><small>Average live viewers</small><strong>{{ number_format((float) $average_viewers) }}</strong></div>
                    <div class="span-two"><small>Main audience markets</small><strong>{{ implode(', ', $audience_geos) }}</strong></div>
                    <div><small>Indicative base rate</small><strong>€{{ number_format((float) $rate, 2) }}</strong></div>
                    <div><small>Commercial model</small><strong>{{ $commercial_model === 'Open' ? 'Open to discussion' : $commercial_model }}</strong></div>
                    <div><small>Availability</small><strong>{{ $availability }}</strong></div>
                    @if($bio)<div class="span-two"><small>Your story</small><strong>{{ $bio }}</strong></div>@endif
                </div>
                <div class="privacy-note"><x-ui-icon name="shield"/><span>Our team reviews every profile before matching. You choose which invitations to accept; All In Streams coordinates introductions and agreements.</span></div>
                @if($errors->any())<div class="alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @endif
        </div>

        <div class="wizard-actions">
            @if($step > 1)<button type="button" class="btn btn-secondary" wire:click="back" wire:loading.attr="disabled"><x-ui-icon name="left" size="16"/> Back</button>@else<a href="/creator/opportunities" class="btn btn-secondary">Cancel</a>@endif
            <span>Step {{ $step }} of 5 · Your next opportunity awaits</span>
            @if($step < 5)
                <button type="button" class="btn btn-primary" wire:click="next" wire:loading.attr="disabled"><span wire:loading.remove wire:target="next">Continue</span><span wire:loading wire:target="next">Checking…</span><x-ui-icon name="right" size="16"/></button>
            @else
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><span wire:loading.remove wire:target="submit">Submit profile for review</span><span wire:loading wire:target="submit">Submitting…</span><x-ui-icon name="right" size="16"/></button>
            @endif
        </div>
    </form>
</div>
