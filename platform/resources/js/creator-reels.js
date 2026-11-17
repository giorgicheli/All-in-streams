const instances = new WeakMap();
const autoDuration = 15500;

export function initializeCreatorReels(root = document) {
    instances.get(root)?.();
    const cleanups = [...root.querySelectorAll('[data-creator-reels]')].map(mountCreatorReels);
    const cleanup = () => cleanups.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountCreatorReels(section) {
    const playlist = section.querySelector('[data-reel-playlist]');
    const buttons = [...section.querySelectorAll('[data-reel-select]')];
    const layers = [...section.querySelectorAll('[data-reel-layer]')].map(element => ({ element, poster: element.querySelector('[data-reel-poster]'), video: element.querySelector('[data-reel-video]') }));
    let clips;
    try { clips = JSON.parse(section.querySelector('[data-reel-clips]')?.textContent || '[]'); } catch { return () => {}; }
    if (!playlist || layers.length !== 2 || !clips.length || buttons.length !== clips.length) return () => {};
    const controller = new AbortController();
    const options = { signal: controller.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    const title = section.querySelector('[data-reel-title]');
    const format = section.querySelector('[data-reel-format]');
    const link = section.querySelector('[data-reel-link]');
    const counter = section.querySelector('[data-reel-counter]');
    const announcement = section.querySelector('[data-reel-announcement]');
    let selected = Math.min(clips.length - 1, Math.max(0, Number(section.dataset.reelSelected || 1) - 1));
    let activeSlot = Math.max(0, layers.findIndex(layer => layer.element.classList.contains('is-active')));
    let visible = false;
    let menuOpen = false;
    let hovering = false;
    let focused = section.contains(document.activeElement);
    let touching = false;
    let suspended = false;
    let disposed = false;
    let autoTimer = 0;
    let autoStarted = 0;
    let remaining = autoDuration;
    let transitionTimer = 0;
    let transitionFrame = 0;

    const limited = () => reduced.matches || Boolean(connection?.saveData);
    const playing = () => !disposed && visible && !menuOpen && !document.hidden && !suspended && !limited();
    const advancing = () => playing() && !hovering && !focused && !touching;

    function unload(layer) {
        layer.video.pause();
        layer.video.classList.remove('is-ready');
        if (layer.video.hasAttribute('src')) { layer.video.removeAttribute('src'); layer.video.load(); }
        delete layer.video.dataset.reelIndex;
    }

    function loadCurrent() {
        const layer = layers[activeSlot];
        if (!playing()) { layers.forEach(unload); return; }
        if (layer.video.dataset.reelIndex !== String(selected)) {
            unload(layer);
            layer.video.muted = layer.video.defaultMuted = true;
            layer.video.dataset.reelIndex = String(selected);
            layer.video.src = clips[selected].src;
            layer.video.load();
        }
        if (layer.video.paused) layer.video.play().catch(() => {});
    }

    function pauseAdvance() {
        if (autoTimer) {
            clearTimeout(autoTimer);
            autoTimer = 0;
            remaining = Math.max(0, remaining - (performance.now() - autoStarted));
        }
    }

    function syncAdvance() {
        pauseAdvance();
        section.dataset.reelMoving = String(advancing());
        if (!advancing()) return;
        autoStarted = performance.now();
        autoTimer = setTimeout(() => {
            autoTimer = 0;
            remaining = autoDuration;
            if (advancing()) select((selected + 1) % clips.length, false);
        }, Math.max(40, remaining));
    }

    function revealChoice(index) {
        const item = buttons[index].getBoundingClientRect();
        const viewport = playlist.getBoundingClientRect();
        const horizontal = getComputedStyle(playlist).flexDirection === 'row';
        const behavior = reduced.matches ? 'instant' : 'smooth';
        // Scroll only this collection, never the document containing it.
        if (horizontal) playlist.scrollTo({ left: playlist.scrollLeft + item.left - viewport.left - (playlist.clientWidth - item.width) / 2, behavior });
        else playlist.scrollTo({ top: playlist.scrollTop + item.top - viewport.top - (playlist.clientHeight - item.height) / 2, behavior });
    }

    function updateMetadata(manual) {
        const clip = clips[selected];
        title.textContent = clip.title;
        format.textContent = clip.format;
        counter.textContent = `${selected + 1} / ${clips.length}`;
        buttons.forEach((button, index) => {
            button.classList.toggle('is-selected', index === selected);
            button.setAttribute('aria-pressed', String(index === selected));
        });
        if (clip.link) {
            link.href = clip.link;
            link.hidden = false;
            link.setAttribute('aria-label', `Open ${clip.title}`);
            if (clip.link.startsWith('https://')) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
            else { link.removeAttribute('target'); link.removeAttribute('rel'); }
        } else { link.hidden = true; link.removeAttribute('href'); }
        section.dataset.reelSelected = String(selected + 1);
        if (manual && announcement) announcement.textContent = `Preview selected: ${clip.title}`;
    }

    function select(index, manual = true) {
        if (disposed || index < 0 || index >= clips.length) return;
        pauseAdvance();
        remaining = autoDuration;
        if (index === selected) { sync(); return; }
        clearTimeout(transitionTimer);
        cancelAnimationFrame(transitionFrame);
        const previousSlot = activeSlot;
        activeSlot = 1 - activeSlot;
        selected = index;
        const incoming = layers[activeSlot];
        unload(incoming);
        incoming.poster.src = clips[selected].poster;
        updateMetadata(manual);
        loadCurrent();
        transitionFrame = requestAnimationFrame(() => {
            transitionFrame = 0;
            if (disposed) return;
            layers.forEach((layer, slot) => layer.element.classList.toggle('is-active', slot === activeSlot));
        });
        transitionTimer = setTimeout(() => {
            transitionTimer = 0;
            if (!disposed && previousSlot !== activeSlot) unload(layers[previousSlot]);
        }, reduced.matches ? 0 : 900);
        revealChoice(selected);
        syncAdvance();
    }

    function sync() {
        if (disposed) return;
        loadCurrent();
        section.dataset.reelPlaying = String(playing());
        syncAdvance();
    }

    layers.forEach(layer => {
        layer.video.addEventListener('loadeddata', () => {
            if (!disposed && playing() && layer.video.hasAttribute('src')) layer.video.classList.add('is-ready');
        }, options);
        layer.video.addEventListener('error', () => unload(layer), options);
    });
    buttons.forEach((button, index) => button.addEventListener('click', () => select(index), options));
    section.querySelector('[data-reel-previous]')?.addEventListener('click', () => select((selected - 1 + clips.length) % clips.length), options);
    section.querySelector('[data-reel-next]')?.addEventListener('click', () => select((selected + 1) % clips.length), options);
    playlist.addEventListener('keydown', event => {
        const button = event.target.closest('[data-reel-select]');
        if (!button) return;
        const current = Number(button.dataset.reelSelect);
        const direction = ['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 0;
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? clips.length - 1 : direction ? (current + direction + clips.length) % clips.length : null;
        if (next === null) return;
        event.preventDefault();
        buttons[next].focus({ preventScroll: true });
        select(next);
    }, options);
    section.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') { hovering = true; syncAdvance(); } }, options);
    section.addEventListener('pointerleave', event => { if (event.pointerType === 'mouse') { hovering = false; syncAdvance(); } }, options);
    section.addEventListener('focusin', () => { focused = true; syncAdvance(); }, options);
    section.addEventListener('focusout', event => { if (!section.contains(event.relatedTarget)) { focused = false; syncAdvance(); } }, options);
    playlist.addEventListener('pointerdown', event => { if (event.pointerType === 'touch') { touching = true; syncAdvance(); } }, { ...options, passive: true });
    const releaseTouch = () => { if (touching) { touching = false; syncAdvance(); } };
    window.addEventListener('pointerup', releaseTouch, { ...options, passive: true });
    window.addEventListener('pointercancel', releaseTouch, { ...options, passive: true });
    const observer = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; sync(); }, { threshold: .1 });
    observer.observe(section.querySelector('[data-reel-screen]'));
    reduced.addEventListener('change', sync, options);
    connection?.addEventListener?.('change', sync, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); sync(); }, options);
    document.addEventListener('livewire:navigating', cleanup, options);
    window.addEventListener('pagehide', event => { suspended = true; sync(); if (!event.persisted) cleanup(); }, options);
    window.addEventListener('pageshow', () => { suspended = false; sync(); }, options);
    layers[activeSlot].poster.src = clips[selected].poster;
    updateMetadata(false);
    section.dataset.reelReady = 'true';
    sync();

    function cleanup() {
        if (disposed) return;
        disposed = true;
        controller.abort();
        observer.disconnect();
        clearTimeout(autoTimer);
        clearTimeout(transitionTimer);
        cancelAnimationFrame(transitionFrame);
        layers.forEach(unload);
        delete section.dataset.reelReady;
        delete section.dataset.reelMoving;
        delete section.dataset.reelPlaying;
    }
    return cleanup;
}
