@php
    $copy = $site['auth']['register'];
    $labels = $site['auth']['labels'];
    $role = old('role', request('role') === 'creator' ? 'creator' : 'business');
    $kind = $role === 'creator' ? 'creator' : old('account_type', request('account_type') === 'agency' ? 'agency' : 'business');
@endphp
<x-layout :title="$copy['page_title']">
    <div class="auth-page">
        <x-auth-story/>
        <main class="auth-main">
            <div class="auth-form" x-data="{kind:{{ Js::from($kind) }}}">
                <a href="/" class="auth-back"><x-ui-icon name="left" size="14"/> {{ $site['auth']['back_label'] }}</a>
                <span class="eyebrow">{{ $copy['eyebrow'] }}</span>
                <h2>{{ $copy['title'] }}</h2>
                <p class="muted">{{ $copy['description'] }}</p>
                <form method="POST" action="/register">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}" :value="kind === 'creator' ? 'creator' : 'business'">
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
                        <div class="form-field">
                            <label for="twitch_url">{{ $labels['twitch'] }}</label>
                            <input id="twitch_url" name="twitch_url" type="url" value="{{ old('twitch_url') }}" placeholder="https://www.twitch.tv/your_channel" maxlength="255" :disabled="kind !== 'creator'">
                            @error('twitch_url')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-field">
                            <label for="kick_url">{{ $labels['kick'] }}</label>
                            <input id="kick_url" name="kick_url" type="url" value="{{ old('kick_url') }}" placeholder="https://kick.com/your_channel" maxlength="255" :disabled="kind !== 'creator'">
                            @error('kick_url')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <p class="field-help mb-5">{{ $copy['channel_help'] }}</p>
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
