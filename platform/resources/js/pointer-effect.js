const instances = new WeakMap();
const quietControls = 'input,textarea,select,[data-rope-node],[contenteditable]:not([contenteditable="false"])';
const headerLabels = '.desktop-site-navigation a, .desktop-site-navigation summary, .header-actions > a';
const radii = [11.5, 10.4, 8.8, 7.1, 5.5, 4.1, 2.8, 1.8];
const simulationStep = 1 / 120;
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

function mountPointerEffect(root) {
    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-effect';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.dataset.pointerEffect = 'ink';
    canvas.dataset.pointerShape = 'connected-ink';
    canvas.dataset.pointerState = 'sleeping';
    const context = canvas.getContext('2d', { alpha: true, desynchronized: true });
    if (!context) return () => {};

    const header = root.querySelector('.site-header');
    const headerCopy = document.createElement('div');
    headerCopy.className = 'header-pointer-lens';
    headerCopy.dataset.headerPointerLens = '';
    headerCopy.setAttribute('aria-hidden', 'true');
    headerCopy.inert = true;
    document.body.append(canvas, headerCopy);
    const events = new AbortController();
    const options = { signal: events.signal, passive: true };
    const beads = radii.map(radius => ({ x: 0, y: 0, previousX: 0, previousY: 0, radius }));
    const paintedBeads = radii.map(radius => ({ x: 0, y: 0, radius, paintRadius: 0 }));
    let frame = 0;
    let resizeFrame = 0;
    let previousTime = 0;
    let accumulator = 0;
    let lastMotion = 0;
    let lastLabelRefresh = 0;
    let headerMode = false;
    const lens = { x: 0, y: 0 };
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
        frame = previousTime = accumulator = 0;
        pointer = null;
        paintedBeads.forEach(bead => { bead.paintRadius = 0; });
        canvas.classList.remove('is-active');
        headerCopy.classList.remove('is-active');
        headerCopy.style.clipPath = 'circle(0px at 0px 0px)';
        headerCopy.dataset.lensRadius = '0';
        canvas.dataset.pointerState = 'sleeping';
        clear();
    }

    // Copies contain just public navigation glyphs, without links, icons or
    // backgrounds. A shared circular clip reveals only the white letter parts
    // inside the black lens while all original click targets stay in place.
    function refreshHeaderLabels() {
        if (disposed || !header) return;
        const copies = [];
        const typography = ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'fontStretch', 'fontVariant', 'fontFeatureSettings', 'fontVariationSettings', 'fontKerning', 'letterSpacing', 'wordSpacing', 'lineHeight', 'textTransform', 'textRendering'];
        header.querySelectorAll(headerLabels).forEach(label => {
            const walker = document.createTreeWalker(label, NodeFilter.SHOW_TEXT);
            let node;
            while ((node = walker.nextNode())) {
                if (!node.textContent.trim()) continue;
                const style = getComputedStyle(node.parentElement);
                if (style.visibility !== 'visible' || style.display === 'none') continue;
                const range = document.createRange();
                const lines = [];
                for (const word of node.textContent.matchAll(/\S+/g)) {
                    range.setStart(node, word.index);
                    range.setEnd(node, word.index + word[0].length);
                    const rect = range.getBoundingClientRect();
                    if (!rect.width || !rect.height || rect.bottom < 0 || rect.top > height) continue;
                    const previous = lines[lines.length - 1];
                    if (previous && Math.abs(previous.top - rect.top) < 1) {
                        previous.text += ' ' + word[0];
                    } else lines.push({ text: word[0], left: rect.left, top: rect.top });
                }
                lines.forEach(line => {
                    const copy = document.createElement('span');
                    copy.className = 'header-pointer-label';
                    copy.dataset.headerPointerLabel = '';
                    copy.textContent = line.text;
                    typography.forEach(property => { copy.style[property] = style[property]; });
                    copies.push({ copy, line });
                });
            }
        });
        headerCopy.replaceChildren(...copies.map(item => item.copy));
        // Match the glyph bounds instead of estimating a font baseline. This
        // stays aligned with variable fonts and the site's responsive sizes.
        const positions = copies.map(({ copy, line }) => {
            const range = document.createRange();
            range.selectNodeContents(copy);
            const rect = range.getBoundingClientRect();
            return { copy, left: line.left - rect.left, top: line.top - rect.top };
        });
        positions.forEach(({ copy, left, top }) => {
            copy.style.left = `${left}px`;
            copy.style.top = `${top}px`;
        });
        lastLabelRefresh = performance.now();
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
        refreshHeaderLabels();
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
        if (!permitted() || !pointer || (!headerMode && now - lastMotion > 1180)) { stop(); return; }
        const dt = previousTime ? Math.min((now - previousTime) / 1000, 1 / 30) : 1 / 60;
        previousTime = now;
        const drying = headerMode ? 0 : clamp((now - lastMotion - 150) / 930, 0, 1);
        const scale = 1 - drying * drying * (3 - 2 * drying);
        if (headerMode) {
            const follow = 1 - Math.exp(-dt * 24);
            lens.x += (pointer.x - lens.x) * follow;
            lens.y += (pointer.y - lens.y) * follow;
            const gap = Math.hypot(lens.x - pointer.x, lens.y - pointer.y);
            if (gap > 32) {
                lens.x = pointer.x + (lens.x - pointer.x) / gap * 32;
                lens.y = pointer.y + (lens.y - pointer.y) / gap * 32;
            }
            const radius = 30 * scale;
            clear();
            context.fillStyle = '#17191f';
            context.beginPath();
            context.arc(lens.x, lens.y, radius, 0, Math.PI * 2);
            context.fill();
            headerCopy.style.clipPath = `circle(${radius}px at ${lens.x}px ${lens.y}px)`;
            headerCopy.dataset.lensX = lens.x.toFixed(2);
            headerCopy.dataset.lensY = lens.y.toFixed(2);
            headerCopy.dataset.lensRadius = radius.toFixed(2);
            if (now - lastLabelRefresh > 100) refreshHeaderLabels();
            // A stationary hover remains visible without an idle animation loop.
            if (Math.hypot(lens.x - pointer.x, lens.y - pointer.y) > .05) frame = requestAnimationFrame(draw);
            else previousTime = 0;
            return;
        }
        // The same simulation rate on every display keeps the familiar elastic
        // chain consistent. Interpolation smooths the frames between its steps.
        accumulator = Math.min(accumulator + dt, .05);
        while (accumulator >= simulationStep) {
            beads.forEach((bead, index) => {
                bead.previousX = bead.x;
                bead.previousY = bead.y;
                const leader = index === 0 ? pointer : beads[index - 1];
                const follow = 1 - Math.exp(-simulationStep * (index === 0 ? 20 : 18.5));
                bead.x += (leader.x - bead.x) * follow;
                bead.y += (leader.y - bead.y) * follow;
                const distance = Math.hypot(bead.x - pointer.x, bead.y - pointer.y);
                if (distance > 60) {
                    bead.x = pointer.x + (bead.x - pointer.x) / distance * 60;
                    bead.y = pointer.y + (bead.y - pointer.y) / distance * 60;
                }
                if (index > 0) {
                    const separation = Math.hypot(bead.x - leader.x, bead.y - leader.y);
                    if (separation > 10) {
                        bead.x = leader.x + (bead.x - leader.x) / separation * 10;
                        bead.y = leader.y + (bead.y - leader.y) / separation * 10;
                    }
                }
            });
            accumulator -= simulationStep;
        }
        const interpolation = accumulator / simulationStep;
        clear();
        context.fillStyle = '#17191f';
        paintedBeads.forEach((bead, index) => {
            const sample = beads[index];
            bead.x = sample.previousX + (sample.x - sample.previousX) * interpolation;
            bead.y = sample.previousY + (sample.y - sample.previousY) * interpolation;
            const distance = Math.hypot(bead.x - pointer.x, bead.y - pointer.y);
            if (distance > 60) {
                bead.x = pointer.x + (bead.x - pointer.x) / distance * 60;
                bead.y = pointer.y + (bead.y - pointer.y) / distance * 60;
            }
            if (index > 0) {
                const leader = paintedBeads[index - 1];
                const separation = Math.hypot(bead.x - leader.x, bead.y - leader.y);
                if (separation > 10) {
                    bead.x = leader.x + (bead.x - leader.x) / separation * 10;
                    bead.y = leader.y + (bead.y - leader.y) / separation * 10;
                }
            }
            bead.paintRadius = bead.radius * scale;
        });
        for (let index = 0; index < paintedBeads.length - 1; index++) bridge(paintedBeads[index], paintedBeads[index + 1]);
        paintedBeads.forEach(bead => {
            if (bead.paintRadius < .1) return;
            context.beginPath();
            context.arc(bead.x, bead.y, bead.paintRadius, 0, Math.PI * 2);
            context.fill();
        });
        if (scale <= 0) { stop(); return; }
        frame = requestAnimationFrame(draw);
    }

    document.addEventListener('pointermove', event => {
        if (event.pointerType !== 'mouse' || event.isPrimary === false) { stop(); return; }
        if (!permitted() || isQuiet(event.target)) { stop(); return; }
        const now = performance.now();
        const inHeader = Boolean(header && event.target instanceof Node && header.contains(event.target));
        const changedMode = inHeader !== headerMode;
        headerMode = inHeader;
        const next = { x: event.clientX - (headerMode ? 0 : 7), y: event.clientY + (headerMode ? 0 : 10) };
        if (!pointer || changedMode || Math.hypot(next.x - pointer.x, next.y - pointer.y) > 150) {
            beads.forEach(bead => { bead.x = bead.previousX = next.x; bead.y = bead.previousY = next.y; });
            lens.x = next.x;
            lens.y = next.y;
            accumulator = 0;
        }
        headerCopy.classList.toggle('is-active', headerMode);
        if (headerMode && (changedMode || now - lastLabelRefresh > 100)) refreshHeaderLabels();
        if (!headerMode) headerCopy.style.clipPath = 'circle(0px at 0px 0px)';
        canvas.dataset.pointerMode = headerMode ? 'header' : 'body';
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
    window.addEventListener('scroll', () => { stop(); lastLabelRefresh = 0; }, options);
    header?.addEventListener('toggle', refreshHeaderLabels, { signal: events.signal, capture: true });
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
        canvas.remove();
        headerCopy.remove();
    }

    resize();
    document.fonts?.ready.then(() => { if (!disposed) refreshHeaderLabels(); });
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
