const instances = new WeakMap();
const shortlistStorageKey = 'ais.creator-shortlist.v1';
const publicIdPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function initializeCreatorNetwork(root = document) {
    instances.get(root)?.();
    const galleries = [...root.querySelectorAll('[data-creator-network]')];
    const disposers = galleries.map(gallery => {
        const controller = new AbortController();
        const options = { signal: controller.signal };
        const tabs = [...gallery.querySelectorAll('[data-creator-region]')];
        const panels = [...gallery.querySelectorAll('[data-creator-panel]')];
        const dialog = gallery.querySelector('[data-shortlist-dialog]');
        const shortlistList = gallery.querySelector('[data-shortlist-list]');
        const status = gallery.querySelector('[data-creator-network-status]');
        const profiles = new Map();
        const animations = new Set();
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let activeRegion = tabs[0]?.dataset.creatorRegion;
        let selected = new Set();

        gallery.querySelectorAll('[data-public-profile]').forEach(card => {
            const id = card.dataset.publicProfile;
            if (publicIdPattern.test(id)) profiles.set(id, {
                card,
                caption: card.dataset.profileCaption,
                country: card.dataset.profileMarket,
                type: card.dataset.profileType,
            });
        });

        function readShortlist() {
            try {
                const stored = JSON.parse(localStorage.getItem(shortlistStorageKey) || '[]');
                selected = new Set(Array.isArray(stored) ? stored.filter(id => typeof id === 'string' && publicIdPattern.test(id) && profiles.has(id)).slice(0, 24) : []);
            } catch {
                selected = new Set();
            }
        }

        function requestUrl(region = activeRegion) {
            const url = new URL(gallery.dataset.requestUrl, window.location.origin);
            url.searchParams.set('type', 'brand');
            url.searchParams.set('region', region);
            url.searchParams.delete('profiles[]');
            selected.forEach(id => url.searchParams.append('profiles[]', id));
            return url.href;
        }

        function renderShortlist() {
            profiles.forEach((profile, id) => {
                const button = profile.card.querySelector('[data-add-shortlist]');
                const isSelected = selected.has(id);
                button.setAttribute('aria-pressed', String(isSelected));
                button.querySelector('span').textContent = isSelected ? 'Added to shortlist' : 'Add to shortlist';
            });
            gallery.querySelectorAll('[data-shortlist-count]').forEach(counter => counter.textContent = `(${selected.size})`);
            gallery.querySelectorAll('[data-request-profiles]').forEach(link => link.href = requestUrl(link.closest('[data-creator-panel]').dataset.creatorPanel));
            gallery.querySelector('[data-shortlist-request]').href = requestUrl();
            gallery.querySelector('[data-shortlist-empty]').hidden = selected.size > 0;
            shortlistList.replaceChildren();
            selected.forEach(id => {
                const profile = profiles.get(id);
                const item = document.createElement('li');
                const copy = document.createElement('div');
                const title = document.createElement('strong');
                const detail = document.createElement('small');
                const remove = document.createElement('button');
                title.textContent = profile.caption;
                detail.textContent = `${profile.country} / ${profile.type}`;
                remove.type = 'button';
                remove.textContent = 'Remove';
                remove.dataset.removeShortlist = id;
                remove.setAttribute('aria-label', `Remove ${profile.caption}, ${profile.country}`);
                copy.append(title, detail);
                item.append(copy, remove);
                shortlistList.append(item);
            });
        }

        function saveShortlist() {
            try {
                localStorage.setItem(shortlistStorageKey, JSON.stringify([...selected]));
            } catch {
                // The current selection remains usable when browser storage is restricted.
            }
            renderShortlist();
            status.textContent = `${selected.size} ${selected.size === 1 ? 'creator' : 'creators'} in your shortlist.`;
        }

        function selectRegion(tab, moveFocus = false) {
            activeRegion = tab.dataset.creatorRegion;
            tabs.forEach(item => {
                const active = item === tab;
                item.classList.toggle('is-selected', active);
                item.setAttribute('aria-selected', String(active));
                item.tabIndex = active ? 0 : -1;
            });
            animations.forEach(animation => animation.cancel());
            animations.clear();
            panels.forEach(panel => {
                panel.hidden = panel.dataset.creatorPanel !== activeRegion;
                if (panel.hidden || reducedMotion.matches) return;
                panel.querySelectorAll('.creator-public-card').forEach((card, index) => {
                    if (typeof card.animate !== 'function') return;
                    const animation = card.animate([{ opacity: 0, transform: 'translateY(9px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 480, delay: index * 65, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'backwards' });
                    animations.add(animation);
                    animation.finished.finally(() => animations.delete(animation)).catch(() => {});
                });
            });
            gallery.querySelector('[data-shortlist-request]').href = requestUrl();
            if (moveFocus) {
                tab.focus({ preventScroll: true });
                tab.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reducedMotion.matches ? 'instant' : 'smooth' });
            }
        }

        gallery.addEventListener('click', event => {
            const tab = event.target.closest('[data-creator-region]');
            if (tab) selectRegion(tab);
            const addButton = event.target.closest('[data-add-shortlist]');
            const removeButton = event.target.closest('[data-remove-shortlist]');
            if (addButton) {
                const id = addButton.closest('[data-public-profile]').dataset.publicProfile;
                if (selected.has(id)) {
                    selected.delete(id);
                } else if (profiles.has(id)) {
                    selected.add(id);
                }
                saveShortlist();
            }
            if (removeButton) {
                const removeIndex = [...shortlistList.querySelectorAll('[data-remove-shortlist]')].indexOf(removeButton);
                selected.delete(removeButton.dataset.removeShortlist);
                saveShortlist();
                const remaining = [...shortlistList.querySelectorAll('[data-remove-shortlist]')];
                const nextControl = remaining[Math.min(removeIndex, remaining.length - 1)] ?? gallery.querySelector('[data-shortlist-close]');
                nextControl.focus({ preventScroll: true });
            }
            if (event.target.closest('[data-shortlist-open]')) dialog.showModal();
            if (event.target.closest('[data-shortlist-close]')) dialog.close();
        }, options);

        gallery.querySelector('[role="tablist"]').addEventListener('keydown', event => {
            const index = tabs.indexOf(event.target);
            if (index < 0) return;
            let nextIndex;
            if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = tabs.length - 1;
            if (nextIndex === undefined) return;
            event.preventDefault();
            selectRegion(tabs[nextIndex], true);
        }, options);

        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
        }, options);
        window.addEventListener('storage', event => {
            if (event.key !== shortlistStorageKey) return;
            readShortlist();
            renderShortlist();
        }, options);
        readShortlist();
        renderShortlist();

        return () => {
            controller.abort();
            animations.forEach(animation => animation.cancel());
            if (dialog.open) dialog.close();
        };
    });
    const dispose = () => disposers.forEach(cleanup => cleanup());
    instances.set(root, dispose);
    return dispose;
}
