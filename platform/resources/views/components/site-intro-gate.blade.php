@props(['eligible' => false])
<style>
    html[data-site-intro-pending]::before { content:''; position:fixed; inset:0; z-index:9999; background:#f5f6fa; }
</style>
<script data-navigate-once>
    (() => {
        if (window.__aisIntroBootstrapped) return;
        window.__aisIntroBootstrapped = true;
        let firstVisit = false;
        try {
            firstVisit = sessionStorage.getItem('ais.site-intro.seen') !== '1';
            sessionStorage.setItem('ais.site-intro.seen', '1');
        } catch { firstVisit = false; }
        let internalVisit = false;
        try { internalVisit = Boolean(document.referrer) && new URL(document.referrer).origin === location.origin; } catch {}
        const navigation = performance.getEntriesByType?.('navigation')?.[0];
        if (!@js($eligible) || !firstVisit || internalVisit || navigation?.type === 'back_forward') return;
        document.documentElement.setAttribute('data-site-intro-pending', '');
        window.__aisIntroFailsafe = setTimeout(() => {
            document.documentElement.removeAttribute('data-site-intro-pending');
            document.dispatchEvent(new Event('ais:site-intro-abort'));
            const intro = document.querySelector('[data-site-intro]');
            if (intro?.open) intro.close();
            intro?.remove();
        }, 10000);
        window.addEventListener('pagehide', () => {
            clearTimeout(window.__aisIntroFailsafe);
            document.documentElement.removeAttribute('data-site-intro-pending');
        }, { once: true });
        window.addEventListener('pageshow', event => {
            if (event.persisted) document.documentElement.removeAttribute('data-site-intro-pending');
        });
    })();
</script>
