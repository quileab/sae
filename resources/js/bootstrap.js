import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Lazy loading with IntersectionObserver
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                }
                if (img.dataset.srcset) {
                    img.srcset = img.dataset.srcset;
                }
                img.removeAttribute('data-src');
                img.removeAttribute('data-srcset');
                img.classList.add('loaded');
                imageObserver.unobserve(img);
            }
        });
    }, {
        rootMargin: '200px 0px',
        threshold: 0.01
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('img[data-src], img[data-srcset]').forEach((img) => {
            imageObserver.observe(img);
        });
    });
}

// Content-visibility auto polyfill for long lists
if (!CSS.supports('content-visibility', 'auto')) {
    document.querySelectorAll('.content-list, .card-grid, .table-container').forEach((el) => {
        el.style.contain = 'layout style paint';
    });
}