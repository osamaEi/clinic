/*
 * Service worker for the clinic PWA.
 *  - app shell: precached, served network-first so deploys show up when online
 *  - patient files (/files/*) and the prescription template (/rx-template/*): cache-first, keyed without the signature query,
 *    so anything viewed once (or warmed after a pull) opens offline
 *  - Google Fonts: stale-while-revalidate
 *  - /api/*: never cached; the app keeps its own data in IndexedDB
 * Bump VERSION on every deploy that changes shell files.
 */
const VERSION = 'v7';
const SHELL_CACHE = 'shell-' + VERSION;
const FILES_CACHE = 'files-v1';
const FONTS_CACHE = 'fonts-v1';
const SHELL = [
  '/app/',
  '/app/app.css',
  '/app/sync.js',
  '/app/manifest.webmanifest',
  '/app/icons/icon.svg',
  '/app/icons/icon-192.png',
  '/app/icons/icon-512.png',
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(SHELL_CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  const keep = [SHELL_CACHE, FILES_CACHE, FONTS_CACHE];
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => !keep.includes(k)).map(k => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

async function networkFirst(request, fallbackUrl) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const res = await fetch(request);
    if (res.ok) cache.put(fallbackUrl || request, res.clone());
    return res;
  } catch (err) {
    const hit = await cache.match(fallbackUrl || request, { ignoreSearch: true });
    if (hit) return hit;
    throw err;
  }
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(request, { ignoreSearch: true });
  if (hit) return hit;
  const res = await fetch(request);
  if (res.ok) cache.put(request, res.clone());
  return res;
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(request);
  const refresh = fetch(request).then(res => {
    if (res.ok || res.type === 'opaque') cache.put(request, res.clone());
    return res;
  }).catch(() => hit);
  return hit || refresh;
}

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);

  if (url.origin === self.location.origin) {
    if (url.pathname.startsWith('/api/')) return;
    if (url.pathname.startsWith('/files/') || url.pathname.startsWith('/rx-template/')) {
      event.respondWith(cacheFirst(request, FILES_CACHE));
      return;
    }
    if (request.mode === 'navigate' && url.pathname.startsWith('/app')) {
      event.respondWith(networkFirst(request, '/app/'));
      return;
    }
    if (url.pathname.startsWith('/app/')) {
      event.respondWith(networkFirst(request));
    }
    return;
  }

  if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
    event.respondWith(staleWhileRevalidate(request, FONTS_CACHE));
  }
});
