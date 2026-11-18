const instances = new WeakMap();
const baseDuration = 5200;
const holdDuration = 1000;
const advanceDuration = baseDuration - holdDuration;
const holdAt = advanceDuration * .68;
const completeDuration = 350;
const exitDuration = 1150;
const reducedDuration = 700;
const mediaStartDelay = 900;
const cardRevealDelay = 1100;
const ease = amount => (1 - Math.cos(Math.PI * Math.max(0, Math.min(1, amount)))) / 2;

export function initializeSiteIntro(root = document) {
    if (instances.has(root)) return instances.get(root);
    const html = document.documentElement;
    const dialog = root.querySelector('[data-site-intro]');
    let readyResolve;
    let finishedResolve;
    const readyPromise = new Promise(resolve => { readyResolve = resolve; });
    const finishedPromise = new Promise(resolve => { finishedResolve = resolve; });
    let disposed = false;
    let ready = false;
    let opened = false;
    let started = false;
    let mediaStarted = false;
    let clips = [];
    let frame = 0;
    let lastTime = 0;
    let elapsed = 0;
    let exitElapsed = 0;
    let leaving = false;
    let visible = true;
    let titleIndex = -1;
    let value = 0;
    let observer;
    let rootStyles = null;
    let scrollPosition = 0;
    const outgoing = [];
    const events = new AbortController();
    const options = { signal: events.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    const previousFocus = document.activeElement;
    const videos = dialog ? [...dialog.querySelectorAll('[data-site-intro-video]')] : [];
    const headings = dialog ? [...dialog.querySelectorAll('[data-site-intro-heading]')] : [];
    const fill = dialog?.querySelector('[data-site-intro-fill]');
    const count = dialog?.querySelector('[data-site-intro-count]');
    const bar = dialog?.querySelector('[data-site-intro-progress]');
    const settled = new Set();
    const pausedAnimations = new Set();

    function reveal() {
        html.removeAttribute('data-site-intro-pending');
        clearTimeout(window.__aisIntroFailsafe);
        window.__aisIntroFailsafe = null;
        if (ready) return;
        ready = true;
        readyResolve();
    }

    function unload(video) {
        video.pause();
        if (video.hasAttribute('src')) { video.removeAttribute('src'); video.load(); }
        video.classList.remove('is-ready');
    }

    function cleanup(restoreFocus = true) {
        if (disposed) return;
        disposed = true;
        cancelAnimationFrame(frame);
        events.abort();
        observer?.disconnect();
        pausedAnimations.clear();
        videos.forEach(unload);
        if (dialog?.open) dialog.close();
        dialog?.remove();
        if (rootStyles) {
            rootStyles.forEach(([name, value, priority]) => {
                if (value) html.style.setProperty(name, value, priority);
                else html.style.removeProperty(name);
            });
            rootStyles = null;
            if (Math.abs(window.scrollY - scrollPosition) > 1) window.scrollTo({ top: scrollPosition, behavior: 'instant' });
            window.dispatchEvent(new Event('resize'));
        }
        reveal();
        html.dataset.siteIntroState = 'finished';
        document.dispatchEvent(new CustomEvent('ais:site-intro-state', { detail: { active: false } }));
        if (restoreFocus && opened && previousFocus instanceof HTMLElement && previousFocus.isConnected && previousFocus !== document.body) previousFocus.focus({ preventScroll: true });
        finishedResolve();
    }
    cleanup.readyPromise = readyPromise;
    cleanup.finishedPromise = finishedPromise;
    instances.set(root, cleanup);

    if (!dialog || !html.hasAttribute('data-site-intro-pending')) { cleanup(false); return cleanup; }

    function updateProgress(next) {
        value = Math.max(value, Math.min(100, next));
        const integer = Math.floor(value);
        fill.style.transform = `scaleX(${value / 100})`;
        count.textContent = String(integer);
        bar.setAttribute('aria-valuenow', String(integer));
        dialog.dataset.progress = String(integer);
    }

    function showTitle(index) {
        index = Math.max(0, Math.min(headings.length - 1, index));
        if (index === titleIndex) return;
        if (titleIndex >= 0) {
            const previous = headings[titleIndex];
            previous.classList.remove('is-active');
            previous.classList.add('is-out');
            outgoing.push({ element: previous, until: elapsed + 950 });
        }
        titleIndex = index;
        headings[index].classList.add('is-active');
        dialog.dataset.heading = String(index + 1);
    }

    function running() { return !disposed && !document.hidden && (visible || leaving); }

    function resumeVideos() {
        if (reduced.matches || connection?.saveData || !running()) return;
        videos.forEach(video => {
            if (video.hasAttribute('src')) video.play().catch(() => {});
        });
    }

    function startMedia() {
        if (mediaStarted || disposed || leaving || reduced.matches || !running()) return;
        mediaStarted = true;
        videos.forEach((video, index) => {
            const clip = clips[Number(video.dataset.clipIndex) % clips.length];
            if (!clip || typeof clip.src !== 'string' || typeof clip.poster !== 'string') { settled.add(index); return; }
            const poster = video.parentElement.querySelector('[data-site-intro-poster]');
            if (poster) poster.src = clip.poster;
            if (connection?.saveData) { settled.add(index); return; }
            video.muted = video.defaultMuted = true;
            video.poster = clip.poster;
            video.addEventListener('loadeddata', () => { settled.add(index); video.classList.add('is-ready'); }, { ...options, once: true });
            video.addEventListener('error', () => { settled.add(index); unload(video); }, { ...options, once: true });
            video.src = clip.src;
            video.load();
        });
        resumeVideos();
    }

    function leave() {
        if (disposed || leaving) return;
        updateProgress(100);
        leaving = true;
        exitElapsed = 0;
        dialog.dataset.state = 'leaving';
        html.dataset.siteIntroState = 'leaving';
        reveal();
        if (reduced.matches) { cleanup(); return; }
        dialog.classList.add('is-leaving');
        lastTime = performance.now();
        cancelAnimationFrame(frame);
        if (running()) frame = requestAnimationFrame(tick);
    }

    function tick(now) {
        frame = 0;
        if (!running()) { lastTime = 0; return; }
        try {
            const delta = lastTime ? Math.max(0, now - lastTime) : 0;
            lastTime = now;
            if (leaving) {
                exitElapsed += delta;
                if (exitElapsed >= exitDuration) { cleanup(); return; }
            } else {
                elapsed += delta;
                dialog.dataset.elapsed = String(Math.round(elapsed));
                outgoing.forEach(item => { if (elapsed >= item.until) item.element.classList.remove('is-out'); });
                if (reduced.matches) {
                    showTitle(headings.length - 1);
                    updateProgress(100 * ease(elapsed / reducedDuration));
                    if (elapsed >= reducedDuration) { leave(); return; }
                } else {
                    if (elapsed >= mediaStartDelay) startMedia();
                    if (elapsed >= cardRevealDelay) dialog.classList.add('is-deck-visible');
                    if (elapsed >= 4500) videos.forEach((_, index) => settled.add(index));
                    const inHold = elapsed >= holdAt && elapsed < holdAt + holdDuration;
                    const advance = elapsed <= holdAt ? elapsed : elapsed < holdAt + holdDuration ? holdAt : elapsed - holdDuration;
                    const paced = advance < holdAt ? 70 * ease(advance / holdAt) : 70 + 30 * ease((advance - holdAt) / (advanceDuration - holdAt));
                    const readyLimit = 85 + 15 * settled.size / Math.max(videos.length, 1);
                    updateProgress(Math.min(paced, readyLimit));
                    const phase = Math.min(1, advance / advanceDuration);
                    const heading = headings.length === 3 ? (phase < .30 ? 0 : phase < .62 ? 1 : 2) : Math.min(headings.length - 1, Math.floor(phase * headings.length));
                    showTitle(heading);
                    dialog.dataset.progressState = inHold ? 'holding' : elapsed >= baseDuration ? 'complete' : 'advancing';
                    if (elapsed >= baseDuration) {
                        updateProgress(100);
                        dialog.dataset.state = 'complete';
                        if (elapsed >= baseDuration + completeDuration) { leave(); return; }
                    }
                }
            }
            frame = requestAnimationFrame(tick);
        } catch { cleanup(); }
    }

    function visibility() {
        if (disposed) return;
        cancelAnimationFrame(frame);
        frame = lastTime = 0;
        const paused = !running();
        const hasFinished = animation => {
            const endTime = animation.effect?.getComputedTiming().endTime;
            return animation.playState === 'finished' || (Number.isFinite(endTime) && animation.currentTime !== null && Number(animation.currentTime) >= endTime - .001);
        };
        if (paused) {
            // Capture completion before the CSS pause selector changes a
            // finished, fill-forwards animation's playState to "paused".
            dialog.getAnimations?.({ subtree: true }).forEach(animation => {
                if (hasFinished(animation) || animation.playState === 'idle') return;
                pausedAnimations.add(animation);
                animation.pause();
            });
            dialog.dataset.paused = 'true';
        } else {
            dialog.dataset.paused = 'false';
            pausedAnimations.forEach(animation => {
                if (!hasFinished(animation) && animation.playState !== 'idle') animation.play();
            });
            pausedAnimations.clear();
        }
        if (paused) videos.forEach(video => video.pause());
        else {
            if (!started) {
                started = true;
                dialog.classList.add('is-started');
                showTitle(reduced.matches ? headings.length - 1 : 0);
            }
            resumeVideos();
            lastTime = performance.now();
            frame = requestAnimationFrame(tick);
        }
    }

    try {
        if (!fill || !count || !bar || !headings.length || typeof dialog.showModal !== 'function') throw new Error('Intro unavailable');
        clips = JSON.parse(dialog.querySelector('[data-site-intro-clips]')?.textContent || '[]');
        if (!Array.isArray(clips) || !clips.length) throw new Error('Intro clips unavailable');
        dialog.showModal();
        opened = true;
        clearTimeout(window.__aisIntroFailsafe);
        window.__aisIntroFailsafe = null;
        scrollPosition = window.scrollY;
        rootStyles = ['overflow', 'scrollbar-gutter'].map(name => [name, html.style.getPropertyValue(name), html.style.getPropertyPriority(name)]);
        if (CSS.supports('scrollbar-gutter', 'stable')) html.style.setProperty('scrollbar-gutter', 'stable');
        html.style.setProperty('overflow', 'hidden', 'important');
        dialog.dataset.state = 'playing';
        html.dataset.siteIntroState = 'playing';
        dialog.classList.toggle('is-reduced', reduced.matches);
        document.dispatchEvent(new CustomEvent('ais:site-intro-state', { detail: { active: true } }));
        dialog.querySelector('[data-site-intro-focus]')?.focus({ preventScroll: true });
        dialog.querySelector('[data-site-intro-skip]')?.addEventListener('click', leave, options);
        dialog.addEventListener('cancel', event => { event.preventDefault(); leave(); }, options);
        dialog.addEventListener('close', () => cleanup(), options);
        document.addEventListener('visibilitychange', visibility, options);
        document.addEventListener('livewire:navigating', () => cleanup(false), options);
        document.addEventListener('ais:site-intro-abort', () => cleanup(false), options);
        window.addEventListener('pagehide', () => cleanup(false), options);
        reduced.addEventListener('change', () => { if (reduced.matches) leave(); }, options);
        connection?.addEventListener?.('change', () => {
            if (connection.saveData) videos.forEach(unload);
            else resumeVideos();
        }, options);
        observer = new IntersectionObserver(entries => {
            const nextVisible = entries[0].isIntersecting;
            if (nextVisible !== visible) { visible = nextVisible; visibility(); }
        }, { threshold: 0 });
        observer.observe(dialog);
        // Start the copy and progress before revealing the delayed card deck.
        frame = requestAnimationFrame(() => {
            if (disposed) return;
            frame = requestAnimationFrame(now => {
                if (disposed) return;
                started = true;
                dialog.classList.add('is-started');
                showTitle(reduced.matches ? headings.length - 1 : 0);
                if (running()) { resumeVideos(); lastTime = now; frame = requestAnimationFrame(tick); }
                else visibility();
            });
        });
    } catch { cleanup(false); }
    return cleanup;
}
