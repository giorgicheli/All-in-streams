import './bootstrap';
import { initializeMotion } from './motion';
import { initializeSmoothScroll } from './smooth-scroll';
import { initializeGeographyCarousels } from './geography';
import { initializeMobileNavigation } from './navigation';
import { initializePublicResults, initializeNetworkCounters } from './public-results';
import { initializePartnerEnquiry } from './partner-enquiry';
import { initializeHeroHeadlines } from './hero-headlines';
import { initializeAudiencePickers } from './audience-picker';
import { initializePublicRefinements } from './public-refinement';
import { initializeCreatorNetwork } from './creator-network';

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
    const disposeEnquiry = initializePartnerEnquiry();
    const disposeHeadlines = initializeHeroHeadlines();
    const disposeAudience = initializeAudiencePickers();
    const disposeRefinements = initializePublicRefinements();
    const disposeCreatorNetwork = initializeCreatorNetwork();
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
        disposeEnquiry();
        disposeHeadlines();
        disposeAudience();
        disposeRefinements();
        disposeCreatorNetwork();
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
