import './bootstrap';
import { initializeMotion } from './motion';
import { initializeSmoothScroll } from './smooth-scroll';
import { initializeGeographyCarousels } from './geography';
import { initializeMobileNavigation } from './navigation';
import { initializePublicResults, initializeNetworkCounters } from './public-results';

let cleanup = () => {};

function initializePage() {
    cleanup();
    const controller = new AbortController();
    const disposeMotion = initializeMotion();
    const disposeScroll = initializeSmoothScroll();
    const disposeGeography = initializeGeographyCarousels();
    const disposeNavigation = initializeMobileNavigation();
    const disposeResults = initializePublicResults();
    const disposeCounters = initializeNetworkCounters();
    const header = document.querySelector('.site-header');
    if (header) {
        const updateHeader = () => {
            if (document.documentElement.classList.contains('site-menu-open')) return;
            header.classList.toggle('scrolled', scrollY > 20);
        };
        updateHeader();
        addEventListener('scroll', updateHeader, { passive: true, signal: controller.signal });
    }

    cleanup = () => {
        controller.abort();
        disposeMotion();
        disposeScroll();
        disposeGeography();
        disposeNavigation();
        disposeResults();
        disposeCounters();
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
