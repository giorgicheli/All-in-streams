<footer class="site-footer"><div class="container">
    <div class="footer-top"><x-brand :home-url="($lightPreview ?? false) ? route('home.white') : '/'"/><p>{{ $site['footer']['tagline'] }}</p><a href="/login" class="text-link">{{ $site['footer']['sign_in_label'] }} <x-ui-icon name="arrow" size="14"/></a></div>
    <div class="footer-bottom"><span>© {{ date('Y') }} {{ $site['brand_name'] }}. {{ $site['footer']['copyright'] }}</span><nav class="footer-legal" aria-label="Legal"><a href="{{ route('privacy') }}">{{ $site['footer']['privacy_label'] ?? 'Privacy policy' }}</a><a href="{{ route('terms') }}">{{ $site['footer']['terms_label'] ?? 'Terms & conditions' }}</a></nav></div>
</div></footer>
