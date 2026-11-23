const preferenceKey = 'ais:color-theme';
const validTheme = value => value === 'dark' || value === 'light';

export function initializeColorTheme() {
    const root = document.documentElement;
    if (!root.classList.contains('site-redesign')) return () => {};
    const controller = new AbortController();
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const buttons = [...document.querySelectorAll('[data-theme-toggle]')];
    const locked = root.dataset.themeLocked === 'true';
    let desiredTheme = validTheme(root.dataset.colorTheme) ? root.dataset.colorTheme : 'dark';
    let activeTransition;
    let fallbackTimer;
    let sequence = 0;

    function apply(theme) {
        root.dataset.colorTheme = theme;
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = theme === 'dark' ? '#090a0c' : '#f5f6fa';
        for (const button of buttons) {
            const label = theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme';
            button.setAttribute('aria-pressed', String(theme === 'dark'));
            button.setAttribute('aria-label', 'Dark theme');
            button.title = label;
        }
        document.dispatchEvent(new CustomEvent('ais:theme-change', { detail: { theme } }));
    }

    function change(theme, button) {
        const generation = ++sequence;
        activeTransition?.skipTransition();
        activeTransition = undefined;
        clearTimeout(fallbackTimer);
        root.removeAttribute('data-theme-transitioning');
        desiredTheme = theme;
        const update = () => { if (generation === sequence) apply(theme); };
        const finish = () => {
            if (generation !== sequence) return;
            root.removeAttribute('data-theme-transitioning');
            activeTransition = undefined;
        };
        if (!button || reducedMotion.matches) { update(); return; }
        if (typeof document.startViewTransition !== 'function') {
            root.setAttribute('data-theme-transitioning', '');
            update();
            fallbackTimer = setTimeout(finish, 520);
            return;
        }
        try {
            const bounds = button.getBoundingClientRect();
            const x = bounds.left + bounds.width / 2;
            const y = bounds.top + bounds.height / 2;
            const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
            const transition = document.startViewTransition(update);
            activeTransition = transition;
            transition.ready.then(() => {
                if (generation !== sequence) return;
                return root.animate([
                    { clipPath: `circle(0px at ${x}px ${y}px)` },
                    { clipPath: `circle(${radius}px at ${x}px ${y}px)` },
                ], {
                    duration: 760, easing: 'cubic-bezier(.2,.7,.15,1)',
                    pseudoElement: '::view-transition-new(root)',
                }).finished;
            }).catch(() => {}).finally(finish);
            transition.finished.catch(() => {});
        } catch {
            root.setAttribute('data-theme-transitioning', '');
            update();
            fallbackTimer = setTimeout(finish, 520);
        }
    }

    apply(locked ? 'light' : desiredTheme);
    for (const button of buttons) {
        button.addEventListener('click', () => {
            if (locked) return;
            const next = desiredTheme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem(preferenceKey, next); } catch {}
            change(next, button);
        }, { signal: controller.signal });
    }
    addEventListener('storage', event => {
        if (locked || (event.key !== preferenceKey && event.key !== null)) return;
        if (event.newValue !== null && !validTheme(event.newValue)) return;
        const next = validTheme(event.newValue) ? event.newValue : root.dataset.defaultTheme;
        change(validTheme(next) ? next : 'dark');
    }, { signal: controller.signal });

    return () => {
        sequence++;
        controller.abort();
        clearTimeout(fallbackTimer);
        activeTransition?.skipTransition();
        root.removeAttribute('data-theme-transitioning');
    };
}
