import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.site-header');
    if (header) addEventListener('scroll', () => header.classList.toggle('scrolled', scrollY > 20), {passive:true});
    document.querySelectorAll('[data-geo]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-geo]').forEach(tab => {tab.classList.remove('selected');tab.setAttribute('aria-selected','false')});
        button.classList.add('selected');button.setAttribute('aria-selected','true');
        document.querySelector('[data-geo-label]').textContent = button.dataset.geo;
    }));
});
