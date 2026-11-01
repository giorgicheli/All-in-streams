@php
    $role = old('role', request('role') === 'creator' ? 'creator' : 'business');
    $kind = $role === 'creator' ? 'creator' : old('account_type', request('account_type') === 'agency' ? 'agency' : 'business');
@endphp
<x-layout title="Join All In Streams">
    <div class="auth-page">
        <x-auth-story/>
        <main class="auth-main">
            <div class="auth-form" x-data="{kind:{{ Js::from($kind) }}}">
                <a href="/" class="auth-back"><x-ui-icon name="left" size="14"/> Back to All In Streams</a>
                <span class="eyebrow">LET’S MAKE YOUR NEXT MOVE</span>
                <h2>Let’s go all in.</h2>
                <p class="muted">A new partnership starts with you.</p>
                <form method="POST" action="/register">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}" :value="kind === 'creator' ? 'creator' : 'business'">
                    <input type="hidden" name="account_type" value="{{ $kind === 'creator' ? '' : $kind }}" :value="kind === 'creator' ? '' : kind">
                    <div class="choice-grid mb-6">
                        @foreach(['business' => ['briefcase', 'Company'], 'agency' => ['briefcase', 'Agency'], 'creator' => ['user', 'Creator']] as $value => [$icon, $label])
                            <button type="button" class="choice-label" :style="kind === '{{ $value }}' ? 'border-color:#8bb54f;color:#c5f65c' : ''" @click="kind='{{ $value }}'"><x-ui-icon :name="$icon" size="15"/> {{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="form-field">
                        <label for="name">Your name</label>
                        <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" placeholder="Full name" required maxlength="120">
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-field" x-show="kind !== 'creator'">
                        <label for="company" x-text="kind === 'agency' ? 'Agency name' : 'Company name'">Company name</label>
                        <input id="company" name="company" value="{{ old('company') }}" autocomplete="organization" placeholder="Your company or agency name" maxlength="180">
                        @error('company')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div x-show="kind === 'creator'" x-cloak>
                        <div class="form-field">
                            <label for="twitch_url">Twitch channel</label>
                            <input id="twitch_url" name="twitch_url" type="url" value="{{ old('twitch_url') }}" placeholder="https://www.twitch.tv/your_channel" maxlength="255" :disabled="kind !== 'creator'">
                            @error('twitch_url')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-field">
                            <label for="kick_url">Kick channel</label>
                            <input id="kick_url" name="kick_url" type="url" value="{{ old('kick_url') }}" placeholder="https://kick.com/your_channel" maxlength="255" :disabled="kind !== 'creator'">
                            @error('kick_url')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <p class="field-help mb-5">Add at least one channel. Stream on both? Add both links. Your channel details stay private until our team authorizes an introduction.</p>
                    </div>
                    <div class="form-field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" required maxlength="255">
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-grid">
                        <div class="form-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" placeholder="Create a password" required minlength="10"></div>
                        <div class="form-field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat password" required minlength="10"></div>
                    </div>
                    @error('password')<p class="field-error mb-4">{{ $message }}</p>@enderror
                    @error('role')<p class="field-error mb-4">{{ $message }}</p>@enderror
                    @error('account_type')<p class="field-error mb-4">{{ $message }}</p>@enderror
                    <p class="field-help mb-5">Use at least 10 characters, including letters and a number.</p>
                    <button class="btn btn-primary btn-block">Create account <x-ui-icon name="right" size="16"/></button>
                </form>
                <p class="auth-switch">Already a partner? <a href="/login">Sign in ↗</a></p>
            </div>
        </main>
    </div>
</x-layout>