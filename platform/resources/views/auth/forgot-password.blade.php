@php
    $recoveryEmail = old('email', '');
    $recoveryEmail = is_string($recoveryEmail) ? $recoveryEmail : '';
@endphp
<x-layout title="Forgot password" :sensitive="true">
<div class="public-auth-layout">
@include('site.navigation')
<main class="auth-page auth-page-centered password-recovery" aria-labelledby="password-request-title"><div class="auth-main">
    <div class="auth-form password-recovery-form">
        <div class="auth-form-heading"><h1 id="password-request-title">Forgot your password?</h1>
        <p class="muted">Enter your account email and we’ll send you a link to choose a new password.</p>
        </div>
        @if(session('status'))<div class="auth-notice" role="status">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="auth-notice auth-notice-error" role="alert">{{ session('error') }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">@csrf
            <div class="form-field">
                <label for="recovery-email">Email address</label>
                <input id="recovery-email" name="email" type="email" value="{{ $recoveryEmail }}" autocomplete="email" placeholder="you@example.com" required maxlength="255" autofocus @error('email') aria-invalid="true" aria-describedby="recovery-email-error" @enderror>
                @error('email')<span class="field-error" id="recovery-email-error">{{ $message }}</span>@enderror
            </div>
            <p class="auth-recovery-hint">If an account matches this email, you’ll receive a reset link. Check your inbox and spam folder.</p>
            <button type="submit" class="btn btn-primary btn-block">Send reset link <x-ui-icon name="right" size="16"/></button>
        </form>
        <p class="auth-switch">Remember your password? <a href="{{ route('login') }}">Sign in ↗</a></p>
    </div>
</div></main>
@include('site.footer')
</div>
</x-layout>
