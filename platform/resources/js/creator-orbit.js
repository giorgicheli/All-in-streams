const instances = new WeakMap();
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

export function initializeCreatorOrbit(root = document) {
    instances.get(root)?.();
    const cleanups = [...root.querySelectorAll('[data-creator-orbit]')].map(mountOrbit);
    const cleanup = () => cleanups.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountOrbit(root) {
    const stage = root.querySelector('[data-orbit-stage]');
    const svg = root.querySelector('[data-orbit-routes]');
    const button = root.querySelector('[data-orbit-control]');
    let clips;
    try {
        const source = JSON.parse(root.querySelector('[data-orbit-clips]')?.textContent || '[]');
        const seen = new Set();
        clips = source.filter(clip => clip && typeof clip.src === 'string' && typeof clip.poster === 'string' && !seen.has(clip.src) && seen.add(clip.src));
    } catch { return () => {}; }
    if (!stage || !svg || !button || !clips.length) return () => {};

    const events = new AbortController();
    const options = { signal: events.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const fine = matchMedia('(hover: hover) and (pointer: fine)');
    const connection = navigator.connection;
    const anchors = [...root.querySelectorAll('[data-orbit-anchor]')];
    const hub = root.querySelector('[data-orbit-hub]');
    const failed = new Set();
    const states = [...root.querySelectorAll('[data-orbit-reel]')].map((element, index) => ({
        element, videos: [...element.querySelectorAll('[data-orbit-video]')], poster: element.querySelector('[data-orbit-poster]'),
        clipIndex: index % clips.length, activeSlot: 0, pendingIndex: null, incomingSlot: null,
        loading: false, token: 0, elapsed: 0, interval: 8 + index * 1.7, blendEnd: null,
    }));
    const paths = [...root.querySelectorAll('[data-orbit-route]')];
    const particles = [...root.querySelectorAll('[data-orbit-flow]')];
    let visible = false;
    let paused = false;
    let optedIn = false;
    let menuOpen = false;
    let suspended = false;
    let disposed = false;
    let frame = 0;
    let lastTime = 0;
    let progress = 0;
    let width = 1;
    let height = 1;
    const pointer = { x: 0, y: 0, targetX: 0, targetY: 0 };

    function limited() { return reduced.matches || Boolean(connection?.saveData); }
    function intent() { return !paused && (!limited() || optedIn); }
    function running() { return !disposed && visible && !menuOpen && !document.hidden && !suspended && intent(); }
    function moving() { return running() && !reduced.matches; }

    function releaseVideo(video) {
        video.pause();
        video.classList.remove('is-active', 'is-incoming');
        if (video.hasAttribute('src')) {
            video.removeAttribute('src');
            video.load();
        }
        delete video.dataset.clipIndex;
    }

    function unload(state) {
        state.token++;
        state.loading = false;
        state.pendingIndex = state.incomingSlot = state.blendEnd = null;
        state.activeSlot = 0;
        state.videos.forEach(releaseVideo);
        state.poster.src = clips[state.clipIndex].poster;
        delete state.element.dataset.orbitTransition;
    }

    function choose(state) {
        const occupied = new Set(states.flatMap(item => item === state ? [] : [item.clipIndex, item.pendingIndex]));
        const candidates = clips.map((_, index) => index).filter(index => index !== state.clipIndex && !failed.has(index) && !occupied.has(index));
        return candidates.length ? candidates[Math.floor(Math.random() * candidates.length)] : null;
    }

    async function playBuffer(state, slot, index, token) {
        const video = state.videos[slot];
        video.muted = video.defaultMuted = true;
        video.poster = clips[index].poster;
        video.dataset.clipIndex = String(index);
        video.src = clips[index].src;
        video.load();
        let timeout;
        try {
            await Promise.race([video.play(), new Promise((_, reject) => { timeout = setTimeout(() => reject(new Error('Preview unavailable')), 8000); })]);
            return !disposed && token === state.token && running() && video.readyState >= 2;
        } catch (error) {
            if (token === state.token && !disposed && error.name !== 'AbortError') {
                if (error.name === 'NotAllowedError') paused = true;
                else failed.add(index);
            }
            return false;
        } finally { clearTimeout(timeout); }
    }

    async function ensureCurrent(state) {
        if (state.loading || state.videos[state.activeSlot].hasAttribute('src') || failed.has(state.clipIndex)) return;
        state.loading = true;
        const token = ++state.token;
        const okay = await playBuffer(state, state.activeSlot, state.clipIndex, token);
        if (disposed || token !== state.token) return;
        state.loading = false;
        if (okay) {
            state.videos[state.activeSlot].classList.add('is-active');
            state.element.dataset.activeClip = String(state.clipIndex);
            state.element.dataset.activeTitle = clips[state.clipIndex].title;
        } else {
            releaseVideo(state.videos[state.activeSlot]);
            const next = choose(state);
            if (next !== null && !paused) {
                state.clipIndex = next;
                state.poster.src = clips[next].poster;
            }
            sync();
        }
    }

    async function beginSwap(state) {
        const next = choose(state);
        if (next === null) { state.elapsed = 0; return; }
        state.loading = true;
        state.pendingIndex = next;
        const slot = 1 - state.activeSlot;
        const token = ++state.token;
        const okay = await playBuffer(state, slot, next, token);
        if (disposed || token !== state.token) return;
        state.loading = false;
        if (okay && moving()) {
            state.incomingSlot = slot;
            state.blendEnd = progress + .85;
            state.videos[slot].classList.add('is-incoming');
            state.element.dataset.orbitTransition = 'true';
        } else {
            releaseVideo(state.videos[slot]);
            state.pendingIndex = null;
            state.elapsed = 0;
            if (paused) sync();
        }
    }

    function finishSwap(state) {
        releaseVideo(state.videos[state.activeSlot]);
        state.activeSlot = state.incomingSlot;
        state.clipIndex = state.pendingIndex;
        state.pendingIndex = state.incomingSlot = state.blendEnd = null;
        state.elapsed = 0;
        state.interval = 8 + Math.random() * 4;
        state.videos[state.activeSlot].classList.remove('is-incoming');
        state.videos[state.activeSlot].classList.add('is-active');
        state.poster.src = clips[state.clipIndex].poster;
        state.element.dataset.activeClip = String(state.clipIndex);
        state.element.dataset.activeTitle = clips[state.clipIndex].title;
        delete state.element.dataset.orbitTransition;
    }

    function point(element, horizontal, vertical, bounds) {
        const rect = element.getBoundingClientRect();
        return { x: rect.left - bounds.left + rect.width * horizontal, y: rect.top - bounds.top + rect.height * vertical };
    }

    function paintRoutes() {
        const bounds = stage.getBoundingClientRect();
        if (!bounds.width || !bounds.height || !hub) return;
        width = bounds.width;
        height = bounds.height;
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        const center = point(hub, .5, .5, bounds);
        const elements = ['brand', 'top', 'main', 'bottom'].map(name => root.querySelector(`[data-orbit-anchor="${name}"]`));
        const ports = [[.97, .5], [.6, .96], [.03, .5], [.5, .04]];
        elements.forEach((element, index) => {
            if (!element || !paths[index]) return;
            const visual = element.querySelector('.creator-orbit-float-card') || element;
            const target = point(visual, ...ports[index], bounds);
            const middleY = (center.y + target.y) / 2;
            paths[index].setAttribute('d', `M${center.x} ${center.y} C${center.x} ${middleY} ${target.x} ${middleY} ${target.x} ${target.y}`);
            const t = (progress / 4.6 + index * .23) % 1;
            const rest = 1 - t;
            const x = rest ** 3 * center.x + 3 * rest ** 2 * t * center.x + 3 * rest * t ** 2 * target.x + t ** 3 * target.x;
            const y = rest ** 3 * center.y + 3 * rest ** 2 * t * middleY + 3 * rest * t ** 2 * middleY + t ** 3 * target.y;
            particles[index]?.setAttribute('cx', x);
            particles[index]?.setAttribute('cy', y);
        });
    }

    function tick(time) {
        frame = 0;
        if (!moving()) return;
        const delta = lastTime ? Math.min((time - lastTime) / 1000, .05) : 1 / 60;
        lastTime = time;
        progress += delta;
        const follow = 1 - Math.exp(-3 * delta);
        pointer.x += (pointer.targetX - pointer.x) * follow;
        pointer.y += (pointer.targetY - pointer.y) * follow;
        anchors.forEach(anchor => {
            const depth = Number(anchor.dataset.orbitDepth) || 0;
            anchor.style.translate = `${pointer.x * depth}px ${pointer.y * depth}px`;
        });
        states.forEach(state => {
            if (state.blendEnd !== null && progress >= state.blendEnd) finishSwap(state);
            else if (!state.loading && state.pendingIndex === null) {
                state.elapsed += delta;
                if (state.elapsed >= state.interval) beginSwap(state);
            }
        });
        paintRoutes();
        frame = requestAnimationFrame(tick);
    }

    function sync() {
        if (disposed) return;
        cancelAnimationFrame(frame);
        frame = lastTime = 0;
        root.dataset.orbitRunning = String(moving());
        button.setAttribute('aria-pressed', String(intent()));
        const label = intent() ? button.dataset.pauseLabel : button.dataset.playLabel;
        button.setAttribute('aria-label', label);
        button.querySelector('[data-orbit-control-label]').textContent = label;
        button.querySelector('[data-orbit-symbol]').classList.toggle('creator-orbit-play', !intent());
        if (!running()) states.forEach(unload);
        else states.forEach(ensureCurrent);
        if (moving()) frame = requestAnimationFrame(tick);
        else {
            pointer.x = pointer.y = pointer.targetX = pointer.targetY = 0;
            anchors.forEach(anchor => anchor.style.removeProperty('translate'));
            paintRoutes();
        }
    }

    button.addEventListener('click', () => {
        if (intent()) paused = true;
        else { paused = false; optedIn = true; failed.clear(); }
        sync();
    }, options);
    stage.addEventListener('pointermove', event => {
        if (event.pointerType !== 'mouse' || !fine.matches || !moving()) return;
        const rect = stage.getBoundingClientRect();
        pointer.targetX = clamp((event.clientX - rect.left) / rect.width - .5, -.5, .5) * 15;
        pointer.targetY = clamp((event.clientY - rect.top) / rect.height - .5, -.5, .5) * 15;
    }, { ...options, passive: true });
    stage.addEventListener('pointerleave', () => { pointer.targetX = pointer.targetY = 0; }, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); sync(); }, options);
    reduced.addEventListener('change', sync, options);
    connection?.addEventListener('change', sync, options);
    window.addEventListener('pagehide', event => { suspended = true; sync(); if (!event.persisted) cleanup(); }, options);
    window.addEventListener('pageshow', event => { if (event.persisted) { suspended = false; sync(); } }, options);
    document.addEventListener('livewire:navigating', cleanup, options);
    const observer = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; sync(); }, { threshold: .05 });
    observer.observe(root);
    const resize = new ResizeObserver(paintRoutes);
    resize.observe(stage);
    root.dataset.orbitReady = 'true';
    root.dataset.orbitClipCount = String(clips.length);
    button.hidden = false;
    paintRoutes();
    sync();

    function cleanup() {
        if (disposed) return;
        disposed = true;
        cancelAnimationFrame(frame);
        events.abort();
        observer.disconnect();
        resize.disconnect();
        states.forEach(unload);
        anchors.forEach(anchor => anchor.style.removeProperty('translate'));
        button.hidden = true;
        delete root.dataset.orbitReady;
        delete root.dataset.orbitRunning;
        delete root.dataset.orbitClipCount;
    }
    return cleanup;
}
