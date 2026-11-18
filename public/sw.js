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

/**
 * Push notifications (see `App\Notifications\Concerns\SendsWebPush`): the payload holds the options of the notification,
 * with the URL to open on click in `data.url`.
 */
self.addEventListener('push', (event) => {
    let payload = {};

    // A payload that is not JSON (e.g. a test from the developer tools of the browser) is shown as the body.
    try {
        payload = event.data?.json() ?? {};
    } catch {
        payload = { body: event.data?.text() };
    }

    const { title = 'OpenCongregationHelper', ...options } = payload;

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data?.url ?? '/app', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const appWindow = windows.find((client) => new URL(client.url).origin === self.location.origin);

            if (appWindow) {
                // `navigate()` fails on pages not controlled by this service worker: a new window is opened instead.
                return appWindow.focus()
                    .then((client) => client.navigate(url))
                    .catch(() => self.clients.openWindow(url));
            }

            return self.clients.openWindow(url);
        }),
    );
});
