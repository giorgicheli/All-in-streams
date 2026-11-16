<x-layout :title="$rejected ? 'Account review' : 'Account awaiting verification'">
    <main class="min-h-svh flex flex-col px-6 py-8">
        <div class="w-full max-w-2xl mx-auto"><x-brand/></div>
        <section class="w-full max-w-xl m-auto py-16" aria-labelledby="verification-title">
            <span class="eyebrow">Account review</span>
            <h1 id="verification-title" class="text-4xl mt-5">{{ $rejected ? 'Your account has not been approved.' : 'Your account is awaiting verification.' }}</h1>
            <p class="muted mt-6">{{ $rejected ? 'The All In Streams team has reviewed your details. You can update your contact information and check here for a new decision.' : 'The All In Streams team will review your details and activate your partner workspace after approval.' }}</p>
            <p class="muted mt-4">Signed in as {{ auth()->user()->email }}.</p>
            <div class="flex flex-wrap gap-3 mt-8">
                <a href="{{ route('account.pending') }}" class="btn btn-primary">Check status <x-ui-icon name="right" size="16"/></a>
                <a href="{{ route('account.settings') }}" class="btn btn-secondary">Account settings</a>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button type="submit" class="text-link">Sign out <x-ui-icon name="logout" size="15"/></button>
            </form>
        </section>
    </main>
</x-layout>