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
    let curtain;
    let curtainAnimation;
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
        curtainAnimation?.cancel();
        curtain?.remove();
        curtain = curtainAnimation = undefined;
        root.removeAttribute('data-theme-transitioning');
        desiredTheme = theme;
        const update = () => { if (generation === sequence) apply(theme); };
        const finish = () => {
            if (generation !== sequence) return;
            root.removeAttribute('data-theme-transitioning');
            activeTransition = undefined;
        };
        if (!button || reducedMotion.matches) { update(); return; }
        const verticalWipe = () => {
            if (typeof Element.prototype.animate !== 'function') {
                root.setAttribute('data-theme-transitioning', '');
                update();
                fallbackTimer = setTimeout(finish, 520);
                return;
            }
            const layer = document.createElement('div');
            layer.className = 'theme-wipe';
            layer.setAttribute('aria-hidden', 'true');
            layer.dataset.direction = theme === 'dark' ? 'up' : 'down';
            layer.style.backgroundColor = theme === 'dark' ? '#090a0c' : '#f5f6fa';
            if (matchMedia('(max-width: 700px)').matches) {
                const header = document.querySelector('.site-header');
                const bottom = header?.getBoundingClientRect().bottom ?? 0;
                layer.style.top = `${Math.max(0, Math.min(innerHeight, bottom))}px`;
            }
            document.body.append(layer);
            curtain = layer;
            curtainAnimation = layer.animate([{ transform: 'scaleY(0)' }, { transform: 'scaleY(1)' }], {
                duration: 540, easing: 'cubic-bezier(.2,.7,.15,1)', fill: 'forwards',
            });
            curtainAnimation.finished.then(() => {
                if (generation !== sequence) return;
                update();
                curtainAnimation = layer.animate([{ opacity: 1 }, { opacity: 0 }], {
                    duration: 220, easing: 'ease-out', fill: 'forwards',
                });
                return curtainAnimation.finished;
            }).catch(() => {}).finally(() => {
                layer.remove();
                if (curtain === layer) curtain = curtainAnimation = undefined;
                finish();
            });
        };
        if (typeof document.startViewTransition !== 'function') {
            verticalWipe();
            return;
        }
        try {
            const transition = document.startViewTransition(update);
            activeTransition = transition;
            transition.ready.then(() => {
                if (generation !== sequence) return;
                return root.animate([
                    { clipPath: theme === 'dark' ? 'inset(100% 0 0 0)' : 'inset(0 0 100% 0)' },
                    { clipPath: 'inset(0 0 0 0)' },
                ], {
                    duration: 820, easing: 'cubic-bezier(.2,.7,.15,1)',
                    pseudoElement: '::view-transition-new(root)',
                }).finished;
            }).catch(() => {}).finally(finish);
            transition.finished.catch(() => {});
        } catch {
            verticalWipe();
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
        curtainAnimation?.cancel();
        curtain?.remove();
        activeTransition?.skipTransition();
        root.removeAttribute('data-theme-transitioning');
    };
}
