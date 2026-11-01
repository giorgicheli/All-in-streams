import Lenis from 'lenis';

export function initializeSmoothScroll() {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const desktopPointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    let scroll = null;

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
    };

    reducedMotion.addEventListener('change', configure);
    desktopPointer.addEventListener('change', configure);
    document.addEventListener('visibilitychange', configure);
    configure();

    return () => {
        destroy();
        reducedMotion.removeEventListener('change', configure);
        desktopPointer.removeEventListener('change', configure);
        document.removeEventListener('visibilitychange', configure);
    };
}
