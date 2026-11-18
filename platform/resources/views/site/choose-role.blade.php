@php $entry = $site['enquiry_entry']; @endphp
<x-layout :title="$entry['title']">
    <div class="public-auth-layout">
    @include('site.navigation')
    <main class="partner-enquiry enquiry-role-page">
        <div class="enquiry-shell">
            <div class="enquiry-intro"><p class="enquiry-eyebrow">{{ $entry['eyebrow'] }}</p><h1>{{ $entry['title'] }}</h1><p>{{ $entry['description'] }}</p></div>
            <div class="enquiry-role-cards" aria-label="Choose your role">
                @foreach($entry['cards'] as $card)
                    <a class="enquiry-role-card" href="{{ $card['url'] }}" data-role-choice>
                        <span class="enquiry-role-icon"><x-ui-icon :name="$card['icon']" size="29"/></span>
                        <h2>{{ $card['title'] }}</h2><p>{{ $card['description'] }}</p><span class="enquiry-role-arrow"><x-ui-icon name="arrow" size="20"/></span>
                    </a>
                @endforeach
            </div>
            <p class="enquiry-existing">Already a partner? <a href="/login">Sign in ↗</a></p>
        </div>
    </main>
    @include('site.footer')
    </div>
</x-layout>
