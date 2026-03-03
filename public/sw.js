const CACHE_NAME = 'sae-attendance-v2';
const CACHED_URLS = [
    '/favicon.ico',
    '/images/icon-192x192.png',
    '/images/icon-512x512.png',
];

// Instalación: cachear recursos esenciales
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(CACHED_URLS))
    );
    self.skipWaiting();
});

// Activación: limpiar cachés viejos
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: network-first para navegación crítica, cache-first para el resto y assets
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // EXCLUSIÓN CRÍTICA: Ignorar Vite, HMR y dominios externos (MercadoPago, etc)
    if (url.port === '5173' || 
        url.hostname !== self.location.hostname || 
        url.pathname.includes('@vite') || 
        url.pathname.includes('hot')) {
        return;
    }

    // Las llamadas a la API, Livewire y PWA Sync siempre van a red
    if (url.pathname.startsWith('/api/') || 
        url.pathname.startsWith('/livewire/') || 
        url.pathname.startsWith('/pwa-attendance/')) {
        return;
    }

    // Estrategia Network-First para la página de asistencia (para evitar CSRF obsoletos)
    if (url.pathname === '/attendance') {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    if (response && response.status === 200 && response.type === 'basic') {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // Cache-First para archivos de Build (Vite), imágenes y fuentes
    const isAsset = url.pathname.startsWith('/build/') || 
                    url.pathname.startsWith('/images/') || 
                    url.pathname.match(/\.(js|css|png|jpg|jpeg|gif|svg|woff|woff2|ico)$/);

    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) return cached;
            return fetch(event.request).then((response) => {
                // SOLO CACHEAR SI ES GET y la respuesta es exitosa
                if (event.request.method === 'GET' && response && response.status === 200) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            });
        })
    );
});

// Background Sync: sincronizar asistencias pendientes cuando vuelve la red
self.addEventListener('sync', (event) => {
    if (event.tag === 'attendance-sync') {
        event.waitUntil(syncAttendances());
    }
});

async function syncAttendances() {
    const db = await openDB();
    const tx = db.transaction('pending_attendance', 'readonly');
    const records = await promisify(tx.objectStore('pending_attendance').getAll());

    if (records.length === 0) return;

    // Intentar sincronizar vía PWA Route
    try {
        const response = await fetch('/pwa-attendance/sync', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'Accept': 'application/json',
                // El token de CSRF se omite aquí (debe estar excluido en Laravel)
            },
            credentials: 'same-origin',
            body: JSON.stringify({ records }),
        });

        if (response.ok && response.headers.get('content-type')?.includes('application/json')) {
            const data = await response.json();
            if (data.synced) {
                const tx2 = db.transaction('pending_attendance', 'readwrite');
                tx2.objectStore('pending_attendance').clear();
            }
        }
    } catch (e) {
        console.error('Background sync failed', e);
    }
}

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('sae_attendance', 3);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            // Si ya existe, lo borramos para aplicar el nuevo esquema de clave compuesta
            if (db.objectStoreNames.contains('pending_attendance')) {
                db.deleteObjectStore('pending_attendance');
            }
            db.createObjectStore('pending_attendance', { keyPath: ['career_id', 'user_id', 'date'] });
            
            if (!db.objectStoreNames.contains('students_cache')) {
                db.createObjectStore('students_cache', { keyPath: 'career_id' });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e);
    });
}

function promisify(req) {
    return new Promise((resolve, reject) => {
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e);
    });
}
