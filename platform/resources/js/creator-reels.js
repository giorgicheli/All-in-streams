const instances = new WeakMap();
const autoDuration = 8500;

export function initializeCreatorReels(root = document) {
    instances.get(root)?.();
    const cleanups = [...root.querySelectorAll('[data-creator-reels]')].map(mountCreatorReels);
    const cleanup = () => cleanups.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountCreatorReels(section) {
    const ribbon = section.querySelector('[data-reel-ribbon]');
    const panels = [...section.querySelectorAll('[data-reel-panel]')];
    const previous = section.querySelector('[data-reel-previous]');
    const next = section.querySelector('[data-reel-next]');
    const announcement = section.querySelector('[data-reel-announcement]');
    if (!ribbon || !panels.length || !previous || !next) return () => {};
    const videos = panels.map(panel => panel.querySelector('[data-reel-video]'));
    const events = new AbortController();
    const options = { signal: events.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    let visible = false;
    let menuOpen = false;
    let hovering = false;
    let focused = section.contains(document.activeElement);
    let touching = false;
    let suspended = false;
    let disposed = false;
    let timer = 0;
    let motionFrame = 0;
    let scrollFrame = 0;
    let settleTimer = 0;
    let direction = 1;
    let dragging = null;
    let suppressClickUntil = 0;
    const failedSources = new Set();
    const limited = () => reduced.matches || Boolean(connection?.saveData);
    const playing = () => !disposed && visible && !menuOpen && !document.hidden && !suspended && !limited();
    const advancing = () => playing() && !hovering && !focused && !touching && !dragging && maximum() > 2;
    const maximum = () => Math.max(0, ribbon.scrollWidth - ribbon.clientWidth);
    const stepSize = () => panels[0].getBoundingClientRect().width + (parseFloat(getComputedStyle(ribbon).columnGap) || 0);

    function unload(video) {
        video.pause();
        video.classList.remove('is-ready');
        if (video.hasAttribute('src')) { video.removeAttribute('src'); video.load(); }
    }

    function syncMedia() {
        const bounds = ribbon.getBoundingClientRect();
        const onscreen = playing() ? panels.map((panel, index) => ({ index, rect: panel.getBoundingClientRect() })).filter(item => item.rect.right > bounds.left + 3 && item.rect.left < bounds.right - 3).slice(0, 4).map(item => item.index) : [];
        videos.forEach((video, index) => {
            if (!onscreen.includes(index) || failedSources.has(video.dataset.src)) { unload(video); return; }
            if (!video.hasAttribute('src')) { video.muted = video.defaultMuted = true; video.src = video.dataset.src; video.load(); }
            if (video.paused) video.play().catch(() => {});
        });
        section.dataset.reelPlaying = String(playing());
        section.dataset.reelLoaded = String(onscreen.length);
    }

    function updatePosition() {
        const position = ribbon.scrollLeft;
        previous.disabled = position <= 2;
        next.disabled = position >= maximum() - 2;
        section.dataset.reelSelected = String(Math.min(panels.length, Math.max(1, Math.round(position / stepSize()) + 1)));
    }

    function stopMotion() {
        cancelAnimationFrame(motionFrame);
        motionFrame = 0;
        ribbon.classList.remove('is-advancing');
    }

    function schedule() {
        clearTimeout(timer);
        timer = 0;
        section.dataset.reelMoving = String(advancing());
        if (!advancing()) return;
        timer = setTimeout(() => {
            timer = 0;
            if (!advancing()) return;
            if (ribbon.scrollLeft >= maximum() - 2) direction = -1;
            if (ribbon.scrollLeft <= 2) direction = 1;
            move(direction, true);
        }, autoDuration);
    }

    function move(by, automatic = false) {
        stopMotion();
        clearTimeout(timer);
        const start = ribbon.scrollLeft;
        const target = Math.max(0, Math.min(maximum(), start + by * stepSize()));
        if (reduced.matches) { ribbon.scrollLeft = target; updatePosition(); syncMedia(); schedule(); return; }
        ribbon.classList.add('is-advancing');
        const duration = automatic ? 1650 : 750;
        let started;
        const tick = time => {
            motionFrame = 0;
            if (disposed || !visible || menuOpen || document.hidden || suspended) { stopMotion(); return; }
            started ??= time;
            const progress = Math.min(1, (time - started) / duration);
            const eased = progress * progress * (3 - 2 * progress);
            ribbon.scrollLeft = start + (target - start) * eased;
            if (progress < 1) motionFrame = requestAnimationFrame(tick);
            else { stopMotion(); updatePosition(); syncMedia(); schedule(); }
        };
        motionFrame = requestAnimationFrame(tick);
        if (!automatic && announcement) announcement.textContent = by > 0 ? 'Next creator previews' : 'Previous creator previews';
    }

    function sync() {
        if (disposed) return;
        if (!playing()) stopMotion();
        updatePosition();
        syncMedia();
        schedule();
    }

    previous.addEventListener('click', () => move(-1), options);
    next.addEventListener('click', () => move(1), options);
    ribbon.addEventListener('scroll', () => {
        if (scrollFrame) return;
        scrollFrame = requestAnimationFrame(() => { scrollFrame = 0; if (!disposed) { updatePosition(); syncMedia(); } });
        clearTimeout(settleTimer);
        settleTimer = setTimeout(() => { if (!motionFrame && !disposed) schedule(); }, 160);
    }, { ...options, passive: true });
    ribbon.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'Home' || event.key === 'End') {
            stopMotion();
            ribbon.scrollTo({ left: event.key === 'Home' ? 0 : maximum(), behavior: reduced.matches ? 'instant' : 'smooth' });
        } else move(event.key === 'ArrowRight' ? 1 : -1);
    }, options);
    ribbon.addEventListener('focusin', event => {
        const panel = event.target.closest('[data-reel-panel]');
        if (!panel) return;
        const bounds = ribbon.getBoundingClientRect();
        const rect = panel.getBoundingClientRect();
        if (rect.left < bounds.left || rect.right > bounds.right) ribbon.scrollTo({ left: ribbon.scrollLeft + rect.left - bounds.left, behavior: reduced.matches ? 'instant' : 'smooth' });
    }, options);
    section.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') { hovering = true; stopMotion(); schedule(); } }, options);
    section.addEventListener('pointerleave', event => { if (event.pointerType === 'mouse') { hovering = false; schedule(); } }, options);
    section.addEventListener('focusin', () => { focused = true; stopMotion(); schedule(); }, options);
    section.addEventListener('focusout', event => { if (!section.contains(event.relatedTarget)) { focused = false; schedule(); } }, options);
    ribbon.addEventListener('pointerdown', event => {
        stopMotion();
        if (event.pointerType === 'touch') touching = true;
        else if (event.button === 0) dragging = { x: event.clientX, scroll: ribbon.scrollLeft, pointerId: event.pointerId, moved: false };
        schedule();
    }, { ...options, passive: true });
    ribbon.addEventListener('pointermove', event => {
        if (!dragging || event.pointerType !== 'mouse') return;
        const distance = event.clientX - dragging.x;
        if (Math.abs(distance) > 6 && !dragging.moved) { dragging.moved = true; ribbon.setPointerCapture(event.pointerId); ribbon.classList.add('is-dragging'); }
        if (dragging.moved) { event.preventDefault(); ribbon.scrollLeft = dragging.scroll - distance; suppressClickUntil = performance.now() + 400; }
    }, options);
    const release = () => {
        touching = false;
        if (dragging?.moved && ribbon.hasPointerCapture(dragging.pointerId)) ribbon.releasePointerCapture(dragging.pointerId);
        dragging = null;
        ribbon.classList.remove('is-dragging');
        schedule();
    };
    window.addEventListener('pointerup', release, { ...options, passive: true });
    window.addEventListener('pointercancel', release, { ...options, passive: true });
    ribbon.addEventListener('click', event => { if (performance.now() < suppressClickUntil) { event.preventDefault(); event.stopPropagation(); suppressClickUntil = 0; } }, { ...options, capture: true });
    ribbon.addEventListener('dragstart', event => event.preventDefault(), options);
    videos.forEach(video => {
        video.addEventListener('loadeddata', () => { if (playing() && video.hasAttribute('src')) video.classList.add('is-ready'); }, options);
        video.addEventListener('error', () => { failedSources.add(video.dataset.src); unload(video); }, options);
    });
    const observer = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; sync(); }, { threshold: .08 });
    observer.observe(ribbon);
    const resize = new ResizeObserver(sync);
    resize.observe(ribbon);
    reduced.addEventListener('change', sync, options);
    connection?.addEventListener?.('change', sync, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); sync(); }, options);
    document.addEventListener('livewire:navigating', cleanup, options);
    window.addEventListener('pagehide', event => { suspended = true; sync(); if (!event.persisted) cleanup(); }, options);
    window.addEventListener('pageshow', () => { suspended = false; sync(); }, options);
    section.dataset.reelReady = 'true';
    sync();

    function cleanup() {
        if (disposed) return;
        disposed = true;
        events.abort();
        observer.disconnect();
        resize.disconnect();
        clearTimeout(timer);
        clearTimeout(settleTimer);
        cancelAnimationFrame(scrollFrame);
        stopMotion();
        videos.forEach(unload);
        ribbon.classList.remove('is-dragging');
        delete section.dataset.reelReady;
        delete section.dataset.reelPlaying;
        delete section.dataset.reelMoving;
        delete section.dataset.reelLoaded;
    }
    return cleanup;
}
