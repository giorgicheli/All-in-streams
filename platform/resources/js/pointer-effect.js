const instances = new WeakMap();
const quietControls = 'input,textarea,select,[data-rope-node],[contenteditable]:not([contenteditable="false"])';
const headerLabels = '.desktop-site-navigation a, .desktop-site-navigation summary, .header-actions > a, button, [role="button"]';
const buttonLabels = 'button,.btn,[role="button"],summary,.text-link,.site-footer a:not(.brand)';
const radii = [11.5, 10.4, 8.8, 7.1, 5.5, 4.1, 2.8, 1.8];
const simulationStep = 1 / 120;
const pointerDelay = 85;
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

function mountPointerEffect(root) {
    const canvas = document.createElement('canvas');
    canvas.className = 'pointer-effect';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.dataset.pointerEffect = 'ink';
    canvas.dataset.pointerShape = 'connected-ink';
    canvas.dataset.pointerDelayMs = String(pointerDelay);
    canvas.dataset.pointerState = 'sleeping';
    const context = canvas.getContext('2d', { alpha: true, desynchronized: false });
    if (!context) return () => {};

    const header = root.querySelector('.site-header');
    const footer = root.querySelector('.site-footer');
    const layer = document.createElement('div');
    layer.className = 'pointer-layer';
    layer.dataset.pointerLayer = '';
    layer.setAttribute('aria-hidden', 'true');
    layer.inert = true;
    const headerCopy = document.createElement('div');
    headerCopy.className = 'header-pointer-lens';
    headerCopy.dataset.headerPointerLens = '';
    headerCopy.setAttribute('aria-hidden', 'true');
    headerCopy.inert = true;
    layer.append(canvas, headerCopy);
    document.body.append(layer);
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
    let circleMode = false;
    let hoveredControl = null;
    let activeModal = null;
    const lens = { x: 0, y: 0 };
    const pointerSamples = [];
    let inputPointer = null;
    let pointer = null;
    let width = innerWidth;
    let height = innerHeight;
    let menuOpen = false;
    let pageSuspended = false;
    let disposed = false;

    const isQuiet = element => element instanceof Element && Boolean(element.closest(quietControls));
    const permitted = (overControl = circleMode) => !disposed && !pageSuspended && !menuOpen && !document.hidden && (overControl || !isQuiet(document.activeElement));

    function syncLayer(modal) {
        if (modal === activeModal) return;
        activeModal = modal;
        if (typeof layer.showPopover !== 'function') return;
        if (modal) {
            layer.setAttribute('popover', 'manual');
            try { layer.showPopover(); } catch { layer.removeAttribute('popover'); }
        } else {
            if (layer.matches(':popover-open')) layer.hidePopover();
            layer.removeAttribute('popover');
        }
        layer.dataset.pointerLayerMode = modal ? 'modal' : 'page';
        resize();
    }

    function inkAt(x, y) {
        const contains = rect => rect && x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
        const overHeader = contains(header?.getBoundingClientRect());
        const lightInk = !headerMode && !activeModal && !overHeader && contains(footer?.getBoundingClientRect());
        const ink = lightInk ? '#fff' : '#17191f';
        canvas.dataset.pointerContrast = lightInk ? 'light' : 'dark';
        headerCopy.style.setProperty('--pointer-label-color', lightInk ? '#17191f' : '#fff');
        return ink;
    }

    function clear() {
        context.clearRect(0, 0, width, height);
    }

    function stop() {
        cancelAnimationFrame(frame);
        frame = previousTime = accumulator = 0;
        pointer = null;
        inputPointer = null;
        pointerSamples.length = 0;
        paintedBeads.forEach(bead => { bead.paintRadius = 0; });
        canvas.classList.remove('is-active');
        headerCopy.classList.remove('is-active');
        headerCopy.style.clipPath = 'circle(0px at 0px 0px)';
        headerCopy.dataset.lensRadius = '0';
        canvas.dataset.pointerState = 'sleeping';
        clear();
        syncLayer(null);
    }

    // Copy only visible label glyphs and built-in line icons, never controls,
    // logos or backgrounds.
    // Their shared circular clip changes only the letter parts under the lens;
    // all original controls and accessible names stay in place.
    function refreshHeaderLabels() {
        if (disposed) return;
        const copies = [];
        const icons = [];
        const seen = new Set();
        const typography = ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'fontStretch', 'fontVariant', 'fontFeatureSettings', 'fontVariationSettings', 'fontKerning', 'letterSpacing', 'wordSpacing', 'lineHeight', 'textTransform', 'textRendering'];
        const labels = headerMode ? [...(header?.querySelectorAll(headerLabels) || [])] : circleMode ? [...(activeModal || root).querySelectorAll(buttonLabels)] : [];
        labels.forEach(label => {
            const bounds = label.getBoundingClientRect();
            if (!bounds.width || !bounds.height || bounds.bottom < 0 || bounds.top > height || bounds.right < 0 || bounds.left > width) return;
            label.querySelectorAll('svg.icon').forEach(icon => {
                if (seen.has(icon)) return;
                seen.add(icon);
                const rect = icon.getBoundingClientRect();
                const style = getComputedStyle(icon);
                const matrix = icon.getScreenCTM();
                if (!matrix || !rect.width || !rect.height || style.visibility !== 'visible' || style.display === 'none' || ![matrix.a, matrix.b, matrix.c, matrix.d, matrix.e, matrix.f].every(Number.isFinite)) return;
                const copy = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                copy.classList.add('header-pointer-icon');
                copy.dataset.headerPointerIcon = '';
                copy.setAttribute('viewBox', `0 0 ${rect.width} ${rect.height}`);
                copy.setAttribute('fill', 'none');
                copy.setAttribute('stroke', 'currentColor');
                copy.setAttribute('stroke-width', style.strokeWidth);
                copy.setAttribute('stroke-linecap', style.strokeLinecap);
                copy.setAttribute('stroke-linejoin', style.strokeLinejoin);
                copy.setAttribute('aria-hidden', 'true');
                copy.setAttribute('focusable', 'false');
                copy.style.left = `${rect.left}px`;
                copy.style.top = `${rect.top}px`;
                copy.style.width = `${rect.width}px`;
                copy.style.height = `${rect.height}px`;
                const paths = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                // Apply the measured screen matrix inside the measured bounds,
                // so an original icon rotation/scale is never applied twice.
                paths.setAttribute('transform', `matrix(${matrix.a} ${matrix.b} ${matrix.c} ${matrix.d} ${matrix.e - rect.left} ${matrix.f - rect.top})`);
                icon.querySelectorAll('path').forEach(path => {
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    line.setAttribute('d', path.getAttribute('d') || '');
                    paths.append(line);
                });
                copy.append(paths);
                icons.push(copy);
            });
            const walker = document.createTreeWalker(label, NodeFilter.SHOW_TEXT);
            let node;
            while ((node = walker.nextNode())) {
                if (seen.has(node) || !node.textContent.trim() || node.parentElement.closest('svg,.sr-only,[hidden],[aria-hidden="true"]')) continue;
                seen.add(node);
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
        headerCopy.replaceChildren(...copies.map(item => item.copy), ...icons);
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
        // CSS fixed width excludes classic scrollbars; innerWidth includes
        // them. Match the real drawing surface so the ink and CSS clip align.
        const rect = canvas.getBoundingClientRect();
        width = Math.max(1, rect.width);
        height = Math.max(1, rect.height);
        const ratio = Math.min(devicePixelRatio || 1, 1.5, Math.sqrt(4_000_000 / Math.max(1, width * height)));
        canvas.width = Math.max(1, Math.round(width * ratio));
        canvas.height = Math.max(1, Math.round(height * ratio));
        context.setTransform(canvas.width / width, 0, 0, canvas.height / height, 0, 0);
        refreshHeaderLabels();
    }

    function delayedPointer(now) {
        const time = now - pointerDelay;
        while (pointerSamples.length > 1 && pointerSamples[1].time <= time) pointerSamples.shift();
        const first = pointerSamples[0];
        const next = pointerSamples[1] || first;
        const amount = clamp((time - first.time) / (next.time - first.time || 1), 0, 1);
        return { x: first.x + (next.x - first.x) * amount, y: first.y + (next.y - first.y) * amount };
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
        if (!permitted() || !pointer || (!circleMode && now - lastMotion > 1180)) { stop(); return; }
        if (activeModal && !activeModal.matches(':modal')) { stop(); return; }
        const target = delayedPointer(now);
        if (Math.hypot(target.x - pointer.x, target.y - pointer.y) > 150) {
            beads.forEach(bead => { bead.x = bead.previousX = target.x; bead.y = bead.previousY = target.y; });
            lens.x = target.x;
            lens.y = target.y;
            accumulator = 0;
        }
        pointer = target;
        const dt = previousTime ? Math.min((now - previousTime) / 1000, 1 / 30) : 1 / 60;
        previousTime = now;
        const drying = circleMode ? 0 : clamp((now - lastMotion - 150) / 930, 0, 1);
        const scale = 1 - drying * drying * (3 - 2 * drying);
        if (circleMode) {
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
            context.fillStyle = inkAt(lens.x, lens.y);
            context.beginPath();
            context.arc(lens.x, lens.y, radius, 0, Math.PI * 2);
            context.fill();
            // Keep contrasting glyph antialiasing inside the opaque lens edge.
            headerCopy.style.clipPath = `circle(${Math.max(0, radius - .75)}px at ${lens.x}px ${lens.y}px)`;
            headerCopy.dataset.lensX = lens.x.toFixed(2);
            headerCopy.dataset.lensY = lens.y.toFixed(2);
            headerCopy.dataset.lensRadius = radius.toFixed(2);
            if (now - lastLabelRefresh > 100) refreshHeaderLabels();
            // A stationary hover remains visible without an idle animation loop.
            const awaitingInput = now < pointerSamples[pointerSamples.length - 1].time + pointerDelay;
            if (awaitingInput || Math.hypot(lens.x - pointer.x, lens.y - pointer.y) > .05) frame = requestAnimationFrame(draw);
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
        context.fillStyle = inkAt(paintedBeads[0].x, paintedBeads[0].y);
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
        const now = performance.now();
        const overHeader = Boolean(header && event.target instanceof Node && header.contains(event.target));
        const control = event.target instanceof Element ? event.target.closest(overHeader ? headerLabels : buttonLabels) : null;
        const inHeader = overHeader && Boolean(control);
        const inCircle = Boolean(control);
        if (!permitted(inCircle) || isQuiet(event.target)) { stop(); return; }
        const changedControl = control !== hoveredControl;
        const changedMode = inHeader !== headerMode || inCircle !== circleMode;
        headerMode = inHeader;
        circleMode = inCircle;
        hoveredControl = control;
        syncLayer(event.target instanceof Element ? event.target.closest('dialog:modal') : null);
        const next = { x: event.clientX - (circleMode ? 0 : 7), y: event.clientY + (circleMode ? 0 : 10) };
        if (!inputPointer || changedMode) {
            beads.forEach(bead => { bead.x = bead.previousX = next.x; bead.y = bead.previousY = next.y; });
            lens.x = next.x;
            lens.y = next.y;
            accumulator = 0;
            pointer = next;
            pointerSamples.length = 0;
            pointerSamples.push({ ...next, time: now - pointerDelay });
        } else if (now - pointerSamples[pointerSamples.length - 1].time > 32) {
            // Do not interpolate a new movement back across stationary time.
            pointerSamples.push({ ...inputPointer, time: now - .001 });
        }
        inputPointer = next;
        pointerSamples.push({ ...next, time: now });
        if (pointerSamples.length > 128) pointerSamples.splice(0, pointerSamples.length - 128);
        headerCopy.classList.toggle('is-active', circleMode);
        if (circleMode && (changedMode || changedControl || now - lastLabelRefresh > 100)) refreshHeaderLabels();
        if (!circleMode) headerCopy.style.clipPath = 'circle(0px at 0px 0px)';
        canvas.dataset.pointerMode = headerMode ? 'header' : circleMode ? 'button' : 'body';
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
    document.addEventListener('toggle', refreshHeaderLabels, { signal: events.signal, capture: true });
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
        layer.remove();
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
