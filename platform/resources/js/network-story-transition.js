const instances = new WeakMap();

export function initializeNetworkStoryTransition(root = document) {
    instances.get(root)?.();
    const hero = root.querySelector('.hero-stage');
    const network = root.querySelector('[data-network-showcase]');
    if (!hero || !network) return () => {};
    const events = new AbortController();
    const options = { passive: true, signal: events.signal };
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const previousInert = hero.inert;
    let disposed = false;
    let menuOpen = false;
    let frame = 0;
    hero.classList.add('has-network-departure');

    function update() {
        frame = 0;
        if (disposed || menuOpen || document.hidden) return;
        const heroTop = hero.getBoundingClientRect().top + scrollY;
        const networkTop = network.getBoundingClientRect().top + scrollY;
        const start = Math.max(heroTop + 24, networkTop - innerHeight * .9);
        const end = Math.max(start + 180, networkTop - innerHeight * .18);
        const focused = hero.contains(document.activeElement);
        const progress = reduced.matches || focused ? 0 : Math.max(0, Math.min(1, (scrollY - start) / (end - start)));
        const eased = progress ** 3 * (progress * (progress * 6 - 15) + 10);
        hero.style.setProperty('--hero-story-opacity', String(1 - eased));
        hero.inert = previousInert || progress >= 1;
    }

    function schedule() {
        if (!disposed && !frame) frame = requestAnimationFrame(update);
    }

    addEventListener('scroll', schedule, options);
    addEventListener('resize', schedule, options);
    document.addEventListener('visibilitychange', schedule, options);
    document.addEventListener('focusin', schedule, options);
    document.addEventListener('focusout', schedule, options);
    document.addEventListener('ais:navigation-state', event => {
        menuOpen = Boolean(event.detail?.open);
        if (!menuOpen) schedule();
    }, { signal: events.signal });
    reduced.addEventListener('change', schedule, options);
    const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(schedule) : null;
    observer?.observe(hero);
    observer?.observe(network);
    document.fonts?.ready.then(schedule);
    schedule();

    const cleanup = () => {
        disposed = true;
        events.abort();
        observer?.disconnect();
        cancelAnimationFrame(frame);
        hero.classList.remove('has-network-departure');
        hero.style.removeProperty('--hero-story-opacity');
        hero.inert = previousInert;
    };
    instances.set(root, cleanup);
    return cleanup;
}
