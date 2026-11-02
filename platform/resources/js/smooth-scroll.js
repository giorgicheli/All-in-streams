import Lenis from 'lenis';

export function initializeSmoothScroll() {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const desktopPointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    let scroll = null;
    let navigationOpen = false;

    const destroy = () => {
        scroll?.destroy();
        scroll = null;
    };

    const configure = () => {
        destroy();
        if (reducedMotion.matches || !desktopPointer.matches || document.hidden || document.querySelector('.portal')) return;

        scroll = new Lenis({
            autoRaf: true,
            autoResize: true,
            smoothWheel: true,
            syncTouch: false,
            lerp: 0.095,
            wheelMultiplier: 0.9,
            anchors: { duration: 0.85 },
            stopInertiaOnNavigate: true,
            prevent: (node) => Boolean(node.closest?.('input, textarea, select, [contenteditable="true"], [role="dialog"], .table-wrap, .sidebar, [data-lenis-prevent]')),
        });
        if (navigationOpen) scroll.stop();
    };

    const navigationChanged = event => {
        navigationOpen = event.detail?.open === true;
        if (navigationOpen) scroll?.stop();
        else scroll?.start();
    };

    reducedMotion.addEventListener('change', configure);
    desktopPointer.addEventListener('change', configure);
    document.addEventListener('visibilitychange', configure);
    document.addEventListener('ais:navigation-state', navigationChanged);
    configure();

    return () => {
        destroy();
        reducedMotion.removeEventListener('change', configure);
        desktopPointer.removeEventListener('change', configure);
        document.removeEventListener('visibilitychange', configure);
        document.removeEventListener('ais:navigation-state', navigationChanged);
    };
}
