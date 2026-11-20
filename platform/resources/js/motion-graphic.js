const instances = new WeakMap();

// The supplied 1600 × 900 artwork is retained; only its empty side margins
// are cropped to a square so it can sit in a normal content column.
export function initializeMotionGraphics(root = document) {
    instances.get(root)?.();
    const disposers = [...root.querySelectorAll('[data-motion-graphic]')].map(mountGraphic);
    const cleanup = () => disposers.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountGraphic(root) {
    const viewport = root.querySelector('[data-motion-graphic-viewport]');
    const stage = root.querySelector('[data-motion-graphic-stage]');
    const scenes = [...root.querySelectorAll('.mg-scene')];
    const progress = root.querySelector('[data-motion-graphic-progress]');
    const control = root.querySelector('[data-motion-graphic-control]');
    if (!viewport || !stage || scenes.length !== 5) return () => {};

    const events = new AbortController();
    const options = { signal: events.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let visible = false;
    let paused = false;
    let menuOpen = false;
    let disposed = false;
    let time = 0;
    let last = 0;
    let frame = 0;
    let settleFrames = 0;
    let current = -1;

    const running = () => !disposed && visible && !paused && !menuOpen && !document.hidden && !reduced.matches;

    function showScene(index) {
        if (index === current) return;
        current = index;
        scenes.forEach((scene, position) => {
            scene.classList.toggle('mg-on', position === index);
            scene.classList.remove('mg-out');
        });
        settleFrames = 3;
        root.dataset.motionGraphicScene = String(index);
    }

    function tick(now) {
        frame = 0;
        if (!running()) return;
        const delta = last ? Math.min((now - last) / 1000, .1) : 0;
        last = now;
        time = (time + delta) % 25;
        showScene(Math.floor(time / 5));
        scenes[current].classList.toggle('mg-out', time % 5 > 4.5);
        // Scope the original slow playback to this artwork. The source file
        // used document.getAnimations(), which would slow the entire website.
        if (settleFrames > 0) {
            stage.getAnimations({ subtree: true }).forEach(animation => { animation.playbackRate = 3 / 5; });
            settleFrames--;
        }
        if (progress) progress.style.width = `${time / 25 * 900}px`;
        frame = requestAnimationFrame(tick);
    }

    function sync() {
        const active = running();
        stage.classList.toggle('mg-paused', !active);
        root.dataset.motionGraphicRunning = String(active);
        if (reduced.matches) showScene(4);
        else if (current === -1 || current === 4 && time === 0) showScene(0);
        if (control) {
            control.hidden = reduced.matches;
            control.setAttribute('aria-pressed', String(paused));
            control.setAttribute('aria-label', paused ? 'Play animation' : 'Pause animation');
            control.querySelector('[data-motion-graphic-control-label]').textContent = paused ? 'Play animation' : 'Pause animation';
            control.querySelector('[data-motion-graphic-play]').toggleAttribute('hidden', !paused);
            control.querySelector('[data-motion-graphic-pause]').toggleAttribute('hidden', paused);
        }
        if (!active) {
            cancelAnimationFrame(frame);
            frame = 0;
            last = 0;
        } else if (!frame) {
            last = 0;
            frame = requestAnimationFrame(tick);
        }
    }

    function resize() {
        root.style.setProperty('--ais-motion-scale', String(viewport.getBoundingClientRect().width / 900));
    }
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(viewport);
    const observer = new IntersectionObserver(entries => {
        visible = entries[0].isIntersecting;
        sync();
    }, { threshold: .05 });
    observer.observe(viewport);
    control?.addEventListener('click', () => { paused = !paused; sync(); }, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); sync(); }, options);
    reduced.addEventListener('change', sync, options);
    root.dataset.motionGraphicReady = 'true';
    resize();
    sync();

    return () => {
        disposed = true;
        cancelAnimationFrame(frame);
        events.abort();
        observer.disconnect();
        resizeObserver.disconnect();
        stage.classList.add('mg-paused');
        root.dataset.motionGraphicRunning = 'false';
        if (control) control.hidden = true;
    };
}
