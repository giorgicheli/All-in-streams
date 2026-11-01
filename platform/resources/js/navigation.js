export function initializeMobileNavigation() {
    const trigger = document.querySelector('[data-mobile-menu-open]');
    const menu = document.querySelector('[data-mobile-menu]');
    const closeButton = menu?.querySelector('[data-mobile-menu-close]');
    if (!trigger || !menu || !closeButton) return () => {};

    const controller = new AbortController();
    const options = { signal: controller.signal };
    const mobile = window.matchMedia('(max-width: 700px)');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let state = 'closed';
    let timer;
    let frame;
    let previousFocus = null;
    let scrollPosition = 0;
    let bodyStyles = null;
    let inertElements = [];

    function lockPage() {
        scrollPosition = window.scrollY;
        bodyStyles = Object.fromEntries(['position', 'top', 'left', 'right', 'width', 'overflow'].map(name => [name, document.body.style[name]]));
        Object.assign(document.body.style, { position: 'fixed', top: `-${scrollPosition}px`, left: '0', right: '0', width: '100%', overflow: 'hidden' });
        document.documentElement.classList.add('site-menu-open');
        // The dialog is a sibling of the header/content. Keep every unrelated
        // body subtree inert while leaving the dialog's own ancestor usable.
        let branch = menu;
        while (branch?.parentElement && branch.parentElement !== document.documentElement) {
            for (const sibling of branch.parentElement.children) {
                if (sibling !== branch && !['SCRIPT', 'STYLE', 'LINK'].includes(sibling.tagName)) {
                    inertElements.push([sibling, sibling.inert]);
                    sibling.inert = true;
                }
            }
            branch = branch.parentElement;
            if (branch === document.body) break;
        }
    }

    function unlockPage() {
        inertElements.forEach(([element, wasInert]) => { element.inert = wasInert; });
        inertElements = [];
        if (bodyStyles) {
            Object.assign(document.body.style, bodyStyles);
            bodyStyles = null;
            const oldBehavior = document.documentElement.style.scrollBehavior;
            document.documentElement.style.scrollBehavior = 'auto';
            window.scrollTo(0, scrollPosition);
            document.documentElement.style.scrollBehavior = oldBehavior;
        }
        document.documentElement.classList.remove('site-menu-open');
    }

    function finishClose(restoreFocus = true) {
        clearTimeout(timer);
        cancelAnimationFrame(frame);
        menu.classList.remove('is-open');
        menu.hidden = true;
        menu.inert = true;
        state = 'closed';
        unlockPage();
        if (restoreFocus && previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
    }

    function close({ immediately = false, restoreFocus = true, afterClose = null } = {}) {
        if (state === 'closed') { afterClose?.(); return; }
        clearTimeout(timer);
        cancelAnimationFrame(frame);
        state = 'closing';
        trigger.setAttribute('aria-expanded', 'false');
        menu.classList.remove('is-open');
        menu.inert = true;
        const finish = () => { finishClose(restoreFocus); afterClose?.(); };
        if (immediately || reduced.matches) finish();
        else timer = setTimeout(finish, 420);
    }

    function open() {
        if (!mobile.matches || state === 'open') return;
        if (state === 'closing') finishClose(false);
        previousFocus = document.activeElement;
        lockPage();
        menu.hidden = false;
        menu.inert = false;
        menu.scrollTop = 0;
        state = 'open';
        trigger.setAttribute('aria-expanded', 'true');
        // Establish the closed pose before animating the panel and its links.
        menu.getBoundingClientRect();
        frame = requestAnimationFrame(() => {
            menu.classList.add('is-open');
            closeButton.focus({ preventScroll: true });
        });
    }

    trigger.addEventListener('click', open, options);
    closeButton.addEventListener('click', () => close(), options);
    document.addEventListener('keydown', event => {
        if (state !== 'open') return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        const focusable = [...menu.querySelectorAll('a[href],button:not([disabled]),[tabindex="0"]')].filter(element => element.getClientRects().length > 0);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        else if (!menu.contains(document.activeElement)) { event.preventDefault(); first?.focus(); }
    }, options);

    menu.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || event.defaultPrevented) return;
        event.preventDefault();
        const target = new URL(link.href, location.href);
        close({ restoreFocus: false, afterClose: () => {
            if (target.origin === location.origin && target.pathname === location.pathname && target.search === location.search && target.hash) {
                let anchorId = target.hash.slice(1);
                try { anchorId = decodeURIComponent(anchorId); } catch { /* Keep a malformed CMS fragment navigable. */ }
                const anchor = document.getElementById(anchorId);
                if (anchor) {
                    history.pushState(null, '', target.hash);
                    anchor.scrollIntoView({ behavior: reduced.matches ? 'instant' : 'smooth', block: 'start' });
                    const originalTabIndex = anchor.getAttribute('tabindex');
                    anchor.setAttribute('tabindex', '-1');
                    anchor.focus({ preventScroll: true });
                    if (originalTabIndex === null) anchor.addEventListener('blur', () => anchor.removeAttribute('tabindex'), { once: true });
                    return;
                }
            }
            location.assign(target.href);
        }});
    }, options);

    const resize = () => { if (!mobile.matches) close({ immediately: true }); };
    mobile.addEventListener('change', resize);

    return () => {
        controller.abort();
        mobile.removeEventListener('change', resize);
        trigger.setAttribute('aria-expanded', 'false');
        finishClose(false);
    };
}
