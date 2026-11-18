const instances = new WeakMap();
const speed = 18;

export function initializeCreatorReels(root = document) {
    instances.get(root)?.();
    const cleanups = [...root.querySelectorAll('[data-creator-reels]')].map(mountCreatorReels);
    const cleanup = () => cleanups.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountCreatorReels(section) {
    const rows = [...section.querySelectorAll('[data-reel-row]')].map(element => ({
        element, cards: [...element.querySelectorAll('[data-reel-card]')], originals: [...element.querySelectorAll('[data-reel-panel]')],
        direction: element.dataset.reelRow === 'reverse' ? -1 : 1, span: 0, position: 0, expected: null,
        visible: false, loop: false, hovering: false, focused: element.contains(document.activeElement), touching: false, dragging: null, suppressUntil: 0, resumeAt: 0, settleTimer: 0,
    }));
    if (!rows.length) return () => {};
    const events = new AbortController();
    const options = { signal: events.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    const failed = new Set();
    let disposed = false;
    let menuOpen = false;
    let suspended = false;
    let frame = 0;
    let lastTime = 0;
    let lastMedia = 0;
    const limited = () => reduced.matches || Boolean(connection?.saveData);
    const playing = row => !disposed && row.visible && !menuOpen && !document.hidden && !suspended && !limited();
    const moving = row => playing(row) && row.loop && !row.hovering && !row.focused && !row.touching && !row.dragging && performance.now() >= row.resumeAt;
    const allVideos = rows.flatMap(row => row.cards.map(card => card.querySelector('[data-reel-video]')));

    function unload(video) {
        video.pause();
        video.classList.remove('is-ready');
        if (video.hasAttribute('src')) { video.removeAttribute('src'); video.load(); }
    }

    function paintPosition(row) {
        if (!row.loop || row.span <= 0) return;
        while (row.position < row.span) row.position += row.span;
        while (row.position >= row.span * 2) row.position -= row.span;
        const whole = Math.floor(row.position);
        row.expected = whole;
        row.element.scrollLeft = whole;
        // Preserve fractional motion rather than rounding slow scrolling down
        // to one-pixel steps. Native scrolling still owns all touch gestures.
        row.element.style.setProperty('--reel-shift', `${whole - row.position}px`);
        row.element.dataset.reelOffset = row.position.toFixed(2);
    }

    function configureRows() {
        rows.forEach(row => {
            const card = row.originals[0];
            if (!card) return;
            const oldSpan = row.span;
            const oldOffset = row.loop && oldSpan ? ((row.position % oldSpan) + oldSpan) % oldSpan / oldSpan : 0;
            row.loop = row.originals.length >= 3 && !limited();
            row.element.dataset.reelLoop = String(row.loop);
            row.element.style.setProperty('--reel-shift', '0px');
            const gap = parseFloat(getComputedStyle(row.element).columnGap) || 0;
            row.span = row.originals.length * (card.getBoundingClientRect().width + gap);
            if (row.loop) {
                row.position = row.span * (1 + oldOffset);
                paintPosition(row);
            } else {
                row.position = row.element.scrollLeft;
                row.expected = null;
            }
        });
    }

    function syncMedia() {
        const candidates = rows.flatMap(row => {
            if (!playing(row)) return [];
            const bounds = row.element.getBoundingClientRect();
            return row.cards.map(card => {
                const rect = card.getBoundingClientRect();
                const intersection = Math.max(0, Math.min(rect.right, bounds.right) - Math.max(rect.left, bounds.left));
                return { video: card.querySelector('[data-reel-video]'), overlap: rect.width ? intersection / rect.width : 0, shown: Boolean(rect.width && rect.height) };
            }).filter(item => item.shown && item.overlap > .02 && !failed.has(item.video.dataset.src));
        }).sort((a, b) => b.overlap - a.overlap).slice(0, 6);
        const loaded = new Set(candidates.map(item => item.video));
        allVideos.forEach(video => { if (!loaded.has(video)) unload(video); });
        loaded.forEach(video => {
            if (!video.hasAttribute('src')) { video.muted = video.defaultMuted = true; video.src = video.dataset.src; video.load(); }
            if (video.paused) video.play().catch(() => {});
        });
        section.dataset.reelLoaded = String(loaded.size);
        section.dataset.reelPlaying = String(rows.some(playing));
        lastMedia = performance.now();
    }

    function tick(time) {
        frame = 0;
        if (disposed) return;
        const delta = lastTime ? Math.min(.05, Math.max(0, (time - lastTime) / 1000)) : 0;
        lastTime = time;
        rows.forEach(row => {
            if (!moving(row)) return;
            row.position += row.direction * speed * delta;
            paintPosition(row);
        });
        if (time - lastMedia > 120) syncMedia();
        if (rows.some(moving)) frame = requestAnimationFrame(tick);
        else lastTime = 0;
    }

    function sync() {
        if (disposed) return;
        cancelAnimationFrame(frame);
        frame = lastTime = 0;
        rows.forEach(row => { row.element.dataset.reelMoving = String(moving(row)); });
        section.dataset.reelMoving = String(rows.some(moving));
        syncMedia();
        if (rows.some(moving)) frame = requestAnimationFrame(tick);
    }

    function stopFraction(row) {
        row.position = row.element.scrollLeft;
        row.expected = null;
        row.element.style.setProperty('--reel-shift', '0px');
    }

    function settle(row) {
        row.resumeAt = performance.now() + 800;
        clearTimeout(row.settleTimer);
        row.settleTimer = setTimeout(sync, 830);
    }

    rows.forEach(row => {
        const element = row.element;
        element.addEventListener('scroll', () => {
            if (disposed) return;
            if (row.expected === null || Math.abs(element.scrollLeft - row.expected) > 1) {
                row.position = element.scrollLeft;
                row.expected = null;
                element.style.setProperty('--reel-shift', '0px');
                settle(row);
                if (row.loop && (row.position < row.span * .3 || row.position > row.span * 2.5)) paintPosition(row);
            }
            if (!frame) syncMedia();
        }, { ...options, passive: true });
        element.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') { row.hovering = true; sync(); } }, options);
        element.addEventListener('pointerleave', event => { if (event.pointerType === 'mouse') { row.hovering = false; sync(); } }, options);
        element.addEventListener('focusin', event => {
            row.focused = true;
            stopFraction(row);
            const card = event.target.closest('[data-reel-panel]');
            if (card) {
                const bounds = element.getBoundingClientRect();
                const rect = card.getBoundingClientRect();
                if (rect.left < bounds.left || rect.right > bounds.right) element.scrollLeft += rect.left - bounds.left;
                row.position = element.scrollLeft;
            }
            sync();
        }, options);
        element.addEventListener('focusout', event => { if (!element.contains(event.relatedTarget)) { row.focused = false; row.position = element.scrollLeft; sync(); } }, options);
        element.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            stopFraction(row);
            const width = row.originals[0].getBoundingClientRect().width + (parseFloat(getComputedStyle(element).columnGap) || 0);
            const base = row.loop ? row.span : 0;
            const target = event.key === 'Home' ? base : event.key === 'End' ? base + Math.max(0, row.span - element.clientWidth) : element.scrollLeft + (event.key === 'ArrowRight' ? width : -width);
            element.scrollTo({ left: target, behavior: reduced.matches ? 'instant' : 'smooth' });
        }, options);
        element.addEventListener('pointerdown', event => {
            stopFraction(row);
            if (event.pointerType === 'touch') row.touching = true;
            else if (event.button === 0) row.dragging = { x: event.clientX, scroll: element.scrollLeft, pointerId: event.pointerId, moved: false };
            sync();
        }, { ...options, passive: true });
        element.addEventListener('pointermove', event => {
            if (!row.dragging || event.pointerType !== 'mouse') return;
            const distance = event.clientX - row.dragging.x;
            if (Math.abs(distance) > 6 && !row.dragging.moved) { row.dragging.moved = true; element.setPointerCapture(event.pointerId); element.classList.add('is-dragging'); }
            if (row.dragging.moved) { event.preventDefault(); element.scrollLeft = row.dragging.scroll - distance; row.suppressUntil = performance.now() + 400; }
        }, options);
        element.addEventListener('click', event => { if (performance.now() < row.suppressUntil) { event.preventDefault(); event.stopPropagation(); row.suppressUntil = 0; } }, { ...options, capture: true });
        element.addEventListener('dragstart', event => event.preventDefault(), options);
    });
    const release = () => {
        rows.forEach(row => {
            const interacted = row.touching || row.dragging;
            row.touching = false;
            if (row.dragging?.moved && row.element.hasPointerCapture(row.dragging.pointerId)) row.element.releasePointerCapture(row.dragging.pointerId);
            row.dragging = null;
            row.element.classList.remove('is-dragging');
            row.position = row.element.scrollLeft;
            if (interacted) settle(row);
        });
        sync();
    };
    window.addEventListener('pointerup', release, { ...options, passive: true });
    window.addEventListener('pointercancel', release, { ...options, passive: true });
    window.addEventListener('blur', release, options);
    allVideos.forEach(video => {
        video.addEventListener('loadeddata', () => { if (!disposed && video.hasAttribute('src')) video.classList.add('is-ready'); }, options);
        video.addEventListener('error', () => { failed.add(video.dataset.src); unload(video); }, options);
    });
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => { const row = rows.find(item => item.element === entry.target); if (row) row.visible = entry.isIntersecting; });
        sync();
    }, { threshold: .08 });
    rows.forEach(row => observer.observe(row.element));
    const resize = new ResizeObserver(() => { configureRows(); sync(); });
    rows.forEach(row => resize.observe(row.element));
    const preferences = () => { configureRows(); sync(); };
    reduced.addEventListener('change', preferences, options);
    connection?.addEventListener?.('change', preferences, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); sync(); }, options);
    document.addEventListener('livewire:navigating', cleanup, options);
    window.addEventListener('pagehide', event => { suspended = true; sync(); if (!event.persisted) cleanup(); }, options);
    window.addEventListener('pageshow', () => { suspended = false; sync(); }, options);
    configureRows();
    section.dataset.reelReady = 'true';
    sync();

    function cleanup() {
        if (disposed) return;
        disposed = true;
        events.abort();
        observer.disconnect();
        resize.disconnect();
        cancelAnimationFrame(frame);
        allVideos.forEach(unload);
        rows.forEach(row => {
            clearTimeout(row.settleTimer);
            if (row.dragging?.moved && row.element.hasPointerCapture(row.dragging.pointerId)) row.element.releasePointerCapture(row.dragging.pointerId);
            row.element.classList.remove('is-dragging');
            row.element.style.removeProperty('--reel-shift');
            delete row.element.dataset.reelMoving;
        });
        delete section.dataset.reelReady;
        delete section.dataset.reelPlaying;
        delete section.dataset.reelMoving;
        delete section.dataset.reelLoaded;
    }
    return cleanup;
}
