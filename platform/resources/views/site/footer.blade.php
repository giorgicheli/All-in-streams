<footer class="site-footer"><div class="container">
    <div class="footer-top"><x-brand/><p>{{ $site['footer']['tagline'] }}</p><a href="/login" class="text-link">{{ $site['footer']['sign_in_label'] }} <x-ui-icon name="arrow" size="14"/></a></div>
    <div class="footer-bottom"><span>© {{ date('Y') }} {{ $site['brand_name'] }}. {{ $site['footer']['copyright'] }}</span><span><x-ui-icon name="globe" size="12"/> {{ $site['footer']['statement'] }}</span></div>
    <p class="asset-credit">Flags designed by <a href="https://www.flaticon.com/authors/freepik" target="_blank" rel="noopener noreferrer">Freepik</a> from <a href="https://www.flaticon.com/packs/international-flags" target="_blank" rel="noopener noreferrer">Flaticon</a>.</p>
</div></footer>
