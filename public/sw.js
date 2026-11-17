/**
 * Service worker of the Progressive Web App.
 *
 * Pages are always loaded from the network, as they hold the congregation's data: when there is no connection, the
 * cached offline page is shown instead. Only the static assets built by Vite (whose names change with their content)
 * and the app icons are cached, so the app starts faster.
 *
 * The cached files are replaced when the app version or the cached files change: the page registers this script with
 * their version in its URL (`sw.js?version=...`, see `App\Support\ServiceWorker`), and a new URL installs a new
 * service worker, which deletes the caches of the old one.
 */
const VERSION = new URL(self.location.href).searchParams.get('version') ?? 'unversioned';
const CACHE = `opencongregationhelper-${VERSION}`;
const OFFLINE_URL = '/offline';
const PRECACHED_URLS = [
    OFFLINE_URL,
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(PRECACHED_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));

        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }
});
