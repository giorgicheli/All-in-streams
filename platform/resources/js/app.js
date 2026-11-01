import './bootstrap';
import { initializeMotion } from './motion';
import { initializeSmoothScroll } from './smooth-scroll';

let cleanup = () => {};

function initializePage() {
    cleanup();
    const controller = new AbortController();
    const disposeMotion = initializeMotion();
    const disposeScroll = initializeSmoothScroll();
    const header = document.querySelector('.site-header');
    if (header) {
        const updateHeader = () => header.classList.toggle('scrolled', scrollY > 20);
        updateHeader();
        addEventListener('scroll', updateHeader, { passive: true, signal: controller.signal });
    }
    document.querySelectorAll('[data-geo]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-geo]').forEach(tab => {tab.classList.remove('selected');tab.setAttribute('aria-selected','false')});
        button.classList.add('selected');button.setAttribute('aria-selected','true');
        const label = document.querySelector('[data-geo-label]');
        if (label) label.textContent = button.dataset.geo;
    }, { signal: controller.signal }));

    cleanup = () => {
        controller.abort();
        disposeMotion();
        disposeScroll();
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage, { once: true });
} else {
    initializePage();
}

document.addEventListener('livewire:navigating', () => cleanup());
document.addEventListener('livewire:navigated', initializePage);
window.addEventListener('pagehide', () => cleanup());
window.addEventListener('pageshow', (event) => {
    if (event.persisted) initializePage();
});
