<x-portal-layout title="Account settings">
    <div class="page-heading"><div><span class="eyebrow">YOUR PRIVATE CONTACT DETAILS</span><h1>Account settings</h1><p>Stay in touch with your All In Streams account manager.</p></div></div>
    <form method="POST" action="{{ route('account.settings.update') }}" class="panel wizard-panel">@csrf
        <div class="form-field"><label for="account-telegram">Telegram username or profile link</label><input id="account-telegram" name="telegram" value="{{ old('telegram', auth()->user()->telegram) }}" placeholder="@your_username or https://t.me/your_username" maxlength="100" required autocomplete="off">@error('telegram')<p class="field-error" role="alert">{{ $message }}</p>@enderror<p class="field-help">Required for every account type. Your contact is private to the All In Streams team.</p></div>
        <button class="btn btn-primary mt-5">Save contact details <x-ui-icon name="check" size="16"/></button>
    </form>
</x-portal-layout>
