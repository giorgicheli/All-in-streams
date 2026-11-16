export function initializePublicRefinements(root = document) {
    const events = new AbortController();
    const dropdowns = [...root.querySelectorAll('[data-platform-dropdown]')];
    const controls = new Map();
    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('summary');
        const links = [...dropdown.querySelectorAll('.platform-dropdown-panel a')];
        let timer;
        let hovering = false;
        function open() { clearTimeout(timer); dropdown.open = true; }
        function close({ focus = false } = {}) {
            clearTimeout(timer);
            dropdown.open = false;
            if (focus) trigger.focus();
        }
        function deferClose() {
            clearTimeout(timer);
            timer = setTimeout(() => { if (!hovering && !dropdown.contains(document.activeElement)) close(); }, 140);
        }
        dropdown.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') { hovering = true; open(); } }, { signal: events.signal });
        dropdown.addEventListener('pointerleave', event => { if (event.pointerType === 'mouse') { hovering = false; deferClose(); } }, { signal: events.signal });
        dropdown.addEventListener('focusout', deferClose, { signal: events.signal });
        dropdown.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close({ focus: true }); return; }
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            open();
            const index = links.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1
                : event.key === 'ArrowDown' ? (index + 1) % links.length
                : index <= 0 ? links.length - 1 : index - 1;
            links[next]?.focus();
        }, { signal: events.signal });
        controls.set(dropdown, { close, dispose() { clearTimeout(timer); } });
    });
    document.addEventListener('pointerdown', event => controls.forEach((control, dropdown) => { if (!dropdown.contains(event.target)) control.close(); }), { signal: events.signal });
    document.addEventListener('ais:navigation-state', event => { if (event.detail?.open) controls.forEach(control => control.close()); }, { signal: events.signal });
    return () => { events.abort(); controls.forEach(control => { control.dispose(); control.close(); }); };
}