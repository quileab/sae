import './bootstrap';
import './evaluation-table-design';
import './assist-grading';

// Registrar Service Worker para soporte PWA offline
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js');
    });
}
