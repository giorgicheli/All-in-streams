const instances = new WeakMap();

export function initializeCreatorReels(root = document) {
    instances.get(root)?.();
    const cleanups = [...root.querySelectorAll('[data-creator-reels]')].map(mountCreatorReels);
    const cleanup = () => cleanups.forEach(dispose => dispose());
    instances.set(root, cleanup);
    return cleanup;
}

function mountCreatorReels(section) {
    const controller = new AbortController();
    const options = { signal: controller.signal };
    const videos = [...section.querySelectorAll('[data-reel-video]')];
    const wall = section.querySelector('[data-reel-wall]');
    const control = section.querySelector('[data-reel-control]');
    if (!wall || !control) return () => {};
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    const onscreen = new Set();
    let visible = false;
    let menuOpen = false;
    let paused = false;
    let optedIn = false;
    let disposed = false;
    let suspended = false;
    let focusFrame = 0;
    let pointerFocusedLink = null;
    let pointerFocusTime = 0;
    const focusedTrackStyles = new Map();

    function unload(video) {
        video.pause();
        if (video.hasAttribute('src')) {
            video.removeAttribute('src');
            video.load();
        }
    }

    function preferenceLimited() { return reduced.matches || Boolean(connection?.saveData); }
    function playing() { return !paused && (!preferenceLimited() || optedIn); }

    function sync() {
        if (disposed) return;
        const allowed = visible && !menuOpen && !document.hidden && !suspended && playing();
        section.dataset.reelMoving = String(allowed && !preferenceLimited());
        control.setAttribute('aria-pressed', String(playing()));
        control.setAttribute('aria-label', playing() ? 'Pause video previews' : 'Play video previews');
        control.querySelector('[data-reel-control-label]').textContent = playing() ? 'Pause previews' : 'Play previews';
        control.querySelector('[data-reel-pause]').toggleAttribute('hidden', !playing());
        control.querySelector('[data-reel-play]').toggleAttribute('hidden', playing());
        videos.forEach(video => {
            if (!allowed || !onscreen.has(video)) { unload(video); return; }
            if (!video.hasAttribute('src')) {
                video.src = video.dataset.src;
                video.muted = video.defaultMuted = true;
            }
            if (video.paused) video.play().catch(() => {});
        });
    }

    const sectionObserver = new IntersectionObserver(entries => {
        visible = entries[0].isIntersecting;
        sync();
    }, { threshold: .08 });
    const videoObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) onscreen.add(entry.target);
            else onscreen.delete(entry.target);
        });
        sync();
    }, { root: wall, threshold: .1 });
    sectionObserver.observe(wall);
    videos.forEach(video => videoObserver.observe(video));
    control.addEventListener('click', () => {
        if (playing()) paused = true;
        else { paused = false; optedIn = true; }
        sync();
    }, options);
    // Native Tab focus can scroll an overflow-hidden row while its CSS loop
    // leaves the canonical link far offscreen. Seek that same loop to reveal
    // the focused card; resuming from the new phase avoids a restore jump.
    function revealFocusedLink(link) {
        const row = link.closest('[data-reel-row]');
        const track = row?.querySelector('.creator-reel-track');
        const card = link.closest('.creator-reel-card');
        if (!row || !track || !card) return;
        row.scrollLeft = 0;
        if (row.dataset.reelRow === 'still') {
            if (!focusedTrackStyles.has(track)) focusedTrackStyles.set(track, track.style.justifyContent);
            track.style.justifyContent = 'flex-start';
        }
        const cardRect = card.getBoundingClientRect();
        const trackRect = track.getBoundingClientRect();
        const staticLeft = cardRect.left - trackRect.left;
        const offset = Math.max(0, staticLeft - (row.clientWidth - cardRect.width) / 2);
        const animation = track.getAnimations?.().find(item => item.animationName === 'creator-reel-glide');
        const duration = Number(animation?.effect?.getTiming().duration);
        if (animation && duration > 0) {
            const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            const span = (track.offsetWidth + gap) / 2;
            const progress = Math.min(1, offset / Math.max(1, span));
            const phase = row.dataset.reelRow === 'reverse' ? 1 - progress : progress;
            animation.currentTime = Math.min(duration - .05, Math.max(0, phase * duration));
            row.scrollLeft = 0;
        } else {
            row.scrollLeft = offset;
        }
    }
    wall.addEventListener('pointerdown', event => {
        pointerFocusedLink = event.target.closest?.('[data-reel-link]') ?? null;
        pointerFocusTime = performance.now();
    }, { ...options, capture: true });
    document.addEventListener('keydown', () => { pointerFocusedLink = null; }, options);
    wall.addEventListener('focusin', event => {
        const link = event.target.closest?.('[data-reel-link]');
        if (!link || link.tabIndex < 0 || (link === pointerFocusedLink && performance.now() - pointerFocusTime < 1000)) return;
        cancelAnimationFrame(focusFrame);
        revealFocusedLink(link);
        focusFrame = requestAnimationFrame(() => {
            focusFrame = 0;
            if (!disposed && document.activeElement === link) revealFocusedLink(link);
        });
    }, options);
    wall.addEventListener('focusout', event => {
        const row = event.target.closest?.('[data-reel-row]');
        if (!row || row.contains(event.relatedTarget)) return;
        cancelAnimationFrame(focusFrame);
        focusFrame = 0;
        row.scrollLeft = 0;
        const track = row.querySelector('.creator-reel-track');
        if (focusedTrackStyles.has(track)) {
            const value = focusedTrackStyles.get(track);
            if (value) track.style.justifyContent = value;
            else track.style.removeProperty('justify-content');
            focusedTrackStyles.delete(track);
        }
    }, options);
    reduced.addEventListener('change', sync, options);
    connection?.addEventListener('change', sync, options);
    document.addEventListener('visibilitychange', sync, options);
    document.addEventListener('ais:navigation-state', event => { menuOpen = Boolean(event.detail?.open); sync(); }, options);
    window.addEventListener('pagehide', event => {
        suspended = true;
        sync();
        if (!event.persisted) cleanup();
    }, options);
    window.addEventListener('pageshow', () => { suspended = false; sync(); }, options);
    section.dataset.reelReady = 'true';
    control.hidden = false;
    sync();

    function cleanup() {
        if (disposed) return;
        disposed = true;
        controller.abort();
        cancelAnimationFrame(focusFrame);
        focusedTrackStyles.forEach((value, track) => {
            if (value) track.style.justifyContent = value;
            else track.style.removeProperty('justify-content');
        });
        focusedTrackStyles.clear();
        sectionObserver.disconnect();
        videoObserver.disconnect();
        videos.forEach(unload);
        control.hidden = true;
        delete section.dataset.reelReady;
        delete section.dataset.reelMoving;
    }
    return cleanup;
}
