import { roundedNetworkPanelPath } from './network-panel-clip';

const instances = new WeakMap();
const autoSlideInterval = 11;
const crossfadeDuration = .72;
const manualResumeDelay = 8000;
const nextClipInterval = () => 4 + Math.random() * 2;

export function initializeNetworkShowcase(root = document) {
    instances.get(root)?.();
    const disposers = [...root.querySelectorAll('[data-network-showcase]')].map(mountShowcase);
    const cleanup = () => disposers.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountShowcase(section) {
    const track = section.querySelector('[data-network-track]');
    const slides = [...section.querySelectorAll('[data-network-slide]')];
    const previous = section.querySelector('[data-network-prev]');
    const next = section.querySelector('[data-network-next]');
    const toggle = section.querySelector('[data-network-toggle]');
    const status = section.querySelector('[data-network-status]');
    let clips;
    try {
        clips = JSON.parse(section.querySelector('[data-network-clips]')?.textContent || '[]')
            .filter(clip => clip && typeof clip.src === 'string' && clip.src && typeof clip.poster === 'string');
    } catch { return () => {}; }
    if (!track || !slides.length || !clips.length) return () => {};

    const events = new AbortController();
    const options = { signal: events.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    const failed = new Set();
    const decodedFrames = new Map();
    const states = slides.flatMap((slide, slideIndex) => [...slide.querySelectorAll('[data-network-panel]')].map((element, panelIndex) => ({
        element, slideIndex, videos: [...element.querySelectorAll('[data-network-video]')],
        poster: element.querySelector('[data-network-poster]'), link: element.querySelector('[data-network-link]'),
        clipPath: element.querySelector('[data-network-clip-path]'),
        clipIndex: (Number(element.dataset.networkIndex) || panelIndex) % clips.length,
        activeSlot: 0, incomingSlot: null, pendingIndex: null, blendRemaining: null,
        token: 0, loading: false, rendered: false, elapsed: 0, interval: nextClipInterval(),
    }))).filter(state => state.poster && state.videos.length === 2);
    if (!states.length) return () => {};

    let disposed = false;
    let suspended = false;
    let menuOpen = false;
    let visible = false;
    let paused = false;
    let optedIn = false;
    let hovering = false;
    let focused = section.contains(document.activeElement);
    let touching = false;
    let scrolling = false;
    let activeIndex = 0;
    let requestedIndex = null;
    let frame = 0;
    let scrollFrame = 0;
    let lastTime = 0;
    let autoElapsed = 0;
    let resumeAt = 0;
    let resumeTimer = 0;
    let settleTimer = 0;
    let trackWidth = 0;
    const allVideos = states.flatMap(state => state.videos);
    const limited = () => reduced.matches || Boolean(connection?.saveData);
    const intent = () => !paused && (!limited() || optedIn);
    const available = () => !disposed && !suspended && visible && !menuOpen && !document.hidden;
    const playing = () => available() && intent();
    const clipClockRunning = () => playing() && !limited() && !touching && !scrolling;
    // Inspecting a card or using the controls only holds the outer carousel.
    // Its video previews keep changing on their own shorter clock.
    const carouselClockRunning = () => clipClockRunning() && !hovering && !focused && performance.now() >= resumeAt;
    const activeStates = () => states.filter(state => state.slideIndex === activeIndex && state.rendered);
    const valid = (state, token) => !disposed && token === state.token && state.slideIndex === activeIndex && state.rendered && playing();

    function updatePanelClips() {
        states.forEach(state => {
            if (!state.clipPath) return;
            const bounds = state.element.getBoundingClientRect();
            const style = getComputedStyle(state.element);
            const cut = Number.parseFloat(style.getPropertyValue('--network-cut'));
            const radius = Number.parseFloat(style.getPropertyValue('--network-radius'));
            const path = roundedNetworkPanelPath(bounds.width, bounds.height,
                Number.isFinite(cut) ? cut : 46, Number.isFinite(radius) ? radius : 16, state.element.dataset.networkShape);
            if (path) state.clipPath.setAttribute('d', path);
        });
    }

    function measureOffsets() {
        const first = slides[0].offsetLeft;
        return slides.map(slide => slide.offsetLeft - first);
    }

    function nearestSlide() {
        const offsets = measureOffsets();
        return offsets.reduce((nearest, offset, index) => Math.abs(offset - track.scrollLeft) < Math.abs(offsets[nearest] - track.scrollLeft) ? index : nearest, 0);
    }

    function updateMetadata() {
        if (disposed) return;
        section.dataset.networkActiveSlide = String(activeIndex + 1);
        section.dataset.networkSlideCount = String(slides.length);
        section.dataset.networkLoaded = String(allVideos.filter(video => video.hasAttribute('src')).length);
        section.dataset.networkPlaying = String(playing() && activeStates().some(state => state.videos.some(video => video.hasAttribute('src') && !video.paused)));
        section.dataset.networkPaused = String(!intent());
        section.dataset.networkMoving = String(carouselClockRunning() && slides.length > 1);
        if (previous) previous.disabled = slides.length < 2;
        if (next) next.disabled = slides.length < 2;
        if (toggle) {
            toggle.setAttribute('aria-pressed', String(!intent()));
            toggle.setAttribute('aria-label', intent() ? 'Pause video previews' : 'Play video previews');
        }
    }

    function updatePanel(state) {
        const clip = clips[state.clipIndex];
        state.poster.src = clip.poster;
        state.element.dataset.networkClip = String(state.clipIndex);
        state.element.dataset.networkTitle = clip.title || 'Creator video preview';
        state.element.setAttribute('aria-label', clip.title || 'Creator video preview');
        if (state.link) {
            const hasLink = typeof clip.link === 'string' && Boolean(clip.link);
            state.link.hidden = !hasLink;
            state.link.tabIndex = hasLink && state.slideIndex === activeIndex ? 0 : -1;
            state.link.setAttribute('aria-label', `Open ${clip.title || 'creator video'}`);
            if (hasLink) state.link.href = clip.link;
            else state.link.removeAttribute('href');
            if (hasLink && clip.link.startsWith('https://')) {
                state.link.target = '_blank';
                state.link.rel = 'noopener noreferrer';
            } else {
                state.link.removeAttribute('target');
                state.link.removeAttribute('rel');
            }
        }
    }

    function releaseVideo(video) {
        const waiting = decodedFrames.get(video);
        if (waiting) {
            clearTimeout(waiting.timer);
            if (waiting.id !== null) video.cancelVideoFrameCallback?.(waiting.id);
            decodedFrames.delete(video);
            waiting.resolve(false);
        }
        video.pause();
        video.classList.remove('is-active', 'is-incoming');
        if (video.hasAttribute('src')) {
            video.removeAttribute('src');
            video.load();
        }
        delete video.dataset.networkClip;
    }

    function cancelIncoming(state) {
        state.token++;
        state.loading = false;
        state.pendingIndex = state.incomingSlot = state.blendRemaining = null;
        state.videos.forEach((video, slot) => {
            if (slot !== state.activeSlot || !video.classList.contains('is-active')) releaseVideo(video);
        });
        delete state.element.dataset.networkTransition;
    }

    function unload(state) {
        cancelIncoming(state);
        state.videos.forEach(releaseVideo);
        state.activeSlot = 0;
        state.elapsed = 0;
    }

    function refreshStates() {
        states.forEach(state => {
            const rect = state.element.getClientRects()[0];
            state.rendered = state.slideIndex === activeIndex && Boolean(rect?.width && rect?.height) && getComputedStyle(state.element).visibility === 'visible';
        });
    }

    function choose(state) {
        const occupied = new Set(activeStates().flatMap(other => other === state ? [] : [other.clipIndex, other.pendingIndex]
            .filter(index => index !== null).map(index => clips[index].src)));
        const candidates = clips.map((_, index) => index).filter(index => clips[index].src !== clips[state.clipIndex].src && !failed.has(clips[index].src) && !occupied.has(clips[index].src));
        return candidates.length ? candidates[Math.floor(Math.random() * candidates.length)] : null;
    }

    function decodedFrame(video) {
        if (video.readyState < 2) return Promise.resolve(false);
        if (typeof video.requestVideoFrameCallback !== 'function') return Promise.resolve(true);
        return new Promise(resolve => {
            const waiting = { id: null, timer: 0, resolve };
            const finish = result => {
                if (decodedFrames.get(video) !== waiting) return;
                clearTimeout(waiting.timer);
                if (waiting.id !== null) video.cancelVideoFrameCallback?.(waiting.id);
                decodedFrames.delete(video);
                resolve(result);
            };
            decodedFrames.set(video, waiting);
            waiting.id = video.requestVideoFrameCallback(() => finish(true));
            waiting.timer = setTimeout(() => finish(video.readyState >= 2), 1200);
        });
    }

    async function prepare(state, slot, index, token) {
        if (!valid(state, token)) return false;
        const video = state.videos[slot];
        video.muted = video.defaultMuted = true;
        video.poster = clips[index].poster;
        video.dataset.networkClip = String(index);
        video.src = clips[index].src;
        video.load();
        updateMetadata();
        let timeout;
        let rejectMedia;
        const mediaError = () => rejectMedia?.(new Error('Preview unavailable'));
        try {
            await Promise.race([
                video.play(),
                new Promise((_, reject) => {
                    rejectMedia = reject;
                    video.addEventListener('error', mediaError, { once: true });
                    timeout = setTimeout(() => reject(new Error('Preview unavailable')), 8000);
                }),
            ]);
            return valid(state, token) && await decodedFrame(video) && valid(state, token);
        } catch (error) {
            if (valid(state, token) && error.name !== 'AbortError') {
                if (error.name === 'NotAllowedError') paused = true;
                else failed.add(clips[index].src);
            }
            return false;
        } finally {
            clearTimeout(timeout);
            video.removeEventListener('error', mediaError);
        }
    }

    async function ensureCurrent(state) {
        if (!state.rendered || state.loading || !playing() || failed.has(clips[state.clipIndex].src)) return;
        const video = state.videos[state.activeSlot];
        if (video.hasAttribute('src')) {
            if (video.paused) video.play().then(updateMetadata).catch(error => {
                if (disposed || !state.rendered || !playing()) return;
                if (error.name === 'NotAllowedError') { paused = true; sync(); }
            });
            return;
        }
        state.loading = true;
        const token = ++state.token;
        const okay = await prepare(state, state.activeSlot, state.clipIndex, token);
        if (disposed || token !== state.token) return;
        state.loading = false;
        if (okay) video.classList.add('is-active');
        else {
            releaseVideo(video);
            const index = choose(state);
            if (index !== null) { state.clipIndex = index; updatePanel(state); }
            sync();
        }
        updateMetadata();
    }

    async function beginSwap(state) {
        if (!clipClockRunning() || state.loading || state.pendingIndex !== null) return;
        const index = choose(state);
        state.elapsed = 0;
        state.interval = nextClipInterval();
        if (index === null) return;
        const slot = 1 - state.activeSlot;
        state.pendingIndex = index;
        state.loading = true;
        const token = ++state.token;
        const okay = await prepare(state, slot, index, token);
        if (disposed || token !== state.token) return;
        state.loading = false;
        if (okay && playing()) {
            state.incomingSlot = slot;
            state.blendRemaining = crossfadeDuration;
            state.videos[slot].classList.add('is-incoming');
            state.element.dataset.networkTransition = 'true';
            schedule();
        } else {
            releaseVideo(state.videos[slot]);
            state.pendingIndex = null;
            if (paused) sync();
        }
        updateMetadata();
    }

    function finishSwap(state) {
        releaseVideo(state.videos[state.activeSlot]);
        state.activeSlot = state.incomingSlot;
        state.clipIndex = state.pendingIndex;
        state.pendingIndex = state.incomingSlot = state.blendRemaining = null;
        state.videos[state.activeSlot].classList.remove('is-incoming');
        state.videos[state.activeSlot].classList.add('is-active');
        state.elapsed = 0;
        updatePanel(state);
        delete state.element.dataset.networkTransition;
        updateMetadata();
    }

    function activate(index) {
        if (index === activeIndex) return;
        activeIndex = index;
        slides.forEach((slide, position) => {
            slide.inert = position !== activeIndex;
            slide.setAttribute('aria-hidden', String(position !== activeIndex));
        });
        states.forEach(state => { if (state.slideIndex !== activeIndex) unload(state); updatePanel(state); });
        track.setAttribute('aria-label', `Creator video previews, slide ${activeIndex + 1} of ${slides.length}. Swipe or use the arrow keys to explore.`);
        updateMetadata();
    }

    function delayResume() {
        resumeAt = performance.now() + manualResumeDelay;
        autoElapsed = 0;
        clearTimeout(resumeTimer);
        resumeTimer = setTimeout(sync, manualResumeDelay + 30);
    }

    function settle() {
        clearTimeout(settleTimer);
        settleTimer = setTimeout(() => {
            if (disposed) return;
            scrolling = false;
            requestedIndex = null;
            activate(nearestSlide());
            sync();
        }, 150);
    }

    function goTo(index, manual = false) {
        const target = ((index % slides.length) + slides.length) % slides.length;
        if (manual) {
            delayResume();
            if (status) status.textContent = `Creator previews: slide ${target + 1} of ${slides.length}.`;
        }
        autoElapsed = 0;
        requestedIndex = target;
        scrolling = target !== nearestSlide();
        track.scrollTo({ left: measureOffsets()[target], behavior: reduced.matches ? 'instant' : 'smooth' });
        if (reduced.matches || !scrolling) activate(target);
        settle();
        sync();
    }

    function schedule() {
        if (!frame && playing() && (clipClockRunning() || activeStates().some(state => state.blendRemaining !== null))) frame = requestAnimationFrame(tick);
    }

    function tick(time) {
        frame = 0;
        if (!playing()) return;
        const delta = lastTime ? Math.min(.05, Math.max(0, (time - lastTime) / 1000)) : 0;
        lastTime = time;
        activeStates().forEach(state => {
            if (state.blendRemaining !== null) {
                state.blendRemaining -= delta;
                if (state.blendRemaining <= 0) finishSwap(state);
            } else if (clipClockRunning() && !state.loading && state.pendingIndex === null) {
                state.elapsed += delta;
                if (state.elapsed >= state.interval) beginSwap(state);
            }
        });
        if (carouselClockRunning() && slides.length > 1) {
            autoElapsed += delta;
            if (autoElapsed >= autoSlideInterval) goTo(activeIndex + 1);
        }
        schedule();
    }

    function sync() {
        if (disposed) return;
        cancelAnimationFrame(frame);
        frame = lastTime = 0;
        refreshStates();
        states.forEach(state => {
            if (!state.rendered || !available() || (!intent() && !paused)) unload(state);
            else if (!playing()) {
                cancelIncoming(state);
                state.videos[state.activeSlot].pause();
            } else ensureCurrent(state);
        });
        if (!available()) autoElapsed = 0;
        updateMetadata();
        schedule();
    }

    previous?.addEventListener('click', () => goTo((requestedIndex ?? activeIndex) - 1, true), options);
    next?.addEventListener('click', () => goTo((requestedIndex ?? activeIndex) + 1, true), options);
    toggle?.addEventListener('click', () => {
        if (intent()) paused = true;
        else { paused = false; optedIn = true; failed.clear(); }
        delayResume();
        sync();
    }, options);
    track.addEventListener('keydown', event => {
        if (event.target !== track || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const target = event.key === 'Home' ? 0 : event.key === 'End' ? slides.length - 1 : (requestedIndex ?? activeIndex) + (event.key === 'ArrowRight' ? 1 : -1);
        goTo(target, true);
    }, options);
    track.addEventListener('scroll', () => {
        if (disposed) return;
        scrolling = true;
        if (requestedIndex === null) delayResume();
        settle();
        if (!scrollFrame) scrollFrame = requestAnimationFrame(() => {
            scrollFrame = 0;
            activate(nearestSlide());
            sync();
        });
    }, { ...options, passive: true });
    track.addEventListener('pointerdown', event => {
        if (event.pointerType === 'touch' || event.pointerType === 'pen') { touching = true; requestedIndex = null; delayResume(); sync(); }
    }, { ...options, passive: true });
    const release = () => {
        if (!touching) return;
        touching = false;
        delayResume();
        sync();
    };
    window.addEventListener('pointerup', release, { ...options, passive: true });
    window.addEventListener('pointercancel', release, { ...options, passive: true });
    track.addEventListener('wheel', event => { if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) { requestedIndex = null; delayResume(); sync(); } }, { ...options, passive: true });
    section.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') { hovering = true; sync(); } }, options);
    section.addEventListener('pointerleave', event => { if (event.pointerType === 'mouse') { hovering = false; sync(); } }, options);
    section.addEventListener('focusin', () => { focused = true; sync(); }, options);
    section.addEventListener('focusout', event => { if (!section.contains(event.relatedTarget)) { focused = false; delayResume(); sync(); } }, options);
    states.forEach(state => state.videos.forEach(video => {
        video.addEventListener('error', () => {
            if (!video.classList.contains('is-active') && !video.classList.contains('is-incoming')) return;
            const index = Number(video.dataset.networkClip);
            if (clips[index]) failed.add(clips[index].src);
            if (video.classList.contains('is-incoming')) cancelIncoming(state);
            else {
                unload(state);
                const replacement = choose(state);
                if (replacement !== null) { state.clipIndex = replacement; updatePanel(state); }
            }
            sync();
        }, options);
    }));
    const observer = typeof IntersectionObserver === 'function' ? new IntersectionObserver(entries => {
        visible = entries[0].isIntersecting;
        sync();
    }, { threshold: .08 }) : null;
    observer?.observe(track);
    if (!observer) visible = true;
    const resize = typeof ResizeObserver === 'function' ? new ResizeObserver(() => {
        if (disposed) return;
        updatePanelClips();
        if (track.clientWidth === trackWidth) return;
        trackWidth = track.clientWidth;
        requestedIndex = activeIndex;
        track.scrollTo({ left: measureOffsets()[activeIndex], behavior: 'instant' });
        settle();
        sync();
    }) : null;
    resize?.observe(track);
    states.forEach(state => resize?.observe(state.element));
    if (!resize) window.addEventListener('resize', updatePanelClips, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); if (menuOpen) touching = false; sync(); }, options);
    reduced.addEventListener('change', sync, options);
    connection?.addEventListener?.('change', sync, options);
    document.addEventListener('livewire:navigating', cleanup, options);
    window.addEventListener('pagehide', event => { suspended = true; sync(); if (!event.persisted) cleanup(); }, options);
    window.addEventListener('pageshow', event => { if (event.persisted) { suspended = false; sync(); } }, options);
    window.addEventListener('blur', release, options);
    slides.forEach((slide, index) => {
        slide.inert = index !== activeIndex;
        slide.setAttribute('aria-hidden', String(index !== activeIndex));
    });
    states.forEach(updatePanel);
    updatePanelClips();
    track.setAttribute('aria-label', `Creator video previews, slide 1 of ${slides.length}. Swipe or use the arrow keys to explore.`);
    section.dataset.networkReady = 'true';
    section.dataset.networkClipCount = String(clips.length);
    sync();

    function cleanup() {
        if (disposed) return;
        disposed = true;
        events.abort();
        cancelAnimationFrame(frame);
        cancelAnimationFrame(scrollFrame);
        clearTimeout(resumeTimer);
        clearTimeout(settleTimer);
        observer?.disconnect();
        resize?.disconnect();
        states.forEach(unload);
        slides.forEach(slide => { slide.inert = false; slide.removeAttribute('aria-hidden'); });
        ['networkReady', 'networkClipCount', 'networkActiveSlide', 'networkSlideCount', 'networkLoaded', 'networkPlaying', 'networkPaused', 'networkMoving'].forEach(key => delete section.dataset[key]);
    }
    return cleanup;
}
