/** Progressive motion: content stays visible until an in-view animation starts. */
const instances = new WeakMap();
const controllers = new Set();
let hookedLivewire = null;
let removeLivewireHook = null;
let waitingForLivewire = false;

const REVEAL_TARGETS = [
    '[data-motion-reveal]',
    '.hero-copy > .eyebrow', '.hero-copy h1', '.hero-subtitle', '.hero-copy > p',
    '.hero-buttons', '.hero-footnote', '.section-heading', '.audience-card',
    '.industry-card', '.how-intro', '.process-step', '.gallery-tabs',
    '.gallery-empty', '.gallery-privacy', '.faq-wrap > div:first-child',
    '.faq-list details', '.cta-box', '.auth-story-content', '.auth-form',
    '.page-heading', '.overview-banner', '.privacy-note',
    '.portal-content > .stats-grid > .stat-card', '.portal-content > .panel',
    '.dashboard-bottom > .panel', '.campaign-detail-grid > div > .panel',
    '.creators-grid > .creator-card', '.invite-list > .invite-card',
    '.wizard-shell [wire\\:key^="brief-step-"]',
    '.wizard-shell [wire\\:key^="creator-step-"]',
].join(',');

const REVEAL_GROUPS = [
    '[data-motion-group]', '.hero-copy', '.audience-cards', '.stats-grid',
    '.creators-grid', '.dashboard-bottom', '.campaign-detail-grid',
    '.how-grid', '.faq-wrap', '.process-list', '.invite-list',
].join(',');

function connectLivewire() {
    const livewire = window.Livewire;
    if (!livewire?.hook || livewire === hookedLivewire) return;
    removeLivewireHook?.();
    hookedLivewire = livewire;
    const dispose = livewire.hook('morphed', ({ el }) => {
        controllers.forEach(controller => controller.refresh(el));
    });
    removeLivewireHook = typeof dispose === 'function' ? dispose : null;
}

function waitForLivewire() {
    connectLivewire();
    if (hookedLivewire || waitingForLivewire) return;
    waitingForLivewire = true;
    document.addEventListener('livewire:init', () => {
        waitingForLivewire = false;
        connectLivewire();
    }, { once: true });
}

function elementsWithin(root, selector) {
    const elements = [];
    if (root.nodeType === 1 && root.matches(selector)) elements.push(root);
    elements.push(...root.querySelectorAll(selector));
    return elements;
}

/**
 * Call after DOM ready and after livewire:navigated. Reinitializing the same
 * root disposes its previous observers, listeners, animations and hero state.
 */
