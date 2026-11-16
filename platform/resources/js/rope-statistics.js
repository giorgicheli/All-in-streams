const instances = new WeakMap();
const svgNamespace = 'http://www.w3.org/2000/svg';
const connections = [[0, 1], [0, 2], [1, 3], [2, 3]];
const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

export function initializeRopeStatistics(root = document) {
    instances.get(root)?.();
    const elements = [...root.querySelectorAll('[data-rope-statistics]')];
    if (root instanceof Element && root.matches('[data-rope-statistics]')) elements.unshift(root);
    const cleanups = elements.map(createRopeStatistics);
    const cleanup = () => cleanups.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function createRopeStatistics(element) {
    const cards = [...element.querySelectorAll('[data-rope-node]')];
    const svg = element.querySelector('.rope-statistics-ropes');
    if (cards.length !== 4 || !svg) {
        const help = element.querySelector('#rope-statistics-help');
        const previousHelp = help?.textContent;
        element.dataset.ropeState = 'static';
        element.dataset.ropeCount = String(cards.length);
        if (help) help.textContent = 'Network statistics. Scroll within this group to view additional cards.';
        return () => {
            delete element.dataset.ropeState;
            delete element.dataset.ropeCount;
            if (help) help.textContent = previousHelp;
        };
    }

    const events = new AbortController();
    const listenerOptions = { signal: events.signal };
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const compact = matchMedia('(max-width: 700px)');
    const nodes = cards.map((card, index) => ({ card, index, x: 0, y: 0, previousX: 0, previousY: 0, restX: 0, restY: 0, width: 0, height: 0 }));
    const anchors = [{ fixed: true, x: 0, y: 0 }, { fixed: true, x: 0, y: 0 }];
    const paths = new Map();
    const createdPaths = [];
    const constraints = [];
    let frame = 0;
    let elapsed = 0;
    let previousTime = 0;
    let width = 0;
    let height = 0;
    let inView = true;
    let menuOpen = false;
    let pageSuspended = false;
    let disposed = false;
    let drag = null;

    for (const name of ['anchor-0', 'anchor-1', ...connections.map(pair => pair.join('-'))]) {
        let path = [...svg.querySelectorAll('[data-rope-link]')].find(item => item.dataset.ropeLink === name);
        if (!path) {
            path = document.createElementNS(svgNamespace, 'path');
            path.dataset.ropeLink = name;
            svg.append(path);
            createdPaths.push(path);
        }
        paths.set(name, path);
    }

    function permitted() {
        return !disposed && !pageSuspended && inView && !menuOpen && !document.hidden;
    }

    function stop() {
        cancelAnimationFrame(frame);
        frame = previousTime = 0;
    }

    function wake() {
        if (!frame && permitted() && (!reducedMotion.matches || drag)) frame = requestAnimationFrame(animate);
    }

    function bounds(node) {
        const edge = compact.matches ? 8 : 12;
        node.x = clamp(node.x, node.width / 2 + edge, width - node.width / 2 - edge);
        node.y = clamp(node.y, node.height / 2 + edge, height - node.height / 2 - edge);
    }

    function addConstraint(first, second, stiffness) {
        constraints.push({ first, second, length: Math.hypot(second.x - first.x, second.y - first.y), stiffness });
    }

    function resize() {
        if (disposed) return;
        releaseDrag(true);
        const rect = element.getBoundingClientRect();
        width = rect.width;
        height = rect.height;
        if (!width || !height) return;
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        nodes.forEach(node => {
            node.width = node.card.offsetWidth;
            node.height = node.card.offsetHeight;
            const left = node.index % 2 === 0;
            node.restX = width * (left ? (compact.matches ? .25 : .26) : (compact.matches ? .75 : .74));
            node.restY = height * (node.index < 2 ? .29 : .76);
            node.x = node.restX;
            node.y = node.restY;
            bounds(node);
            node.restX = node.previousX = node.x;
            node.restY = node.previousY = node.y;
        });
        anchors.forEach((anchor, index) => {
            anchor.x = nodes[index].restX;
            anchor.y = Math.max(7, height * .035);
        });
        constraints.length = 0;
        addConstraint(anchors[0], nodes[0], .26);
        addConstraint(anchors[1], nodes[1], .26);
        connections.forEach(([first, second]) => addConstraint(nodes[first], nodes[second], first < 2 && second > 1 ? .12 : .085));
        paint();
        wake();
    }

    function solveRopes() {
        for (const constraint of constraints) {
            const { first, second, length, stiffness } = constraint;
            const dx = second.x - first.x;
            const dy = second.y - first.y;
            const distance = Math.max(.001, Math.hypot(dx, dy));
            const firstWeight = first.fixed || drag?.node === first ? 0 : 1;
            const secondWeight = drag?.node === second ? 0 : 1;
            const weight = firstWeight + secondWeight;
            if (!weight) continue;
            const correction = (distance - length) / distance * stiffness;
            first.x += dx * correction * firstWeight / weight;
            first.y += dy * correction * firstWeight / weight;
            second.x -= dx * correction * secondWeight / weight;
            second.y -= dy * correction * secondWeight / weight;
        }
    }

    function separateCards() {
        const gap = compact.matches ? 9 : 13;
        let collided = false;
        for (let firstIndex = 0; firstIndex < nodes.length; firstIndex++) {
            for (let secondIndex = firstIndex + 1; secondIndex < nodes.length; secondIndex++) {
                const first = nodes[firstIndex];
                const second = nodes[secondIndex];
                const dx = second.x - first.x;
                const dy = second.y - first.y;
                const overlapX = (first.width + second.width) / 2 + gap - Math.abs(dx);
                const overlapY = (first.height + second.height) / 2 + gap - Math.abs(dy);
                if (overlapX <= 0 || overlapY <= 0) continue;
                collided = true;
                const firstWeight = drag?.node === first ? 0 : 1;
                const secondWeight = drag?.node === second ? 0 : 1;
                const weight = firstWeight + secondWeight;
                if (overlapX < overlapY) {
                    const direction = dx < 0 ? -1 : 1;
                    first.x -= overlapX * direction * firstWeight / weight;
                    second.x += overlapX * direction * secondWeight / weight;
                } else {
                    const direction = dy < 0 ? -1 : 1;
                    first.y -= overlapY * direction * firstWeight / weight;
                    second.y += overlapY * direction * secondWeight / weight;
                }
            }
        }
        return collided;
    }

    function freezeStaticLayout() {
        let collided = false;
        for (let pass = 0; pass < 8; pass++) {
            collided = separateCards();
            nodes.forEach(bounds);
        }
        nodes.forEach(node => {
            if (collided) {
                node.x = node.restX;
                node.y = node.restY;
            }
            node.previousX = node.x;
            node.previousY = node.y;
        });
    }

    function paintRope(path, first, second, ceiling) {
        const dx = second.x - first.x;
        const dy = second.y - first.y;
        const bend = ceiling ? 2 : clamp(Math.abs(dx) * .035 + 4, 4, 15);
        const middleX = (first.x + second.x) / 2 + (Math.abs(dy) > Math.abs(dx) ? bend : 0);
        const middleY = (first.y + second.y) / 2 + (Math.abs(dx) >= Math.abs(dy) ? bend : 0);
        path.setAttribute('d', `M${first.x.toFixed(2)} ${first.y.toFixed(2)} Q${middleX.toFixed(2)} ${middleY.toFixed(2)} ${second.x.toFixed(2)} ${second.y.toFixed(2)}`);
    }

    function paint() {
        nodes.forEach(node => {
            const idleRotation = reducedMotion.matches ? 0 : Math.sin(elapsed * .72 + node.index * .9) * .65;
            const rotation = reducedMotion.matches ? 0 : clamp((node.x - node.restX) * .055 + (node.x - node.previousX) * .75 + idleRotation, -6, 6);
            node.card.style.transform = `translate3d(${(node.x - node.width / 2).toFixed(2)}px, ${(node.y - node.height / 2).toFixed(2)}px, 0) rotate(${rotation.toFixed(3)}deg)`;
        });
        anchors.forEach((anchor, index) => paintRope(paths.get(`anchor-${index}`), anchor, nodes[index], true));
        connections.forEach(([first, second]) => paintRope(paths.get(`${first}-${second}`), nodes[first], nodes[second], false));
        element.dataset.ropeState = drag ? 'dragging' : 'ready';
    }

    function animate(time) {
        frame = 0;
        if (!permitted()) return;
        const step = previousTime ? Math.min((time - previousTime) / (1000 / 60), 2) : 1;
        previousTime = time;
        elapsed += step / 60;
        nodes.forEach(node => {
            if (drag?.node === node) {
                node.x = drag.x;
                node.y = drag.y;
                return;
            }
            const velocityX = (node.x - node.previousX) * Math.pow(.965, step);
            const velocityY = (node.y - node.previousY) * Math.pow(.965, step);
            node.previousX = node.x;
            node.previousY = node.y;
            const breeze = reducedMotion.matches ? 0 : Math.sin(elapsed * .75 + node.index * .6) * .014;
            node.x += velocityX + ((node.restX - node.x) * .0035 + breeze) * step * step;
            node.y += velocityY + ((node.restY - node.y) * .0035 + .04) * step * step;
        });
        for (let pass = 0; pass < 4; pass++) {
            solveRopes();
            separateCards();
            nodes.forEach(bounds);
        }
        paint();
        wake();
    }

    function releaseDrag(cancelled = false) {
        if (!drag) return;
        const previousDrag = drag;
        drag = null;
        const { node, pointerId } = previousDrag;
        const pointerRestTime = performance.now() - previousDrag.lastTime;
        const inertia = cancelled || reducedMotion.matches ? 0 : .72 * Math.exp(-pointerRestTime / 110);
        node.previousX = node.x - previousDrag.velocityX * inertia;
        node.previousY = node.y - previousDrag.velocityY * inertia;
        delete node.card.dataset.ropeDragging;
        if (node.card.hasPointerCapture(pointerId)) node.card.releasePointerCapture(pointerId);
        if (reducedMotion.matches) {
            freezeStaticLayout();
            stop();
        }
        paint();
        wake();
    }

    function pointerPosition(event, node, offsetX, offsetY) {
        const rect = element.getBoundingClientRect();
        const edge = compact.matches ? 8 : 12;
        return {
            x: clamp(event.clientX - rect.left - offsetX, node.width / 2 + edge, width - node.width / 2 - edge),
            y: clamp(event.clientY - rect.top - offsetY, node.height / 2 + edge, height - node.height / 2 - edge),
        };
    }

    function announce(message) {
        const status = element.querySelector('[data-rope-status]');
        if (status) status.textContent = message;
    }

    nodes.forEach(node => {
        node.card.addEventListener('pointerdown', event => {
            if (!permitted() || drag || event.isPrimary === false || event.button !== 0) return;
            const rect = element.getBoundingClientRect();
            drag = {
                node, pointerId: event.pointerId,
                offsetX: event.clientX - rect.left - node.x,
                offsetY: event.clientY - rect.top - node.y,
                x: node.x, y: node.y, lastTime: performance.now(), velocityX: 0, velocityY: 0,
            };
            node.card.setPointerCapture(event.pointerId);
            node.card.focus({ preventScroll: true });
            node.card.dataset.ropeDragging = 'true';
            wake();
        }, listenerOptions);
        node.card.addEventListener('pointermove', event => {
            if (drag?.node !== node || drag.pointerId !== event.pointerId) return;
            if (event.cancelable) event.preventDefault();
            const position = pointerPosition(event, node, drag.offsetX, drag.offsetY);
            const now = performance.now();
            const step = Math.max(.4, (now - drag.lastTime) / (1000 / 60));
            drag.velocityX = clamp((position.x - drag.x) / step, -12, 12);
            drag.velocityY = clamp((position.y - drag.y) / step, -12, 12);
            drag.x = position.x;
            drag.y = position.y;
            drag.lastTime = now;
            wake();
        }, { ...listenerOptions, passive: false });
        node.card.addEventListener('pointerup', event => {
            if (drag?.pointerId === event.pointerId) releaseDrag();
        }, listenerOptions);
        node.card.addEventListener('pointercancel', () => releaseDrag(true), listenerOptions);
        node.card.addEventListener('lostpointercapture', () => releaseDrag(true), listenerOptions);
        node.card.addEventListener('keydown', event => {
            if (['Home', 'Escape'].includes(event.key)) {
                event.preventDefault();
                releaseDrag(true);
                resize();
                announce('Network cards returned to their starting positions.');
                return;
            }
            const direction = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
            if (!direction) return;
            event.preventDefault();
            const distance = event.shiftKey ? 20 : 10;
            node.x += direction[0] * distance;
            node.y += direction[1] * distance;
            bounds(node);
            separateCards();
            nodes.forEach(bounds);
            if (reducedMotion.matches) freezeStaticLayout();
            paint();
            wake();
            announce('Card moved. Press Home to reset the network cards.');
        }, listenerOptions);
    });

    const visibilityObserver = new IntersectionObserver(entries => {
        inView = entries[0].isIntersecting;
        if (!inView) { releaseDrag(true); stop(); } else wake();
    }, { threshold: .01 });
    visibilityObserver.observe(element);
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(element);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { releaseDrag(true); stop(); } else wake();
    }, listenerOptions);
    document.addEventListener('ais:navigation-state', event => {
        menuOpen = Boolean(event.detail?.open);
        if (menuOpen) { releaseDrag(true); stop(); } else wake();
    }, listenerOptions);
    reducedMotion.addEventListener('change', () => {
        releaseDrag(true);
        stop();
        nodes.forEach(node => { node.previousX = node.x; node.previousY = node.y; });
        paint();
        wake();
    }, listenerOptions);
    window.addEventListener('pagehide', event => {
        pageSuspended = true;
        releaseDrag(true);
        stop();
        if (!event.persisted) cleanup();
    }, listenerOptions);
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        pageSuspended = false;
        resize();
    }, listenerOptions);
    document.addEventListener('livewire:navigating', cleanup, listenerOptions);

    function cleanup() {
        if (disposed) return;
        disposed = true;
        releaseDrag(true);
        stop();
        events.abort();
        visibilityObserver.disconnect();
        resizeObserver.disconnect();
        nodes.forEach(node => { node.card.style.removeProperty('transform'); delete node.card.dataset.ropeDragging; });
        createdPaths.forEach(path => path.remove());
        delete element.dataset.ropeState;
    }

    resize();
    return cleanup;
}
