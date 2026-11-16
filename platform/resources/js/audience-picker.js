export function initializeAudiencePickers(root = document) {
    const events = new AbortController();
    root.querySelectorAll('[data-audience-picker]').forEach(picker => {
        const form = picker.closest('form');
        const page = picker.closest('[data-partner-enquiry]');
        const inputs = [...picker.querySelectorAll('input[type="checkbox"]')];
        const selected = picker.querySelector('[data-audience-selection]');
        const count = picker.querySelector('[data-audience-count]');
        const search = picker.querySelector('[data-audience-search]');
        function update() {
            const names = inputs.filter(input => input.checked).map(input => input.value);
            selected.replaceChildren();
            if (!names.length) selected.textContent = 'Select audience countries';
            names.forEach(name => { const chip = document.createElement('span'); chip.className = 'audience-country-chip'; chip.textContent = name; selected.append(chip); });
            count.textContent = `${names.length} selected`;
            inputs[0]?.setCustomValidity(page?.dataset.enquiryKind === 'creator' && !names.length ? 'Choose at least one audience country.' : '');
        }
        inputs.forEach(input => input.addEventListener('change', update, { signal: events.signal }));
        page?.addEventListener('enquiry:changed', update, { signal: events.signal });
        form?.addEventListener('invalid', event => {
            if (!inputs.includes(event.target)) return;
            // Native validation must be able to focus its checkbox even when
            // a previous search has filtered that country out of the list.
            if (search) search.value = '';
            picker.querySelectorAll('[data-audience-option]').forEach(option => { option.hidden = false; });
            picker.open = true;
        }, { capture: true, signal: events.signal });
        search?.addEventListener('input', () => picker.querySelectorAll('[data-audience-option]').forEach(option => { option.hidden = !option.textContent.toLowerCase().includes(search.value.trim().toLowerCase()); }), { signal: events.signal });
        search?.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); }, { signal: events.signal });
        picker.addEventListener('keydown', event => { if (event.key === 'Escape') { picker.open = false; picker.querySelector('summary').focus(); } }, { signal: events.signal });
        document.addEventListener('pointerdown', event => { if (!picker.contains(event.target)) picker.open = false; }, { signal: events.signal });
        update();
    });
    return () => events.abort();
}
