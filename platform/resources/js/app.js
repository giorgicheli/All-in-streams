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
import { initializeRopeStatistics } from './rope-statistics';
import { initializePointerEffect } from './pointer-effect';
import { initializeCreatorReels } from './creator-reels';
import { initializeCreatorOrbit } from './creator-orbit';
import { initializeSiteIntro } from './site-intro';

let cleanup = () => {};
let disposeIntro = () => {};
let introReady;
let pageGeneration = 0;

function initializeAfterIntro() {
    const generation = ++pageGeneration;
    if (!introReady) {
        try {
            disposeIntro = initializeSiteIntro();
            clearTimeout(window.__aisIntroFailsafe);
            introReady = Promise.resolve(disposeIntro.readyPromise);
        } catch {
            document.documentElement.removeAttribute('data-site-intro-pending');
            document.dispatchEvent(new Event('ais:site-intro-abort'));
            const intro = document.querySelector('[data-site-intro]');
            if (intro?.open) intro.close();
            intro?.remove();
            introReady = Promise.resolve();
        }
    }
    introReady.then(() => { if (generation === pageGeneration) initializePage(); });
}

function disposePage() {
    pageGeneration++;
    disposeIntro();
    cleanup();
}

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
    const disposeRopeStatistics = initializeRopeStatistics();
    const disposePointerEffect = initializePointerEffect();
    const disposeCreatorReels = initializeCreatorReels();
    const disposeCreatorOrbit = initializeCreatorOrbit();
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
        disposeRopeStatistics();
        disposePointerEffect();
        disposeCreatorReels();
        disposeCreatorOrbit();
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAfterIntro, { once: true });
} else {
    initializeAfterIntro();
}

document.addEventListener('livewire:navigating', disposePage);
document.addEventListener('livewire:navigated', initializeAfterIntro);
window.addEventListener('pagehide', disposePage);
window.addEventListener('pageshow', (event) => {
    if (event.persisted) initializeAfterIntro();
});
