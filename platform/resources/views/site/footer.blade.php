<footer class="site-footer{{ ($site['about']['enabled'] ?? false) ? ' site-footer-has-intro' : '' }}" id="about"><div class="footer-ambient" aria-hidden="true"></div><div class="container">
    @if($site['about']['enabled'] ?? false)
        <section class="footer-intro" aria-labelledby="footer-about-title">
            <div><span class="eyebrow">{{ $site['about']['eyebrow'] }}</span>@if($footerHomepageHeading ?? false)<h1 class="footer-about-title" id="footer-about-title">{{ $site['about']['title'] }}</h1>@else<h2 class="footer-about-title" id="footer-about-title">{{ $site['about']['title'] }}</h2>@endif</div>
            <p class="content-lines">{{ $site['about']['description'] }}</p>
        </section>
    @endif
    <div class="footer-top"><x-brand :home-url="($lightPreview ?? false) ? route('home.white') : '/'"/><a href="/login" class="text-link">{{ $site['footer']['sign_in_label'] }} <x-ui-icon name="arrow" size="14"/></a></div>
    <div class="footer-bottom"><span>&copy; {{ date('Y') }} {{ $site['brand_name'] }}. {{ $site['footer']['copyright'] }}</span><nav class="footer-legal" aria-label="Footer"><a href="{{ route('contact.create') }}">{{ $site['footer']['contact_label'] ?? 'Contact' }}</a><a href="{{ route('privacy') }}">{{ $site['footer']['privacy_label'] ?? 'Privacy policy' }}</a><a href="{{ route('terms') }}">{{ $site['footer']['terms_label'] ?? 'Terms & conditions' }}</a></nav></div>
</div></footer>
