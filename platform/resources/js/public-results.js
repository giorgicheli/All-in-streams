export function initializePublicResults(root = document) {
    const controller = new AbortController();
    root.querySelectorAll('[data-results-gallery]').forEach(gallery => {
        const buttons = [...gallery.querySelectorAll('[data-result-filter]')];
        const cards = [...gallery.querySelectorAll('[data-result-region]')];
        const grid = gallery.querySelector('.results-grid');
        const empty = gallery.querySelector('[data-results-empty]');
        const regionLabel = gallery.querySelector('[data-results-region-label]');
        const status = gallery.querySelector('[data-results-status]');
        function select(button) {
            const region = button.dataset.resultFilter;
            buttons.forEach(item => {
                item.classList.toggle('selected', item === button);
                item.setAttribute('aria-selected', String(item === button));
                item.tabIndex = item === button ? 0 : -1;
            });
            let count = 0;
            cards.forEach(card => {
                card.hidden = region !== '*' && card.dataset.resultRegion !== region;
                if (!card.hidden) count++;
            });
            if (grid) grid.hidden = count === 0;
            if (empty) empty.hidden = count !== 0;
            if (regionLabel) regionLabel.textContent = button.textContent.trim();
            if (status) status.textContent = `${count} campaign ${count === 1 ? 'result' : 'results'} · ${button.textContent.trim()}`;
        }
        buttons.forEach((button, index) => {
            button.addEventListener('click', () => select(button), { signal: controller.signal });
            button.addEventListener('keydown', event => {
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % buttons.length;
                else if (event.key === 'ArrowLeft') next = (index - 1 + buttons.length) % buttons.length;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = buttons.length - 1;
                else return;
                event.preventDefault();
                buttons[next].focus();
                select(buttons[next]);
            }, { signal: controller.signal });
        });
        if (buttons[0]) select(buttons[0]);
    });
    return () => controller.abort();
}

export function initializeNetworkCounters(root = document) {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const format = new Intl.NumberFormat('en');
    const counters = new Map();
    let observer;
    root.querySelectorAll('[data-network-count]').forEach(element => {
        const value = Number(element.dataset.networkCount);
        const card = element.closest('.network-stat-card');
        if (!card || !Number.isSafeInteger(value) || value < 0) return;
        counters.set(card, { element, value, seen: false, frame: 0 });
    });
    function finish(item) {
        cancelAnimationFrame(item.frame);
        item.frame = 0;
        item.seen = true;
        item.element.textContent = format.format(item.value);
    }
    function start(card) {
        const item = counters.get(card);
        if (!item || item.seen) return;
        item.seen = true;
        observer?.unobserve(card);
        if (reduced.matches || document.hidden) { finish(item); return; }
        const startTime = performance.now() + 60;
        function tick(now) {
            const progress = Math.max(0, Math.min(1, (now - startTime) / 1400));
            item.element.textContent = format.format(Math.round(item.value * (1 - (1 - progress) ** 3)));
            if (progress < 1) item.frame = requestAnimationFrame(tick);
            else finish(item);
        }
        item.frame = requestAnimationFrame(tick);
    }
    if ('IntersectionObserver' in window && !reduced.matches) {
        const inset = Math.round(Math.max(64, Math.min(160, innerHeight * .18)));
        observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) start(entry.target); }), { rootMargin: `0px 0px -${inset}px 0px`, threshold: 0 });
        counters.forEach((_, card) => observer.observe(card));
    }
    function preferenceChanged() { if (reduced.matches) { observer?.disconnect(); counters.forEach(finish); } }
    function visibilityChanged() { if (document.hidden) counters.forEach(item => { if (item.frame) finish(item); }); }
    reduced.addEventListener('change', preferenceChanged);
    document.addEventListener('visibilitychange', visibilityChanged);
    return () => {
        observer?.disconnect();
        counters.forEach(finish);
        reduced.removeEventListener('change', preferenceChanged);
        document.removeEventListener('visibilitychange', visibilityChanged);
    };
}
