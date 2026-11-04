@php
    $copy = $site['auth']['register'];
    $labels = $site['auth']['labels'];
    $role = old('role', in_array(request('role'), ['creator', 'agency'], true) ? request('role') : (request('account_type') === 'agency' ? 'agency' : 'business'));
    $kind = $role === 'creator' ? 'creator' : ($role === 'agency' ? 'agency' : old('account_type', 'business'));
@endphp
<x-layout :title="$copy['page_title']">
    <div class="auth-page">
        <x-auth-story/>
        <main class="auth-main">
            <div class="auth-form" x-data="{kind:{{ Js::from($kind) }}, creatorType:{{ Js::from(old('creator_type', request('creator_type') === 'influencer' ? 'influencer' : 'streamer')) }}}">
                <a href="/" class="auth-back"><x-ui-icon name="left" size="14"/> {{ $site['auth']['back_label'] }}</a>
                <span class="eyebrow">{{ $copy['eyebrow'] }}</span>
                <h2>{{ $copy['title'] }}</h2>
                <p class="muted">{{ $copy['description'] }}</p>
                <form method="POST" action="/register" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}" :value="kind">
                    <input type="hidden" name="account_type" value="{{ $kind === 'creator' ? '' : $kind }}" :value="kind === 'creator' ? '' : kind">
                    <div class="choice-grid mb-6">
                        @foreach(['business' => ['briefcase', $labels['company_choice']], 'agency' => ['briefcase', $labels['agency_choice']], 'creator' => ['user', $labels['creator_choice']]] as $value => [$icon, $label])
                            <button type="button" class="choice-label" :style="kind === '{{ $value }}' ? 'border-color:#8bb54f;color:#c5f65c' : ''" @click="kind='{{ $value }}'"><x-ui-icon :name="$icon" size="15"/> {{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="form-field">
                        <label for="name">{{ $labels['name'] }}</label>
                        <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="{{ $labels['name'] }}" required maxlength="120">
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-field" x-show="kind !== 'creator'">
                        <label for="company" x-text="kind === 'agency' ? {{ Js::from($labels['agency']) }} : {{ Js::from($labels['company']) }}">{{ $labels['company'] }}</label>
                        <input id="company" name="company" value="{{ old('company') }}" autocomplete="organization" placeholder="{{ $labels['company'] }}" maxlength="180">
                        @error('company')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div x-show="kind === 'creator'" x-cloak>
                        <input type="hidden" name="creator_type" :value="creatorType" :disabled="kind !== 'creator'">
                        <fieldset class="form-field">
                            <legend class="field-label">Creator type</legend>
                            <div class="choice-grid">
                                <button type="button" class="choice-label" :style="creatorType === 'streamer' ? 'border-color:#8bb54f;color:#c5f65c' : ''" @click="creatorType='streamer'">Streamer · Twitch / Kick</button>
                                <button type="button" class="choice-label" :style="creatorType === 'influencer' ? 'border-color:#8bb54f;color:#c5f65c' : ''" @click="creatorType='influencer'">Influencer · Social media</button>
                            </div>
                            @error('creator_type')<span class="field-error">{{ $message }}</span>@enderror
                        </fieldset>
                        <div x-show="creatorType === 'streamer'">
                        <div class="form-field">
                            <label for="twitch_url">{{ $labels['twitch'] }}</label>
                            <input id="twitch_url" name="twitch_url" type="url" value="{{ old('twitch_url') }}" placeholder="https://www.twitch.tv/your_channel" maxlength="255" :disabled="kind !== 'creator' || creatorType !== 'streamer'">
                            @error('twitch_url')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-field">
                            <label for="kick_url">{{ $labels['kick'] }}</label>
                            <input id="kick_url" name="kick_url" type="url" value="{{ old('kick_url') }}" placeholder="https://kick.com/your_channel" maxlength="255" :disabled="kind !== 'creator' || creatorType !== 'streamer'">
                            @error('kick_url')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <p class="field-help mb-5">At least one Twitch or Kick channel is required. Add both if you use both platforms.</p>
                        </div>
                        <div x-show="creatorType === 'influencer'">
                            @foreach(\App\Rules\SocialProfileUrl::FIELDS as $field => $platform)
                            <div class="form-field">
                                <label for="{{ $field }}">{{ $platform }} profile</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="url" value="{{ old($field) }}" placeholder="https://{{ ['Facebook' => 'www.facebook.com/your.profile', 'Instagram' => 'www.instagram.com/your_profile', 'YouTube' => 'www.youtube.com/@your_channel', 'TikTok' => 'www.tiktok.com/@your_profile'][$platform] }}" maxlength="255" :disabled="kind !== 'creator' || creatorType !== 'influencer'">
                                @error($field)<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                            @endforeach
                            <p class="field-help mb-5">Add at least one social profile. Your links are visible only to you and the All In Streams team.</p>
                        </div>
                        <fieldset class="form-field">
                            <legend class="field-label">Which countries does your audience come from?</legend>
                            <div class="choice-grid" style="max-height:180px;overflow-y:auto">
                                @foreach(\App\Models\CreatorProfile::AUDIENCE_COUNTRIES as $geo)
                                <label class="choice-label"><input type="checkbox" name="audience_geos[]" value="{{ $geo }}" @checked(in_array($geo, old('audience_geos', []), true)) :disabled="kind !== 'creator'"><span>{{ $geo }}</span></label>
                                @endforeach
                            </div>
                            @error('audience_geos')<span class="field-error">Choose at least one audience country.</span>@enderror
                            @error('audience_geos.*')<span class="field-error">{{ $message }}</span>@enderror
                        </fieldset>
                        <div class="form-field">
                            <label for="verification_video">Profile and audience verification video</label>
                            <input id="verification_video" name="verification_video" type="file" accept="video/mp4,video/webm,video/quicktime" :required="kind === 'creator'" :disabled="kind !== 'creator'">
                            <p class="field-help">Upload a short screen recording showing your own platform profile and audience countries. MP4, WebM or MOV; up to {{ app(\App\Services\CreatorProofVideo::class)->maxUploadMb() }} MB and {{ app(\App\Services\CreatorProofVideo::class)->maxDurationSeconds() }} seconds. Only administrators can watch it.</p>
                            @error('verification_video')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="telegram">Telegram</label>
                        <input id="telegram" name="telegram" value="{{ old('telegram') }}" placeholder="@your_username" autocomplete="off" required maxlength="100">
                        <p class="field-help">Required for every account. Your contact stays private with our team.</p>
                        @error('telegram')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-field">
                        <label for="email">{{ $labels['email'] }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" required maxlength="255">
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-grid">
                        <div class="form-field"><label for="password">{{ $labels['password'] }}</label><input id="password" name="password" type="password" autocomplete="new-password" placeholder="{{ $labels['password'] }}" required minlength="10"></div>
                        <div class="form-field"><label for="password_confirmation">{{ $labels['password_confirmation'] }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="{{ $labels['password_confirmation'] }}" required minlength="10"></div>
                    </div>
                    @error('password')<p class="field-error mb-4">{{ $message }}</p>@enderror
                    @error('role')<p class="field-error mb-4">{{ $message }}</p>@enderror
                    @error('account_type')<p class="field-error mb-4">{{ $message }}</p>@enderror
                    <p class="field-help mb-5">{{ $copy['password_help'] }}</p>
                    <button class="btn btn-primary btn-block">{{ $copy['submit_label'] }} <x-ui-icon name="right" size="16"/></button>
                </form>
                <p class="auth-switch">{{ $copy['login_prompt'] }} <a href="/login">{{ $copy['login_label'] }} ↗</a></p>
            </div>
        </main>
    </div>
</x-layout>
