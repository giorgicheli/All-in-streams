const instances = new WeakMap();
const protectedContent = 'h1,h2,h3,h4,h5,h6,p,strong,small,a,button,label,li,dt,dd,summary,figcaption,input,textarea,select,img,video,[role="button"],[contenteditable]:not([contenteditable="false"]),[data-rope-node]';
const quietControls = 'input,textarea,select,[data-rope-node],[contenteditable]:not([contenteditable="false"])';
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

function mountPointerEffect(root) {
    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-effect';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.dataset.pointerEffect = 'ink';
    canvas.dataset.pointerShape = 'droplet';
    canvas.dataset.pointerState = 'sleeping';
    const context = canvas.getContext('2d', { alpha: true, desynchronized: true });
    if (!context) return () => {};

    document.body.append(canvas);
    const events = new AbortController();
    const options = { signal: events.signal, passive: true };
    // These points only carry the motion. One closed silhouette is painted,
    // rather than stamping a chain of circles along the mouse's path.
    const trail = Array.from({ length: 8 }, () => ({ x: 0, y: 0 }));
    let heading = -.5;
    let frame = 0;
    let resizeFrame = 0;
    let previousTime = 0;
    let lastMotion = 0;
    let lastMaskRefresh = 0;
    let masks = [];
    let pointer = null;
    let width = innerWidth;
    let height = innerHeight;
    let menuOpen = false;
    let pageSuspended = false;
    let disposed = false;

    const isQuiet = element => element instanceof Element && Boolean(element.closest(quietControls));
    const permitted = () => !disposed && !pageSuspended && !menuOpen && !document.hidden && !isQuiet(document.activeElement);

    function clear() {
        context.clearRect(0, 0, width, height);
    }

    function stop() {
        cancelAnimationFrame(frame);
        frame = previousTime = 0;
        pointer = null;
        canvas.classList.remove('is-active');
        canvas.dataset.pointerState = 'sleeping';
        clear();
    }

    // Geometry only: carving readable copy and controls out keeps the ink
    // behind the content without reading field values, text or account data.
    function refreshMasks() {
        if (disposed) return;
        masks = [...root.querySelectorAll(protectedContent)].map(element => element.getBoundingClientRect())
            .filter(rect => rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.top < height && rect.right > 0 && rect.left < width)
            .map(rect => ({ left: rect.left - 3, top: rect.top - 3, right: rect.right + 3, bottom: rect.bottom + 3 }));
        lastMaskRefresh = performance.now();
    }

    function resize() {
        resizeFrame = 0;
        if (disposed) return;
        width = innerWidth;
        height = innerHeight;
        const ratio = Math.min(devicePixelRatio || 1, 1.5, Math.sqrt(4_000_000 / Math.max(1, width * height)));
        canvas.width = Math.max(1, Math.round(width * ratio));
        canvas.height = Math.max(1, Math.round(height * ratio));
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        refreshMasks();
    }

    function paintDrop(scale, dt) {
        const head = trail[0];
        const last = trail[trail.length - 1];
        const dx = head.x - last.x;
        const dy = head.y - last.y;
        const distance = Math.hypot(dx, dy);
        if (distance > 3) {
            const target = Math.atan2(dy, dx);
            const turn = Math.atan2(Math.sin(target - heading), Math.cos(target - heading));
            heading += turn * (1 - Math.exp(-dt * 14));
        }
        const ux = Math.cos(heading);
        const uy = Math.sin(heading);
        const nx = -uy;
        const ny = ux;
        const radius = 12.5 * scale;
        const length = clamp(distance, 19, 96) * scale;
        // A slight bend follows the middle of the trail while the two sides
        // share a single pointed tail. Even quick turns cannot shed droplets.
        const middle = trail[3];
        const bend = clamp((middle.x - head.x) * nx + (middle.y - head.y) * ny, -length * .24, length * .24);
        const point = (along, across) => ({ x: head.x + ux * along + nx * across, y: head.y + uy * along + ny * across });
        const front = point(radius * .9, 0);
        const left = point(0, radius);
        const right = point(0, -radius);
        const tail = point(-length, bend * .45);
        const capLeft = point(radius * .9, radius * .62);
        const shoulderLeft = point(radius * .52, radius);
        const bodyLeft = point(-length * .38, radius * .92 + bend);
        const tipLeft = point(-length * .76, radius * .13 + bend * .7);
        const tipRight = point(-length * .76, -radius * .13 + bend * .7);
        const bodyRight = point(-length * .38, -radius * .92 + bend);
        const shoulderRight = point(radius * .52, -radius);
        const capRight = point(radius * .9, -radius * .62);
        context.beginPath();
        context.moveTo(front.x, front.y);
        context.bezierCurveTo(capLeft.x, capLeft.y, shoulderLeft.x, shoulderLeft.y, left.x, left.y);
        context.bezierCurveTo(bodyLeft.x, bodyLeft.y, tipLeft.x, tipLeft.y, tail.x, tail.y);
        context.bezierCurveTo(tipRight.x, tipRight.y, bodyRight.x, bodyRight.y, right.x, right.y);
        context.bezierCurveTo(shoulderRight.x, shoulderRight.y, capRight.x, capRight.y, front.x, front.y);
        context.closePath();
        context.fill();
        const outline = [front, left, right, tail, capLeft, shoulderLeft, bodyLeft, tipLeft, tipRight, bodyRight, shoulderRight, capRight];
        return {
            left: Math.min(...outline.map(point => point.x)) - 1,
            top: Math.min(...outline.map(point => point.y)) - 1,
            right: Math.max(...outline.map(point => point.x)) + 1,
            bottom: Math.max(...outline.map(point => point.y)) + 1,
        };
    }

    function draw(now) {
        frame = 0;
        if (!permitted() || !pointer || now - lastMotion > 1180) { stop(); return; }
        const dt = previousTime ? Math.min((now - previousTime) / 1000, 1 / 30) : 1 / 60;
        previousTime = now;
        const drying = clamp((now - lastMotion - 150) / 930, 0, 1);
        const scale = Math.pow(1 - drying, 1.5);
        clear();
        context.fillStyle = '#17191f';
        trail.forEach((sample, index) => {
            const leader = index === 0 ? pointer : trail[index - 1];
            const follow = 1 - Math.exp(-dt * (index === 0 ? 19 : 17));
            sample.x += (leader.x - sample.x) * follow;
            sample.y += (leader.y - sample.y) * follow;
            const distance = Math.hypot(sample.x - pointer.x, sample.y - pointer.y);
            const limit = index === 0 ? 36 : 112;
            if (distance > limit) {
                sample.x = pointer.x + (sample.x - pointer.x) / distance * limit;
                sample.y = pointer.y + (sample.y - pointer.y) / distance * limit;
            }
        });
        const { left, top, right, bottom } = paintDrop(scale, dt);
        masks.forEach(rect => {
            if (rect.right >= left && rect.left <= right && rect.bottom >= top && rect.top <= bottom) {
                context.clearRect(rect.left, rect.top, rect.right - rect.left, rect.bottom - rect.top);
            }
        });
        if (scale <= 0) { stop(); return; }
        frame = requestAnimationFrame(draw);
    }

    document.addEventListener('pointermove', event => {
        if (event.pointerType !== 'mouse' || event.isPrimary === false) { stop(); return; }
        if (!permitted() || isQuiet(event.target)) { stop(); return; }
        const now = performance.now();
        if (now - lastMaskRefresh > 250) refreshMasks();
        const next = { x: event.clientX - 7, y: event.clientY + 10 };
        if (!pointer || Math.hypot(next.x - pointer.x, next.y - pointer.y) > 220) {
            trail.forEach(sample => { sample.x = next.x; sample.y = next.y; });
        }
        pointer = next;
        lastMotion = now;
        canvas.classList.add('is-active');
        canvas.dataset.pointerState = 'active';
        if (!frame) frame = requestAnimationFrame(draw);
    }, options);
    document.addEventListener('pointerdown', event => {
        if (event.pointerType !== 'mouse' || isQuiet(event.target) || event.target.closest?.('a,button,[role="button"]')) stop();
    }, options);
    document.addEventListener('focusin', event => { if (isQuiet(event.target)) stop(); }, options);
    document.addEventListener('keydown', stop, { signal: events.signal });
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); }, options);
    document.addEventListener('pointerout', event => { if (!event.relatedTarget) stop(); }, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); if (menuOpen) stop(); }, { signal: events.signal });
    document.addEventListener('livewire:navigating', cleanup, { signal: events.signal });
    window.addEventListener('blur', stop, options);
    window.addEventListener('scroll', () => { stop(); masks = []; lastMaskRefresh = 0; }, options);
    window.addEventListener('resize', () => {
        stop();
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(resize);
    }, options);
    window.addEventListener('pagehide', event => {
        pageSuspended = true;
        stop();
        if (!event.persisted) cleanup();
    }, options);
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        pageSuspended = false;
        resize();
    }, options);

    function cleanup() {
        if (disposed) return;
        disposed = true;
        stop();
        events.abort();
        cancelAnimationFrame(resizeFrame);
        masks = [];
        canvas.remove();
    }

    resize();
    document.fonts?.ready.then(() => { if (!disposed) refreshMasks(); });
    return cleanup;
}

export function initializePointerEffect(root = document) {
    instances.get(root)?.();
    if (!document.documentElement.classList.contains('site-redesign')) return () => {};
    const events = new AbortController();
    const finePointer = matchMedia('(hover: hover) and (pointer: fine)');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    let disposeEffect = () => {};

    function configure() {
        disposeEffect();
        disposeEffect = () => {};
        if (finePointer.matches && !reducedMotion.matches && !connection?.saveData) disposeEffect = mountPointerEffect(root);
    }

    finePointer.addEventListener('change', configure, { signal: events.signal });
    reducedMotion.addEventListener('change', configure, { signal: events.signal });
    connection?.addEventListener?.('change', configure, { signal: events.signal });
    configure();
    const cleanup = () => { events.abort(); disposeEffect(); };
    instances.set(root, cleanup);
    return cleanup;
}
