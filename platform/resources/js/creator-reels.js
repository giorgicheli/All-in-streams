const instances = new WeakMap();

export function initializeCreatorReels(root = document) {
    instances.get(root)?.();
    const cleanups = [...root.querySelectorAll('[data-creator-reels]')].map(mountCreatorReels);
    const cleanup = () => cleanups.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountCreatorReels(section) {
    const controller = new AbortController();
    const options = { signal: controller.signal };
    const videos = [...section.querySelectorAll('[data-reel-video]')];
    const wall = section.querySelector('[data-reel-wall]');
    const control = section.querySelector('[data-reel-control]');
    if (!wall || !control) return () => {};
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    const onscreen = new Set();
    let visible = false;
    let menuOpen = false;
    let paused = false;
    let optedIn = false;
    let disposed = false;
    let suspended = false;

    function unload(video) {
        video.pause();
        if (video.hasAttribute('src')) {
            video.removeAttribute('src');
            video.load();
        }
    }

    function preferenceLimited() { return reduced.matches || Boolean(connection?.saveData); }
    function playing() { return !paused && (!preferenceLimited() || optedIn); }

    function sync() {
        if (disposed) return;
        const allowed = visible && !menuOpen && !document.hidden && !suspended && playing();
        section.dataset.reelMoving = String(allowed && !preferenceLimited());
        control.setAttribute('aria-pressed', String(playing()));
        control.setAttribute('aria-label', playing() ? 'Pause video previews' : 'Play video previews');
        control.querySelector('[data-reel-control-label]').textContent = playing() ? 'Pause previews' : 'Play previews';
        control.querySelector('[data-reel-pause]').toggleAttribute('hidden', !playing());
        control.querySelector('[data-reel-play]').toggleAttribute('hidden', playing());
        videos.forEach(video => {
            if (!allowed || !onscreen.has(video)) { unload(video); return; }
            if (!video.hasAttribute('src')) {
                video.src = video.dataset.src;
                video.muted = video.defaultMuted = true;
            }
            if (video.paused) video.play().catch(() => {});
        });
    }

    const sectionObserver = new IntersectionObserver(entries => {
        visible = entries[0].isIntersecting;
        sync();
    }, { threshold: .08 });
    const videoObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) onscreen.add(entry.target);
            else onscreen.delete(entry.target);
        });
        sync();
    }, { root: wall, threshold: .1 });
    sectionObserver.observe(wall);
    videos.forEach(video => videoObserver.observe(video));
    control.addEventListener('click', () => {
        if (playing()) paused = true;
        else { paused = false; optedIn = true; }
        sync();
    }, options);
    reduced.addEventListener('change', sync, options);
    connection?.addEventListener('change', sync, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); sync(); }, options);
    window.addEventListener('pagehide', event => {
        suspended = true;
        sync();
        if (!event.persisted) cleanup();
    }, options);
    window.addEventListener('pageshow', () => { suspended = false; sync(); }, options);
    section.dataset.reelReady = 'true';
    control.hidden = false;
    sync();

    function cleanup() {
        if (disposed) return;
        disposed = true;
        controller.abort();
        sectionObserver.disconnect();
        videoObserver.disconnect();
        videos.forEach(unload);
        control.hidden = true;
        delete section.dataset.reelReady;
        delete section.dataset.reelMoving;
    }
    return cleanup;
}
