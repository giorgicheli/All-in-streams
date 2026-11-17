const instances = new WeakMap();
const protectedContent = 'h1,h2,h3,h4,h5,h6,p,a,button,label,li,dt,dd,summary,figcaption,input,textarea,select,img,video,[role="button"],[contenteditable]:not([contenteditable="false"]),[data-rope-node]';
const quietControls = 'input,textarea,select,[data-rope-node],[contenteditable]:not([contenteditable="false"])';
const radii = [11.5, 10.4, 8.8, 7.1, 5.5, 4.1, 2.8, 1.8];
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

function mountPointerEffect(root) {
    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-effect';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.dataset.pointerEffect = 'ink';
    canvas.dataset.pointerState = 'sleeping';
    const context = canvas.getContext('2d', { alpha: true, desynchronized: true });
    if (!context) return () => {};

    document.body.append(canvas);
    const events = new AbortController();
    const options = { signal: events.signal, passive: true };
    const beads = radii.map(radius => ({ x: 0, y: 0, radius, paintRadius: 0 }));
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
        beads.forEach(bead => { bead.paintRadius = 0; });
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

    function bridge(first, second) {
        const dx = second.x - first.x;
        const dy = second.y - first.y;
        const distance = Math.hypot(dx, dy);
        const sum = first.paintRadius + second.paintRadius;
        if (distance < .1 || distance > sum * 2.35) return;
        const nx = -dy / distance;
        const ny = dx / distance;
        const ux = dx / distance;
        const uy = dy / distance;
        const handle = distance * .58;
        const neck = clamp(1 - distance / (sum * 2.65), .23, .86);
        const firstRadius = first.paintRadius * .9;
        const secondRadius = second.paintRadius * .9;
        context.beginPath();
        context.moveTo(first.x + nx * firstRadius, first.y + ny * firstRadius);
        context.bezierCurveTo(
            first.x + ux * handle + nx * firstRadius * neck, first.y + uy * handle + ny * firstRadius * neck,
            second.x - ux * handle + nx * secondRadius * neck, second.y - uy * handle + ny * secondRadius * neck,
            second.x + nx * secondRadius, second.y + ny * secondRadius,
        );
        context.lineTo(second.x - nx * secondRadius, second.y - ny * secondRadius);
        context.bezierCurveTo(
            second.x - ux * handle - nx * secondRadius * neck, second.y - uy * handle - ny * secondRadius * neck,
            first.x + ux * handle - nx * firstRadius * neck, first.y + uy * handle - ny * firstRadius * neck,
            first.x - nx * firstRadius, first.y - ny * firstRadius,
        );
        context.closePath();
        context.fill();
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
        let left = width;
        let top = height;
        let right = 0;
        let bottom = 0;
        beads.forEach((bead, index) => {
            const leader = index === 0 ? pointer : beads[index - 1];
            const follow = 1 - Math.exp(-dt * (index === 0 ? 19 : 17));
            bead.x += (leader.x - bead.x) * follow;
            bead.y += (leader.y - bead.y) * follow;
            // Keep the trail short; quick motion separates it into droplets.
            const distance = Math.hypot(bead.x - pointer.x, bead.y - pointer.y);
            if (distance > 140) {
                bead.x = pointer.x + (bead.x - pointer.x) / distance * 140;
                bead.y = pointer.y + (bead.y - pointer.y) / distance * 140;
            }
            bead.paintRadius = bead.radius * scale;
            left = Math.min(left, bead.x - bead.paintRadius);
            top = Math.min(top, bead.y - bead.paintRadius);
            right = Math.max(right, bead.x + bead.paintRadius);
            bottom = Math.max(bottom, bead.y + bead.paintRadius);
        });
        for (let index = 0; index < beads.length - 1; index++) bridge(beads[index], beads[index + 1]);
        beads.forEach(bead => {
            if (bead.paintRadius < .1) return;
            context.beginPath();
            context.arc(bead.x, bead.y, bead.paintRadius, 0, Math.PI * 2);
            context.fill();
        });
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
            beads.forEach(bead => { bead.x = next.x; bead.y = next.y; });
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
