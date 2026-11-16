@php
    $selectedKind = old('kind', $kind ?? request('type', 'brand'));
    $selectedKind = in_array($selectedKind, ['brand', 'agency', 'creator'], true) ? $selectedKind : 'brand';
    $selectedCreatorType = old('creator_type', $creatorType ?? request('creator_type', 'streamer'));
    $selectedCreatorType = in_array($selectedCreatorType, ['streamer', 'influencer', 'both'], true) ? $selectedCreatorType : 'streamer';
    $isCreator = $selectedKind === 'creator';
    $isAgency = $selectedKind === 'agency';
    $hasStreams = in_array($selectedCreatorType, ['streamer', 'both'], true);
    $hasSocial = in_array($selectedCreatorType, ['influencer', 'both'], true);
    $oldText = static fn (string $key): string => is_scalar(old($key)) ? (string) old($key) : '';
    $rawGeos = old('audience_geos', []);
    $campaignGeoValue = is_array($rawGeos) ? implode(', ', array_filter($rawGeos, 'is_string')) : (is_string($rawGeos) ? $rawGeos : '');
    $selectedGeos = is_array($rawGeos) ? $rawGeos : [];
    $currencyOptions = $currencies ?? ['EUR', 'USD', 'GBP', 'CAD', 'AUD', 'CHF', 'BRL'];
    $enquiryKey = $submissionKey ?? $submission_key ?? (string) \Illuminate\Support\Str::uuid();
    $enquiryContext = [];
    if (($selectedProfiles ?? collect())->isNotEmpty()) $enquiryContext['profiles'] = $selectedProfiles->pluck('public_id')->all();
    if ($requestedRegion ?? null) $enquiryContext['region'] = $requestedRegion;
