export function initializePartnerEnquiry(root = document) {
    const page = root.querySelector('[data-partner-enquiry]');
    const form = page?.querySelector('[data-enquiry-form]');
    if (!page || !form) return () => {};

    const events = new AbortController();
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const kindInput = form.querySelector('[data-enquiry-kind-input]');
    const typeInput = form.querySelector('[data-enquiry-creator-input]');
    const categories = [...page.querySelectorAll('[data-enquiry-category]')];
    const types = [...page.querySelectorAll('[data-enquiry-type]')];
    const title = page.querySelector('[data-enquiry-title]');
    const description = page.querySelector('[data-enquiry-description]');
    const eyebrow = page.querySelector('[data-enquiry-eyebrow]');
    const submit = form.querySelector('[data-enquiry-submit]');
    const submitLabel = form.querySelector('[data-enquiry-submit-label]');
    const kick = form.querySelector('[name="kick_url"]');
    const twitch = form.querySelector('[name="twitch_url"]');
    const firstGeo = form.querySelector('#enquiry-creator-geo-0');
    const secondGeo = form.querySelector('#enquiry-creator-geo-1');
    const password = form.querySelector('[name="password"]');
    const confirmation = form.querySelector('[name="password_confirmation"]');
    let kind = page.dataset.enquiryKind || 'brand';
    let creatorType = page.dataset.enquiryCreatorType || 'streamer';
    let motion;
    let submitting = false;

    const copy = {
        brand: {
            eyebrow: 'LET’S MAKE IT HAPPEN',
            title: 'Tell us what you have in mind.',
            description: 'Share a few details so we can find your right match.',
            website: 'Company / brand website',
            websitePlaceholder: 'https://yourbrand.com',
            email: 'you@yourbrand.com',
            requirements: 'Campaign goals and preferred platforms',
            requirementsPlaceholder: 'Target audience, timeline, stream package, and goals…',
        },
        agency: {
            eyebrow: 'LET’S MAKE IT HAPPEN',
            title: 'Tell us what you have in mind.',
            description: 'Share your requirements so we can find the right streamers for your clients.',
            website: 'Agency website',
            websitePlaceholder: 'https://youragency.com',
            email: 'you@agency.com',
            requirements: 'Additional requirements',
            requirementsPlaceholder: 'Preferred platforms, campaign dates, creator preferences or KPIs…',
        },
        creator: {
            eyebrow: 'ONGOING PROJECTS. FIXED PAID DEALS.',
            title: 'Join our creator network and keep the deals coming.',
            description: 'Get matched with brands for regular paid collaborations. You focus on your content. We bring the projects and handle the deals.',
        },
    };
    const includes = (value, candidate) => (value || '').split(' ').includes(candidate);

    function validateChannels() {
        const needsChannel = kind === 'creator' && ['streamer', 'both'].includes(creatorType);
        twitch?.setCustomValidity(needsChannel && !twitch.value.trim() && !kick?.value.trim()
            ? 'Add at least one Kick or Twitch profile link.' : '');
    }

    function validateCountries() {
        secondGeo?.setCustomValidity(kind === 'creator' && firstGeo?.value && firstGeo.value === secondGeo.value
            ? 'Choose a different second audience country.' : '');
    }

    function validatePasswordConfirmation() {
        confirmation?.setCustomValidity(confirmation.value && password?.value !== confirmation.value
            ? 'Repeat the same password.' : '');
    }

    function refresh({ animate = false, updateUrl = false } = {}) {
        if (!copy[kind]) kind = 'brand';
        if (!['streamer', 'influencer', 'both'].includes(creatorType)) creatorType = 'streamer';
        page.dataset.enquiryKind = kind;
        page.dataset.enquiryCreatorType = creatorType;
        kindInput.value = kind;
        typeInput.value = creatorType;

        page.querySelectorAll('[data-enquiry-for]').forEach(element => {
            element.hidden = !includes(element.dataset.enquiryFor, kind);
            if (element.tagName === 'FIELDSET') element.disabled = element.hidden;
        });
        page.querySelectorAll('[data-enquiry-type-for]').forEach(element => {
            element.hidden = !includes(element.dataset.enquiryTypeFor, creatorType);
        });
        form.querySelectorAll('input, select, textarea').forEach(control => {
            control.disabled = Boolean(control.closest('[data-enquiry-for][hidden], [data-enquiry-type-for][hidden]'));
            if (control.hasAttribute('data-enquiry-required')) {
                control.required = !control.disabled && includes(control.dataset.enquiryRequired, kind);
            }
            if (control.hasAttribute('data-enquiry-type-required')) {
                control.required = !control.disabled && kind === 'creator' && includes(control.dataset.enquiryTypeRequired, creatorType);
            }
        });
        categories.forEach(link => {
            const active = link.dataset.enquiryCategory === kind;
            link.classList.toggle('is-selected', active);
            if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
        });
        types.forEach(link => {
            const active = link.dataset.enquiryType === creatorType;
            link.classList.toggle('is-selected', active);
            if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
        });
        const current = copy[kind];
        if (title) title.textContent = current.title;
        if (description) description.textContent = current.description;
        if (eyebrow) eyebrow.textContent = current.eyebrow;
        submitLabel.textContent = kind === 'creator' ? 'Submit my profile' : 'Create account';
        page.querySelector('[data-enquiry-email-label]').textContent = kind === 'creator' ? 'Email address' : 'Business email';
        form.querySelector('[name="email"]').placeholder = kind === 'creator' ? 'you@example.com' : current.email;
        if (kind !== 'creator') {
            page.querySelector('[data-enquiry-website-label]').textContent = current.website;
            form.querySelector('[name="website"]').placeholder = current.websitePlaceholder;
            form.querySelector('[name="email"]').placeholder = current.email;
            page.querySelector('[data-enquiry-requirements-label]').textContent = current.requirements;
            form.querySelector('[name="requirements"]').placeholder = current.requirementsPlaceholder;
        }
        validateChannels();
        validateCountries();
        validatePasswordConfirmation();
        if (updateUrl) {
            const url = new URL(location.href);
            url.searchParams.set('type', kind);
            if (kind === 'creator') url.searchParams.set('creator_type', creatorType);
            else url.searchParams.delete('creator_type');
            history.replaceState(history.state, '', url);
            page.querySelector('[data-enquiry-errors]')?.setAttribute('hidden', '');
        }
        motion?.cancel();
        if (animate && !reduced.matches && typeof form.animate === 'function') {
            motion = form.animate([
                { opacity: .5, translate: '0 8px' },
                { opacity: 1, translate: '0 0' },
            ], { duration: 340, easing: 'cubic-bezier(.2,.7,.2,1)' });
        }
    }

    function canEnhanceClick(event) {
        return event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;
    }
    categories.forEach(link => link.addEventListener('click', event => {
        if (!canEnhanceClick(event)) return;
        event.preventDefault();
        kind = link.dataset.enquiryCategory;
        refresh({ animate: true, updateUrl: true });
    }, { signal: events.signal }));
    types.forEach(link => link.addEventListener('click', event => {
        if (!canEnhanceClick(event)) return;
        event.preventDefault();
        creatorType = link.dataset.enquiryType;
        refresh({ animate: true, updateUrl: true });
    }, { signal: events.signal }));
    [kick, twitch].forEach(input => input?.addEventListener('input', validateChannels, { signal: events.signal }));
    [firstGeo, secondGeo].forEach(select => select?.addEventListener('change', validateCountries, { signal: events.signal }));
    [password, confirmation].forEach(input => input?.addEventListener('input', validatePasswordConfirmation, { signal: events.signal }));
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        submit.disabled = true;
        submitLabel.textContent = 'Sending…';
    }, { signal: events.signal });
    refresh();
    return () => {
        events.abort();
        motion?.cancel();
        submitting = false;
        submit.disabled = false;
    };
}
