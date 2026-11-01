export function initializeGeographyCarousels(root = document) {
    const dispose = [...root.querySelectorAll('[data-geo-carousel]')].map(initializeCarousel);
    return () => dispose.forEach(cleanup => cleanup());
}

function initializeCarousel(element) {
    const viewport = element.querySelector('[data-geo-viewport]');
    const track = element.querySelector('.geo-track');
    const original = track?.querySelector('.geo-items');
    if (!viewport || !original) return () => {};

    const mobile = matchMedia('(max-width: 700px)');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const events = new AbortController();
    const clones = [];
    let frame = 0;
    let wake = 0;
    let previousTime = 0;
    let cycle = 0;
    let position = 0;
    let resumeAfter = 0;
    let interacting = false;
    let hovered = false;
    let inView = false;
    let disposed = false;

    function stop() {
        cancelAnimationFrame(frame);
        clearTimeout(wake);
        frame = wake = previousTime = 0;
    }

    function canMove() {
        return !disposed && mobile.matches && !reduced.matches && !document.hidden && inView && cycle > 0
            && !interacting && !hovered && !viewport.matches(':focus-visible');
    }

    function start() {
        if (frame || !canMove()) return;
        clearTimeout(wake);
        const remaining = resumeAfter - performance.now();
        if (remaining > 0) {
            wake = setTimeout(start, remaining + 20);
            return;
        }
        position = viewport.scrollLeft;
        if (position < cycle || position >= cycle * 2) position = (position % cycle) + cycle;
        viewport.scrollLeft = position;
        previousTime = 0;
        frame = requestAnimationFrame(tick);
    }

    function tick(time) {
        frame = 0;
        if (!canMove()) { previousTime = 0; return; }
        if (previousTime) position += Math.min(time - previousTime, 50) * 0.024;
        if (position >= cycle * 2) position -= cycle;
        viewport.scrollLeft = position;
        previousTime = time;
        frame = requestAnimationFrame(tick);
    }

    function pauseForInteraction() {
        stop();
        resumeAfter = performance.now() + 2400;
    }

    function release() {
        interacting = false;
        pauseForInteraction();
        start();
    }

    function configure() {
        stop();
        clones.forEach(clone => clone.remove());
        clones.length = 0;
        cycle = 0;
        viewport.scrollLeft = 0;
        if (mobile.matches && original.children.length > 1 && original.scrollWidth > viewport.clientWidth) {
            for (let index = 0; index < 2; index++) {
                const clone = original.cloneNode(true);
                clone.setAttribute('aria-hidden', 'true');
                clone.setAttribute('inert', '');
                clone.dataset.geoClone = '';
                track.appendChild(clone);
                clones.push(clone);
            }
            cycle = original.getBoundingClientRect().width;
            viewport.scrollLeft = position = cycle;
        }
        start();
    }

    const options = { signal: events.signal, passive: true };
    viewport.addEventListener('pointerdown', () => { interacting = true; pauseForInteraction(); }, options);
    viewport.addEventListener('pointerup', release, options);
    viewport.addEventListener('pointercancel', release, options);
    viewport.addEventListener('pointerenter', event => {
        if (event.pointerType !== 'mouse') return;
        hovered = true;
        stop();
    }, options);
    viewport.addEventListener('pointerleave', event => {
        if (event.pointerType === 'mouse') hovered = false;
        release();
    }, options);
    viewport.addEventListener('wheel', () => { pauseForInteraction(); start(); }, options);
    viewport.addEventListener('keydown', pauseForInteraction, { signal: events.signal });
    viewport.addEventListener('focusin', stop, { signal: events.signal });
    viewport.addEventListener('focusout', () => queueMicrotask(start), { signal: events.signal });
    window.addEventListener('pointerup', () => { if (interacting) release(); }, options);
    document.addEventListener('visibilitychange', () => { stop(); start(); }, { signal: events.signal });
    mobile.addEventListener('change', configure, { signal: events.signal });
    reduced.addEventListener('change', () => { stop(); start(); }, { signal: events.signal });

    const observer = new IntersectionObserver(entries => {
        inView = entries[0].isIntersecting;
        if (inView) start(); else stop();
    });
    observer.observe(element);
    const sizeObserver = new ResizeObserver(configure);
    sizeObserver.observe(viewport);
    configure();

    return () => {
        disposed = true;
        stop();
        events.abort();
        observer.disconnect();
        sizeObserver.disconnect();
        clones.forEach(clone => clone.remove());
        viewport.scrollLeft = 0;
    };
}
