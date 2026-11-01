@php $copy = $site['auth']['login']; $labels = $site['auth']['labels']; @endphp
<x-layout :title="$copy['page_title']">
<div class="auth-page"><x-auth-story/><main class="auth-main">
    <div class="auth-form" x-data="{email:{{ Js::from(old('email','')) }},password:''}">
        <a href="/" class="auth-back"><x-ui-icon name="left" size="14"/> {{ $site['auth']['back_label'] }}</a>
        <span class="eyebrow">{{ $copy['eyebrow'] }}</span><h2>{{ $copy['title'] }}</h2><p class="muted">{{ $copy['description'] }}</p>
        <form method="POST" action="/login">@csrf
            <div class="form-field"><label for="email">{{ $labels['email'] }}</label><input id="email" name="email" type="email" x-model="email" autocomplete="username" placeholder="you@company.com" required autofocus>@error('email')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="form-field"><label for="password">{{ $labels['password'] }}</label><input id="password" name="password" type="password" x-model="password" autocomplete="current-password" placeholder="{{ $labels['password'] }}" required>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
            <label class="field-help flex items-center gap-2 mb-6"><input type="checkbox" name="remember" class="accent-lime-300"> {{ $copy['remember_label'] }}</label>
            <button class="btn btn-primary btn-block">{{ $copy['submit_label'] }} <x-ui-icon name="right" size="16"/></button>
        </form>
        @if(app()->environment('local'))
            <div class="auth-divider">EXPLORE THE LOCAL DEMO</div><div class="demo-accounts">
            @foreach([['brand','briefcase','Business'],['creator','user','Creator'],['admin','shield','Admin']] as [$role,$icon,$label])<button type="button" @click="email='{{ $role==='brand'?'brand':$role }}@allinstreams.test';password='StreamDemo!2026'"><x-ui-icon :name="$icon"/>{{ $label }}</button>@endforeach
            </div><p class="field-help text-center mt-3">Choose a demo account, then select Sign in.</p>
        @endif
        <p class="auth-switch">{{ $copy['register_prompt'] }} <a href="/register">{{ $copy['register_label'] }} ↗</a></p>
    </div>
</main></div>
</x-layout>