@endphp
<x-layout title="Talk to us" description="Create your All In Streams partner account and tell us about your brand, agency or creator profile.">
    <main class="partner-enquiry" data-partner-enquiry data-enquiry-kind="{{ $selectedKind }}" data-enquiry-creator-type="{{ $selectedCreatorType }}">
        <a href="/" class="enquiry-close" aria-label="Close and return to All In Streams"><x-ui-icon name="close" size="23"/></a>
        <div class="enquiry-shell">
            @if(session('enquiry_submitted'))
                <section class="enquiry-success" aria-labelledby="enquiry-success-title">
                    <span class="enquiry-success-icon"><x-ui-icon name="check" size="30"/></span>
                    <p class="enquiry-eyebrow">YOUR NEXT CONNECTION STARTS HERE</p>
                    <h1 id="enquiry-success-title">Thanks. We’re on it.</h1>
                    <p>Your details have reached the All In Streams team. We’ll review them and get in touch on Telegram about the next steps.</p>
                    <div class="enquiry-success-actions"><a href="/" class="enquiry-submit">Back to All In Streams <x-ui-icon name="arrow" size="18"/></a><a href="/talk-to-us?type={{ $selectedKind }}" class="enquiry-text-link">Send another request</a></div>
                </section>
            @else
                <header class="enquiry-intro">
                    <p class="enquiry-eyebrow" data-enquiry-eyebrow>{{ $isCreator ? 'ONGOING PROJECTS. FIXED PAID DEALS.' : 'LET’S MAKE IT HAPPEN' }}</p>
                    <h1 data-enquiry-title>{{ $isCreator ? 'Join our creator network and keep the deals coming.' : 'Tell us what you have in mind.' }}</h1>
                    <p data-enquiry-description>{{ $isCreator ? 'Get matched with brands for regular paid collaborations. You focus on your content. We bring the projects and handle the deals.' : ($isAgency ? 'Share your requirements so we can find the right streamers for your clients.' : 'Share a few details so we can find your right match.') }}</p>
                </header>
                <nav class="enquiry-category-tabs" aria-label="Your partner category">
                    @foreach(['brand' => 'Brand', 'agency' => 'Agency', 'creator' => 'Creator'] as $category => $label)
                        <a href="/talk-to-us?{{ http_build_query(['type' => $category] + $enquiryContext) }}" class="enquiry-choice {{ $selectedKind === $category ? 'is-selected' : '' }}" data-enquiry-category="{{ $category }}" @if($selectedKind === $category) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <p class="enquiry-review-note"><x-ui-icon name="shield" size="15"/>Create your partner account. The All In Streams team will review your details before approving access.</p>

                @if($errors->any())
                    <div class="enquiry-errors" role="alert" tabindex="-1" data-enquiry-errors>
                        <p>Please check the highlighted details.</p>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="/talk-to-us" enctype="multipart/form-data" class="enquiry-form" data-enquiry-form>
                    @csrf
                    @if(($selectedProfiles ?? collect())->isNotEmpty() || ($requestedRegion ?? null))
                        <div class="enquiry-shortlist-context" data-enquiry-for="brand agency" @if($isCreator) hidden @endif>
                            <p class="enquiry-group-label">Your creator request</p>
                            @if($requestedRegion ?? null)<p class="enquiry-help">Audience region: {{ $requestedRegion }}</p><input type="hidden" name="requested_region" value="{{ $requestedRegion }}">@endif
                            <div class="enquiry-shortlist-chips">@foreach($selectedProfiles ?? [] as $profile)<span>{{ $profile['country'] }} · {{ $profile['type_label'] }}</span><input type="hidden" name="profiles[]" value="{{ $profile['public_id'] }}">@endforeach</div>
                            @error('profiles.*')<p class="enquiry-error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    <input type="hidden" name="kind" value="{{ $selectedKind }}" data-enquiry-kind-input>
                    <input type="hidden" name="submission_key" value="{{ $enquiryKey }}">
                    <fieldset class="enquiry-type-field" data-enquiry-for="creator" @unless($isCreator) hidden disabled @endunless>
                        <legend>I am a <span aria-hidden="true">*</span></legend>
                        <input type="hidden" name="creator_type" value="{{ $selectedCreatorType }}" data-enquiry-creator-input>
                        <div class="enquiry-type-options" aria-label="Creator type">
                            @foreach(['streamer' => 'Streamer', 'influencer' => 'Influencer', 'both' => 'Both'] as $type => $label)
                                <a href="/talk-to-us?{{ http_build_query(['type' => 'creator', 'creator_type' => $type] + $enquiryContext) }}" class="enquiry-choice {{ $selectedCreatorType === $type ? 'is-selected' : '' }}" data-enquiry-type="{{ $type }}" @if($selectedCreatorType === $type) aria-current="page" @endif>{{ $label }}</a>
                            @endforeach
                        </div>
                        @error('creator_type')<p class="enquiry-error">{{ $message }}</p>@enderror
                    </fieldset>

                    <div class="enquiry-grid enquiry-contact-grid">
                        <div class="enquiry-field">
                            <label for="enquiry-name">Your name <span aria-hidden="true">*</span></label>
                            <input id="enquiry-name" name="name" value="{{ $oldText('name') }}" autocomplete="name" placeholder="Full name" required maxlength="120" @error('name') aria-invalid="true" aria-describedby="enquiry-name-error" @enderror>
                            @error('name')<p class="enquiry-error" id="enquiry-name-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="enquiry-field">
                            <label for="enquiry-email"><span data-enquiry-email-label>{{ $isCreator ? 'Email address' : 'Business email' }}</span> <span aria-hidden="true">*</span></label>
                            <input id="enquiry-email" name="email" type="email" value="{{ $oldText('email') }}" autocomplete="email" placeholder="{{ $isCreator ? 'you@example.com' : ($isAgency ? 'you@agency.com' : 'you@yourbrand.com') }}" data-enquiry-required="brand agency creator" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="enquiry-email-error" @enderror>
                            @error('email')<p class="enquiry-error" id="enquiry-email-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="enquiry-field">
                            <label for="enquiry-telegram">Telegram username <span aria-hidden="true">*</span></label>
                            <input id="enquiry-telegram" name="telegram" value="{{ $oldText('telegram') }}" autocomplete="off" placeholder="@yourusername" required maxlength="100" autocapitalize="none" spellcheck="false" @error('telegram') aria-invalid="true" aria-describedby="enquiry-telegram-error" @enderror>
                            @error('telegram')<p class="enquiry-error" id="enquiry-telegram-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="enquiry-field" data-enquiry-for="brand agency" @if($isCreator) hidden @endif>
                            <label for="enquiry-website"><span data-enquiry-website-label>{{ $isAgency ? 'Agency website' : 'Company / brand website' }}</span> <span aria-hidden="true">*</span></label>
                            <input id="enquiry-website" name="website" type="url" value="{{ $oldText('website') }}" placeholder="{{ $isAgency ? 'https://youragency.com' : 'https://yourbrand.com' }}" autocomplete="url" data-enquiry-required="brand agency" @if($isCreator) disabled @else required @endif maxlength="2048" @error('website') aria-invalid="true" aria-describedby="enquiry-website-error" @enderror>
                            @error('website')<p class="enquiry-error" id="enquiry-website-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="enquiry-grid enquiry-password-grid">
                        <div class="enquiry-field">
                            <label for="enquiry-password">Password <span aria-hidden="true">*</span></label>
                            <input id="enquiry-password" name="password" type="password" autocomplete="new-password" placeholder="Create a password" required minlength="10" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{10,}" title="Use at least 10 characters, including letters and a number." aria-describedby="enquiry-password-help @error('password') enquiry-password-error @enderror" @error('password') aria-invalid="true" @enderror>
                            <p class="enquiry-help" id="enquiry-password-help">At least 10 characters, including letters and a number.</p>
                            @error('password')<p class="enquiry-error" id="enquiry-password-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="enquiry-field">
                            <label for="enquiry-password-confirmation">Confirm password <span aria-hidden="true">*</span></label>
                            <input id="enquiry-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat your password" required minlength="10" data-enquiry-password-confirmation @error('password_confirmation') aria-invalid="true" aria-describedby="enquiry-password-confirmation-error" @enderror>
                            @error('password_confirmation')<p class="enquiry-error" id="enquiry-password-confirmation-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <fieldset class="enquiry-campaign" data-enquiry-for="brand agency" @if($isCreator) hidden disabled @endif>
                        <legend class="sr-only">Campaign requirements</legend>
                        <div class="enquiry-grid">
                            <div class="enquiry-field">
                                <label for="enquiry-campaign-geos">Target countries / audience GEOs <span aria-hidden="true">*</span></label>
                                <input id="enquiry-campaign-geos" name="audience_geos" value="{{ $campaignGeoValue }}" placeholder="e.g. Germany, Portugal" maxlength="1500" data-enquiry-required="brand agency" @unless($isCreator) required @endunless aria-describedby="enquiry-campaign-geos-help @error('audience_geos') enquiry-campaign-geos-error @enderror">
                                <p class="enquiry-help" id="enquiry-campaign-geos-help">Separate countries with commas.</p>
                                @error('audience_geos')<p class="enquiry-error" id="enquiry-campaign-geos-error">{{ $message }}</p>@enderror
                                @error('audience_geos.*')<p class="enquiry-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="enquiry-field" data-enquiry-for="agency" @unless($isAgency) hidden @endunless>
                                <label for="enquiry-streams">Number of streams <span aria-hidden="true">*</span></label>
                                <input id="enquiry-streams" name="streams" type="number" min="1" max="10000" step="1" inputmode="numeric" value="{{ $oldText('streams') }}" placeholder="e.g. 5" data-enquiry-required="agency" @if($isAgency) required @else disabled @endif @error('streams') aria-invalid="true" aria-describedby="enquiry-streams-error" @enderror>
                                @error('streams')<p class="enquiry-error" id="enquiry-streams-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="enquiry-field">
                                <label for="enquiry-budget">Campaign budget <span aria-hidden="true" data-enquiry-for="agency" @unless($isAgency) hidden @endunless>*</span></label>
                                <input id="enquiry-budget" name="budget" type="number" min="0.01" max="999999999.99" step="0.01" inputmode="decimal" value="{{ $oldText('budget') }}" placeholder="Amount" data-enquiry-required="agency" @if($isAgency) required @endif @error('budget') aria-invalid="true" aria-describedby="enquiry-budget-error" @enderror>
                                @error('budget')<p class="enquiry-error" id="enquiry-budget-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="enquiry-field" data-enquiry-for="agency" @unless($isAgency) hidden @endunless>
                                <label for="enquiry-budget-scope">Budget applies to <span aria-hidden="true">*</span></label>
                                <select id="enquiry-budget-scope" name="budget_scope" data-enquiry-required="agency" @if($isAgency) required @else disabled @endif>
                                    <option value="">Total campaign / Per stream</option>
                                    <option value="total" @selected(old('budget_scope') === 'total')>Total campaign</option>
                                    <option value="per_stream" @selected(old('budget_scope') === 'per_stream')>Per stream</option>
                                </select>
                                @error('budget_scope')<p class="enquiry-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="enquiry-field enquiry-requirements">
                            <label for="enquiry-requirements" data-enquiry-requirements-label>{{ $isAgency ? 'Additional requirements' : 'Campaign goals and preferred platforms' }}</label>
                            <textarea id="enquiry-requirements" name="requirements" rows="4" maxlength="5000" placeholder="{{ $isAgency ? 'Preferred platforms, campaign dates, creator preferences or KPIs…' : 'Target audience, timeline, stream package, and goals…' }}">{{ $oldText('requirements') }}</textarea>
                            @error('requirements')<p class="enquiry-error">{{ $message }}</p>@enderror
                        </div>
                    </fieldset>

                    <fieldset class="enquiry-creator" data-enquiry-for="creator" @unless($isCreator) hidden disabled @endunless>
                        <legend class="sr-only">Creator profile</legend>
                        <section class="enquiry-form-section" aria-labelledby="enquiry-platforms-title">
                            <h2 id="enquiry-platforms-title">Your platforms</h2>
                            <div data-enquiry-type-for="streamer both" @unless($hasStreams) hidden @endunless>
                                <p class="enquiry-group-label">Kick / Twitch links <span aria-hidden="true">*</span></p>
                                <p class="enquiry-help">Streamers: add at least one Kick or Twitch link.</p>
                                <div class="enquiry-grid">
                                    <div class="enquiry-field">
                                        <label for="enquiry-kick">Kick profile</label>
                                        <input id="enquiry-kick" name="kick_url" type="url" value="{{ $oldText('kick_url') }}" placeholder="https://kick.com/yourchannel" maxlength="255" data-enquiry-channel @unless($hasStreams) disabled @endunless>
                                        @error('kick_url')<p class="enquiry-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="enquiry-field">
                                        <label for="enquiry-twitch">Twitch profile</label>
                                        <input id="enquiry-twitch" name="twitch_url" type="url" value="{{ $oldText('twitch_url') }}" placeholder="https://www.twitch.tv/yourchannel" maxlength="255" data-enquiry-channel @unless($hasStreams) disabled @endunless>
                                        @error('twitch_url')<p class="enquiry-error">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="enquiry-field" data-enquiry-type-for="influencer both" @unless($hasSocial) hidden @endunless>
                                <label for="enquiry-instagram">Instagram profile <span aria-hidden="true">*</span></label>
                                <input id="enquiry-instagram" name="instagram_url" type="url" value="{{ $oldText('instagram_url') }}" placeholder="https://www.instagram.com/yourprofile" maxlength="255" data-enquiry-type-required="influencer both" @if($hasSocial) required @else disabled @endif>
                                @error('instagram_url')<p class="enquiry-error">{{ $message }}</p>@enderror
                            </div>
                        </section>
                        <section class="enquiry-form-section" aria-labelledby="enquiry-audience-title">
                            <h2 id="enquiry-audience-title">Your audience</h2>
                            <p class="enquiry-help">Choose every country your audience comes from.</p>
                            <x-audience-picker :countries="$countries" :selected="$selectedGeos"/>
                            @error('audience_geos')<p class="enquiry-error">{{ $message }}</p>@enderror
                        </section>
                        <section class="enquiry-form-section" aria-labelledby="enquiry-rates-title">
                            <h2 id="enquiry-rates-title">Your rates</h2>
                            <p class="enquiry-help">Fields depend on your creator type. Choose the currency below.</p>
                            <div class="enquiry-field" data-enquiry-type-for="streamer both" @unless($hasStreams) hidden @endunless>
                                <label for="enquiry-stream-rate">Price per stream <span aria-hidden="true">*</span></label>
                                <input id="enquiry-stream-rate" name="stream_rate" type="number" min="0.01" max="999999999.99" step="0.01" inputmode="decimal" value="{{ $oldText('stream_rate') }}" placeholder="e.g. 500 per stream" data-enquiry-type-required="streamer both" @if($hasStreams) required @else disabled @endif>
                                @error('stream_rate')<p class="enquiry-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="enquiry-grid" data-enquiry-type-for="influencer both" @unless($hasSocial) hidden @endunless>
                                <div class="enquiry-field">
                                    <label for="enquiry-story-rate">Price per Instagram story <span aria-hidden="true">*</span></label>
                                    <input id="enquiry-story-rate" name="story_rate" type="number" min="0.01" max="999999999.99" step="0.01" inputmode="decimal" value="{{ $oldText('story_rate') }}" placeholder="e.g. 100 per story" data-enquiry-type-required="influencer both" @if($hasSocial) required @else disabled @endif>
                                    @error('story_rate')<p class="enquiry-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="enquiry-field">
                                    <label for="enquiry-reel-rate">Price per Instagram Reel <span aria-hidden="true">*</span></label>
                                    <input id="enquiry-reel-rate" name="reel_rate" type="number" min="0.01" max="999999999.99" step="0.01" inputmode="decimal" value="{{ $oldText('reel_rate') }}" placeholder="e.g. 300 per Reel" data-enquiry-type-required="influencer both" @if($hasSocial) required @else disabled @endif>
                                    @error('reel_rate')<p class="enquiry-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </section>
                        <section class="enquiry-form-section" aria-labelledby="enquiry-video-title">
                            <h2 id="enquiry-video-title">Verify your profile</h2>
                            <div class="enquiry-field">
                                <label for="enquiry-video">Profile and audience verification video <span aria-hidden="true">*</span></label>
                                <input id="enquiry-video" name="verification_video" type="file" accept="video/mp4,video/webm,video/quicktime" data-enquiry-required="creator" @if($isCreator) required @endif aria-describedby="enquiry-video-help @error('verification_video') enquiry-video-error @enderror">
                                <p class="enquiry-help" id="enquiry-video-help">Upload a short screen recording showing your own platform profile and audience countries. MP4, WebM or MOV; up to {{ app(\App\Services\CreatorProofVideo::class)->maxUploadMb() }} MB and {{ app(\App\Services\CreatorProofVideo::class)->maxDurationSeconds() }} seconds. Only administrators can watch it.</p>
                                @error('verification_video')<p class="enquiry-error" id="enquiry-video-error">{{ $message }}</p>@enderror
                            </div>
                        </section>
                    </fieldset>

                    <div class="enquiry-field enquiry-currency">
                        <label for="enquiry-currency">Currency <span aria-hidden="true" data-enquiry-for="agency creator" @if($selectedKind === 'brand') hidden @endif>*</span></label>
                        <select id="enquiry-currency" name="currency" data-enquiry-required="agency creator" @if($selectedKind !== 'brand') required @endif>
                            @foreach($currencyOptions as $currency)<option value="{{ $currency }}" @selected(old('currency', 'EUR') === $currency)>{{ $currency }}</option>@endforeach
                        </select>
                        @error('currency')<p class="enquiry-error">{{ $message }}</p>@enderror
                    </div>
                    <label class="enquiry-consent" for="enquiry-age">
                        <input id="enquiry-age" name="age_confirmed" type="checkbox" value="1" required @checked(old('age_confirmed')) @error('age_confirmed') aria-invalid="true" @enderror>
                        <span>I confirm I am 18+ and agree to share these details with All In Streams for review.</span>
                    </label>
                    @error('age_confirmed')<p class="enquiry-error">{{ $message }}</p>@enderror
                    <button type="submit" class="enquiry-submit" data-enquiry-submit><span data-enquiry-submit-label>{{ $isCreator ? 'Submit my profile' : 'Create account' }}</span><x-ui-icon name="arrow" size="20"/></button>
                    <p class="enquiry-existing">Already a partner? <a href="/login">Sign in <span aria-hidden="true">↗</span></a></p>
                </form>
            @endif
        </div>
    </main>
</x-layout>
