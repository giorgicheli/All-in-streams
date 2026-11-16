export function initializeGeographyCarousels(root = document) {
    const dispose = [...root.querySelectorAll('[data-geo-carousel]')].map(initializeCarousel);
    return () => dispose.forEach(cleanup => cleanup());
}

function initializeCarousel(element) {
    const viewport = element.querySelector('[data-geo-viewport]');
    const track = element.querySelector('.geo-track');
    const original = track?.querySelector('.geo-items');
    if (!viewport || !original) return () => {};

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
    let touching = false;
    let hovered = false;
    let inView = false;
    let disposed = false;
    let drag = null;
    let dragFrame = 0;
    let inertiaFrame = 0;
    let writtenPosition = null;

    function loopPosition(value) {
        return cycle ? ((value % cycle) + cycle) % cycle + cycle : Math.max(0, value);
    }

    function writePosition(value) {
        viewport.scrollLeft = value;
        writtenPosition = viewport.scrollLeft;
    }

    function stopInertia() {
        cancelAnimationFrame(inertiaFrame);
        inertiaFrame = 0;
    }

    function stop() {
        cancelAnimationFrame(frame);
        clearTimeout(wake);
        frame = wake = previousTime = 0;
    }

    function canMove() {
        return !disposed && !reduced.matches && !document.hidden && inView && cycle > 0
            && !interacting && !touching && !inertiaFrame && !hovered && !viewport.matches(':focus-visible');
    }

    function start() {
        if (frame || !canMove()) return;
        clearTimeout(wake);
        const remaining = resumeAfter - performance.now();
        if (remaining > 0) {
            wake = setTimeout(start, remaining + 20);
            return;
        }
        position = loopPosition(viewport.scrollLeft);
        writePosition(position);
        previousTime = 0;
        frame = requestAnimationFrame(tick);
    }

    function tick(time) {
        frame = 0;
        if (!canMove()) { previousTime = 0; return; }
        if (previousTime) position += Math.min(time - previousTime, 50) * 0.024;
        if (position >= cycle * 2) position -= cycle;
        writePosition(position);
        previousTime = time;
        frame = requestAnimationFrame(tick);
    }

    function pauseForInteraction() {
        stop();
        stopInertia();
        resumeAfter = performance.now() + 2400;
    }

    function coast(velocity) {
        let lastTime = performance.now();
        position = viewport.scrollLeft;
        function advance(time) {
            inertiaFrame = 0;
            if (disposed || document.hidden || !inView || interacting || reduced.matches) return;
            const elapsed = Math.min(time - lastTime, 50);
            velocity *= Math.exp(-elapsed / 220);
            position = loopPosition(position + velocity * elapsed);
            writePosition(position);
            lastTime = time;
            if (Math.abs(velocity) < 0.02) {
                resumeAfter = performance.now() + 2400;
                start();
            } else inertiaFrame = requestAnimationFrame(advance);
        }
        inertiaFrame = requestAnimationFrame(advance);
    }

    function applyDrag() {
        dragFrame = 0;
        if (drag?.active) writePosition(loopPosition(drag.target));
    }

    function finishPointer(withInertia = false) {
        const current = drag;
        cancelAnimationFrame(dragFrame);
        dragFrame = 0;
        if (current?.active) applyDrag();
        drag = null;
        viewport.classList.remove('is-dragging');
        if (current && viewport.hasPointerCapture(current.id)) viewport.releasePointerCapture(current.id);
        interacting = false;
        pauseForInteraction();
        const velocity = current && performance.now() - current.lastTime < 100 ? current.velocity : 0;
        if (withInertia && current?.active && !reduced.matches && Math.abs(velocity) > 0.04) coast(velocity);
        else start();
    }

    function pointerDown(event) {
        if (!event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)) return;
        if (viewport.scrollWidth <= viewport.clientWidth + 1) return;
        pauseForInteraction();
        interacting = true;
        // Recenter identical copies before a new gesture, leaving a full cycle
        // available in either direction without changing the visible countries.
        writePosition(loopPosition(viewport.scrollLeft));
        if (event.pointerType !== 'mouse') return;
        event.preventDefault();
        drag = { id: event.pointerId, originX: event.clientX, lastX: event.clientX,
            lastTime: performance.now(), target: viewport.scrollLeft, velocity: 0, active: false };
        viewport.setPointerCapture(event.pointerId);
    }

    function pointerMove(event) {
        if (!drag || event.pointerId !== drag.id) return;
        if (!drag.active && Math.abs(event.clientX - drag.originX) < 3) return;
        const time = performance.now();
        const elapsed = Math.max(1, time - drag.lastTime);
        const distance = drag.lastX - event.clientX;
        drag.target += distance;
        drag.velocity = Math.max(-2.4, Math.min(2.4, drag.velocity * 0.25 + distance / elapsed * 0.75));
        drag.lastX = event.clientX;
        drag.lastTime = time;
        drag.active = true;
        viewport.classList.add('is-dragging');
        if (!dragFrame) dragFrame = requestAnimationFrame(applyDrag);
    }

    function configure() {
        const progress = cycle ? ((viewport.scrollLeft % cycle) + cycle) % cycle / cycle : 0;
        if (interacting) finishPointer();
        stop();
        stopInertia();
        clones.forEach(clone => clone.remove());
        clones.length = 0;
        cycle = 0;
        writePosition(0);
        if (original.children.length > 1 && original.scrollWidth > viewport.clientWidth) {
            for (let index = 0; index < 2; index++) {
                const clone = original.cloneNode(true);
                clone.setAttribute('aria-hidden', 'true');
                clone.setAttribute('inert', '');
                clone.dataset.geoClone = '';
                track.appendChild(clone);
                clones.push(clone);
            }
            cycle = original.getBoundingClientRect().width;
            writePosition(position = cycle * (1 + progress));
        }
        viewport.classList.toggle('is-scrollable', viewport.scrollWidth > viewport.clientWidth + 1);
        start();
    }

    const options = { signal: events.signal, passive: true };
    // Browsers cancel pointer events when native panning takes over, even while
    // the finger is still down. Keep autoplay paused until the actual lift.
    viewport.addEventListener('touchstart', () => { touching = true; pauseForInteraction(); }, options);
    const touchEnded = event => {
        if (!touching) return;
        touching = event.touches.length > 0;
        if (!touching && !drag) finishPointer();
    };
    window.addEventListener('touchend', touchEnded, options);
    window.addEventListener('touchcancel', touchEnded, options);
    viewport.addEventListener('pointerdown', pointerDown, { signal: events.signal });
    viewport.addEventListener('pointermove', pointerMove, options);
    viewport.addEventListener('pointerup', () => { if (interacting) finishPointer(true); }, options);
    viewport.addEventListener('pointercancel', () => { if (interacting) finishPointer(); }, options);
    viewport.addEventListener('lostpointercapture', () => { if (drag) finishPointer(); }, options);
    viewport.addEventListener('dragstart', event => event.preventDefault(), { signal: events.signal });
    viewport.addEventListener('pointerenter', event => {
        if (event.pointerType !== 'mouse') return;
        hovered = true;
        stop();
    }, options);
    viewport.addEventListener('pointerleave', event => {
        if (event.pointerType === 'mouse') hovered = false;
        if (!interacting) start();
    }, options);
    // Touch stays native, including momentum and vertical page scrolling.
    // Extend the autoplay pause until native wheel/touch scrolling settles.
    viewport.addEventListener('scroll', () => {
        if (writtenPosition !== null && Math.abs(viewport.scrollLeft - writtenPosition) < 1) {
            writtenPosition = null;
            return;
        }
        if (frame || inertiaFrame || drag) return;
        pauseForInteraction();
        start();
    }, options);
    viewport.addEventListener('wheel', () => { pauseForInteraction(); start(); }, options);
    viewport.addEventListener('keydown', pauseForInteraction, { signal: events.signal });
    viewport.addEventListener('focusin', stop, { signal: events.signal });
    viewport.addEventListener('focusout', () => queueMicrotask(start), { signal: events.signal });
    window.addEventListener('pointerup', () => { if (interacting) finishPointer(true); }, options);
    window.addEventListener('blur', () => { touching = false; finishPointer(); }, { signal: events.signal });
    document.addEventListener('visibilitychange', () => { if (document.hidden) { touching = false; if (interacting) finishPointer(); } stop(); stopInertia(); start(); }, { signal: events.signal });
    reduced.addEventListener('change', () => { stop(); stopInertia(); start(); }, { signal: events.signal });

    const observer = new IntersectionObserver(entries => {
        inView = entries[0].isIntersecting;
        if (inView) start(); else stop();
    });
    observer.observe(element);
    const sizeObserver = new ResizeObserver(configure);
    sizeObserver.observe(viewport);
    sizeObserver.observe(original);
    configure();

    return () => {
        disposed = true;
        touching = false;
        if (interacting) finishPointer();
        stop();
        stopInertia();
        events.abort();
        observer.disconnect();
        sizeObserver.disconnect();
        clones.forEach(clone => clone.remove());
        viewport.classList.remove('is-scrollable', 'is-dragging');
        viewport.scrollLeft = 0;
    };
}
