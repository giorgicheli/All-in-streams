@php
    $recoveryEmail = old('email', $email ?? '');
    $recoveryEmail = is_string($recoveryEmail) ? $recoveryEmail : '';
@endphp
<x-layout title="Reset password" :sensitive="true">
<div class="public-auth-layout">
@include('site.navigation')
<main class="auth-page auth-page-centered password-recovery" aria-labelledby="password-reset-title"><div class="auth-main">
    <div class="auth-form password-recovery-form">
        <div class="auth-form-heading"><h1 id="password-reset-title">Set a new password.</h1>
        <p class="muted">Choose a password you haven’t used for this account.</p>
        </div>
        @if(session('status'))<div class="auth-notice" role="status">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="auth-notice auth-notice-error" role="alert">{{ session('error') }}</div>@endif
        @error('token')<div class="auth-notice auth-notice-error" role="alert">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('password.update') }}">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="form-field">
                <label for="reset-email">Email address</label>
                <input id="reset-email" name="email" type="email" value="{{ $recoveryEmail }}" autocomplete="username" required maxlength="255" @error('email') aria-invalid="true" aria-describedby="reset-email-error" @enderror>
                @error('email')<span class="field-error" id="reset-email-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="reset-password">New password</label>
                <input id="reset-password" name="password" type="password" autocomplete="new-password" placeholder="Create a new password" required minlength="10" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{10,}" title="Use at least 10 characters, including letters and a number." aria-describedby="reset-password-help @error('password') reset-password-error @enderror" @error('password') aria-invalid="true" @enderror>
                @error('password')<span class="field-error" id="reset-password-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-field">
                <label for="reset-password-confirmation">Confirm new password</label>
                <input id="reset-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat your new password" required minlength="10" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{10,}" title="Use at least 10 characters, including letters and a number." @error('password_confirmation') aria-invalid="true" aria-describedby="reset-password-confirmation-error" @enderror>
                @error('password_confirmation')<span class="field-error" id="reset-password-confirmation-error">{{ $message }}</span>@enderror
            </div>
            <p class="auth-recovery-hint" id="reset-password-help">Use at least 10 characters, including letters and a number.</p>
            <button type="submit" class="btn btn-primary btn-block">Save new password <x-ui-icon name="right" size="16"/></button>
        </form>
        <p class="auth-switch">Link expired? <a href="{{ route('password.request') }}">Request a new link ↗</a></p>
    </div>
</div></main>
@include('site.footer')
</div>
</x-layout>
