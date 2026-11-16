export function initializeHeroHeadlines(root = document) {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const controllers = [...root.querySelectorAll('[data-hero-headlines]')].map(heading => {
        const items = [...heading.querySelectorAll('[data-hero-headline]')];
        let current = 0;
        let timer = 0;
        let animations = [];
        let disposed = false;

        function settle() {
            animations.forEach(animation => animation.cancel());
            animations = [];
            items.forEach((item, index) => { item.hidden = index !== current; });
        }

        function stop() {
            clearTimeout(timer);
            timer = 0;
            settle();
        }

        function schedule() {
            if (disposed || reduced.matches || document.hidden || items.length < 2) return;
            clearTimeout(timer);
            timer = setTimeout(rotate, 6000);
        }

        async function rotate() {
            if (disposed || reduced.matches || document.hidden) return;
            const outgoing = items[current];
            current = (current + 1) % items.length;
            const incoming = items[current];
            incoming.hidden = false;
            if (typeof incoming.animate !== 'function') {
                settle();
                schedule();
                return;
            }
            const outgoingAnimation = outgoing.animate(
                [{ opacity: 1, translate: '0 0' }, { opacity: 0, translate: '0 -7px' }],
                { duration: 420, easing: 'ease-in-out', fill: 'forwards' }
            );
            const incomingAnimation = incoming.animate(
                [{ opacity: 0, translate: '0 8px' }, { opacity: 1, translate: '0 0' }],
                { duration: 700, delay: 160, easing: 'cubic-bezier(.22,.61,.36,1)', fill: 'both' }
            );
            animations = [outgoingAnimation, incomingAnimation];
            await Promise.allSettled(animations.map(animation => animation.finished));
            if (disposed || !animations.includes(incomingAnimation)) return;
            settle();
            schedule();
        }

        return { start: schedule, stop, dispose() { disposed = true; stop(); } };
    });

    function refresh() {
        controllers.forEach(controller => { controller.stop(); controller.start(); });
    }
    document.addEventListener('visibilitychange', refresh);
    reduced.addEventListener('change', refresh);
    controllers.forEach(controller => controller.start());
    return () => {
        controllers.forEach(controller => controller.dispose());
        document.removeEventListener('visibilitychange', refresh);
        reduced.removeEventListener('change', refresh);
    };
}