export function initializeMotion(root = document) {
    instances.get(root)?.();
    if (!root?.querySelectorAll) return () => {};

    const doc = root.nodeType === 9 ? root : root.ownerDocument;
    const win = doc.defaultView;
    if (!win?.matchMedia) return () => {};
    const reduced = win.matchMedia('(prefers-reduced-motion: reduce)');
    const desktopPointer = win.matchMedia('(min-width: 901px) and (hover: hover) and (pointer: fine)');
    const canObserve = typeof win.IntersectionObserver === 'function';
    const canAnimate = typeof win.Element?.prototype.animate === 'function';
    const canTranslate = Boolean(win.CSS?.supports('translate', '0 1px'));
    const targets = new Set();
    const seen = new WeakSet();
    const animations = new Map();
    const heroes = new Map();
    const listeners = [];
    let disposed = false;
    let refreshFrame = 0;
    const queuedRoots = new Set();

    function listen(target, event, callback, options) {
        target.addEventListener(event, callback, options);
        listeners.push(() => target.removeEventListener(event, callback, options));
    }

    function listenMedia(query, callback) {
        if (query.addEventListener) listen(query, 'change', callback);
        else {
            query.addListener(callback);
            listeners.push(() => query.removeListener(callback));
        }
    }

    function belongs(element) {
        return element?.isConnected && (root.nodeType === 9 || root === element || root.contains(element));
    }

    function clearReveal(element) {
        const animation = animations.get(element);
        animations.delete(element);
        animation?.cancel();
        if (!disposed) element.setAttribute('data-ais-reveal', 'done');
    }

    function reveal(element, delay = 140) {
        if (seen.has(element) || !belongs(element)) return;
        seen.add(element);
        revealObserver?.unobserve(element);
        if (reduced.matches || doc.hidden || !canAnimate || element.contains(doc.activeElement)) {
            element.setAttribute('data-ais-reveal', 'done');
            return;
        }
        const duration = element.matches('.stat-card, .process-step, .faq-list details') ? 820 : 900;
        const frames = canTranslate
            ? [{ opacity: 0, translate: '0 18px' }, { opacity: 1, translate: '0 0' }]
            : [{ opacity: 0 }, { opacity: 1 }];

        try {
            element.setAttribute('data-ais-reveal', 'running');
            const animation = element.animate(frames, {
                duration, delay, easing: 'cubic-bezier(.22,.6,.3,1)', fill: 'backwards',
            });
            animations.set(element, animation);
            animation.finished.then(() => {
                if (animations.get(element) === animation) clearReveal(element);
            }, () => {
                if (animations.get(element) === animation) clearReveal(element);
            });
        } catch {
            clearReveal(element);
        }
    }

    function revealBatch(entries) {
        const entering = entries.filter(entry => entry.isIntersecting && !seen.has(entry.target) && belongs(entry.target));
        if (!entering.length) return;

        // Measure the complete visible row before starting any animations. This
        // keeps CSS-reordered cards and cards inside separate columns in visual
        // left-to-right order, without counting hidden or off-screen siblings.
        const candidates = [];
        for (const element of targets) {
            if (seen.has(element) || !belongs(element) || !element.getClientRects().length) continue;
            const bounds = element.getBoundingClientRect();
            if (!bounds.width || !bounds.height || bounds.bottom <= 0 || bounds.top >= win.innerHeight - 28 || bounds.right <= 0 || bounds.left >= win.innerWidth) continue;
            if (win.getComputedStyle(element).visibility === 'hidden') continue;
            candidates.push({ element, bounds, group: element.closest(REVEAL_GROUPS) ?? element.parentElement });
        }
        const schedules = new Map();
        for (const entry of entering) {
            const candidate = candidates.find(item => item.element === entry.target);
            if (!candidate) { schedules.set(entry.target, 140); continue; }
            const intro = candidate.group?.matches('.hero-copy');
            const peers = candidates.filter(item => item.group === candidate.group && (intro || Math.abs(item.bounds.top - candidate.bounds.top) <= 18));
            peers.sort((a, b) => intro ? a.bounds.top - b.bounds.top || a.bounds.left - b.bounds.left : a.bounds.left - b.bounds.left || a.bounds.top - b.bounds.top);
            peers.forEach((item, index) => {
                const delay = 140 + Math.min(index, intro ? 4 : 5) * (intro ? 70 : 140);
                if (!schedules.has(item.element)) schedules.set(item.element, delay);
            });
        }
        // Start in the observer callback, before the next paint. A separate RAF
        // would briefly expose a row and then hide it for its entrance delay.
        schedules.forEach((delay, element) => reveal(element, delay));
    }

    const revealObserver = canObserve ? new win.IntersectionObserver(entries => {
        revealBatch(entries);
    }, { threshold: 0.08, rootMargin: '0px 0px -28px 0px' }) : null;

    function setHeroState(hero) {
        const running = !disposed && hero.inView && !doc.hidden && !reduced.matches;
        hero.element.classList.toggle('ais-hero-running', running);
        if (!running || !desktopPointer.matches) resetTilt(hero);
    }

    function resetTilt(hero) {
        if (hero.frame) win.cancelAnimationFrame(hero.frame);
        hero.frame = 0;
        hero.pointer = null;
        hero.element.style.setProperty('--ais-tilt-x', '0deg');
        hero.element.style.setProperty('--ais-tilt-y', '0deg');
    }

    const heroObserver = canObserve ? new win.IntersectionObserver(entries => {
        entries.forEach(entry => {
            const hero = heroes.get(entry.target);
            if (!hero) return;
            hero.inView = entry.isIntersecting;
            setHeroState(hero);
        });
    }, { threshold: 0 }) : null;

    function rememberVariable(element, name) {
        return [element, name, element.style.getPropertyValue(name), element.style.getPropertyPriority(name)];
    }

    function registerHero(element) {
        if (heroes.has(element) || !heroObserver) return;
        const card = element.querySelector('.partnership-card');
        if (!card) return;
        const hero = {
            element, card, inView: false, frame: 0, pointer: null,
            variables: [rememberVariable(card, '--ais-base-transform'),
                rememberVariable(element, '--ais-tilt-x'), rememberVariable(element, '--ais-tilt-y')],
        };
        // Capture the established rotation before enabling our additional 3D tilt.
        const baseTransform = win.getComputedStyle(card).transform;
        card.style.setProperty('--ais-base-transform', baseTransform === 'none' ? 'rotate(-5deg)' : baseTransform);
        element.classList.add('ais-hero-motion');
        heroes.set(element, hero);
        heroObserver.observe(element);

        listen(element, 'pointermove', event => {
            if (event.pointerType !== 'mouse' || !desktopPointer.matches || !hero.inView || doc.hidden || reduced.matches) return;
            hero.pointer = { x: event.clientX, y: event.clientY };
            if (hero.frame) return;
            hero.frame = win.requestAnimationFrame(() => {
                hero.frame = 0;
                if (disposed || !hero.pointer || !hero.inView || doc.hidden || reduced.matches || !desktopPointer.matches) return;
                const bounds = element.getBoundingClientRect();
                if (!bounds.width || !bounds.height) return;
                const x = Math.max(-0.5, Math.min(0.5, (hero.pointer.x - bounds.left) / bounds.width - 0.5));
                const y = Math.max(-0.5, Math.min(0.5, (hero.pointer.y - bounds.top) / bounds.height - 0.5));
                element.style.setProperty('--ais-tilt-x', `${(-y * 6).toFixed(2)}deg`);
                element.style.setProperty('--ais-tilt-y', `${(x * 8).toFixed(2)}deg`);
            });
        }, { passive: true });
        listen(element, 'pointerleave', () => resetTilt(hero), { passive: true });
        listen(element, 'pointercancel', () => resetTilt(hero), { passive: true });
    }

    function scan(scope = root) {
        if (disposed || !scope?.querySelectorAll) return;
        for (const element of elementsWithin(scope, REVEAL_TARGETS)) {
            if (!belongs(element) || targets.has(element)) continue;
            // Animate the outer section once; never compound parent/child fades.
            const ancestor = element.parentElement?.closest(REVEAL_TARGETS);
            if (ancestor && belongs(ancestor)) continue;
            targets.add(element);
            element.setAttribute('data-ais-reveal', 'ready');
            if (reduced.matches || !revealObserver || !canAnimate) {
                seen.add(element);
                element.setAttribute('data-ais-reveal', 'done');
            } else revealObserver.observe(element);
        }
        elementsWithin(scope, '.hero-visual').forEach(element => {
            if (belongs(element)) registerHero(element);
        });
        // Release references after Livewire replaces a wizard step or a panel.
        for (const element of targets) {
            if (!belongs(element)) {
                revealObserver?.unobserve(element);
                clearReveal(element);
                targets.delete(element);
            }
        }
    }

    function refresh(scope = root) {
        if (disposed) return;
        if (scope?.querySelectorAll) queuedRoots.add(scope);
        if (refreshFrame) return;
        refreshFrame = win.requestAnimationFrame(() => {
            refreshFrame = 0;
            if (disposed) return;
            queuedRoots.forEach(scan);
            queuedRoots.clear();
        });
    }

    function preferenceChanged() {
        doc.documentElement.classList.toggle('ais-motion-smooth', !reduced.matches);
        if (reduced.matches) {
            revealObserver?.disconnect();
            for (const element of targets) {
                seen.add(element);
                clearReveal(element);
            }
        }
        heroes.forEach(setHeroState);
        refresh();
    }

    const mutationObserver = typeof win.MutationObserver === 'function' ? new win.MutationObserver(records => {
        for (const record of records) {
            if (record.type !== 'childList') continue;
            record.addedNodes.forEach(node => { if (node.nodeType === 1) refresh(node); });
            if (record.removedNodes.length) refresh(root);
        }
    }) : null;

    function cleanup() {
        if (disposed) return;
        disposed = true;
        revealObserver?.disconnect();
        heroObserver?.disconnect();
        mutationObserver?.disconnect();
        if (refreshFrame) win.cancelAnimationFrame(refreshFrame);
        queuedRoots.clear();
        for (const element of animations.keys()) clearReveal(element);
        targets.forEach(element => element.removeAttribute('data-ais-reveal'));
        heroes.forEach(hero => {
            if (hero.frame) win.cancelAnimationFrame(hero.frame);
            hero.element.classList.remove('ais-hero-motion', 'ais-hero-running');
            hero.variables.forEach(([element, name, value, priority]) => {
                if (value) element.style.setProperty(name, value, priority);
                else element.style.removeProperty(name);
            });
        });
        listeners.forEach(remove => remove());
        controllers.delete(controller);
        if (instances.get(root) === cleanup) instances.delete(root);
        if (![...controllers].some(item => item.document === doc)) doc.documentElement.classList.remove('ais-motion-smooth');
        if (!controllers.size && removeLivewireHook) {
            removeLivewireHook();
            removeLivewireHook = null;
            hookedLivewire = null;
        }
        targets.clear();
        heroes.clear();
    }

    const controller = { refresh, document: doc };
    controllers.add(controller);
    instances.set(root, cleanup);
    listen(root, 'focusin', event => {
        for (const element of animations.keys()) {
            if (element.contains(event.target)) clearReveal(element);
        }
    }, { capture: true });
    listen(doc, 'visibilitychange', () => {
        if (doc.hidden) for (const element of animations.keys()) clearReveal(element);
        heroes.forEach(setHeroState);
    });
    listen(doc, 'livewire:navigating', cleanup);
    listen(win, 'resize', () => heroes.forEach(resetTilt), { passive: true });
    listenMedia(reduced, preferenceChanged);
    listenMedia(desktopPointer, () => heroes.forEach(setHeroState));
    preferenceChanged();
    scan(root);
    mutationObserver?.observe(root.nodeType === 9 ? doc.documentElement : root, { childList: true, subtree: true });
    waitForLivewire();
    return cleanup;
}
