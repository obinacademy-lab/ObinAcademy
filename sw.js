/*
 * Obin Academy service worker.
 *
 * Deliberately small and conservative — this site has logins, payments and paid course content,
 * so the worker must never be able to serve a stale or someone else's page:
 *
 *  - Pages (every PHP page, API response, lesson and video stream) always go to the network and
 *    are never stored. If the network is down for a page navigation, the visitor sees offline.html.
 *  - Only same-origin GET requests under /assets/ and /uploads/ are ever touched. Everything else
 *    (POSTs, payments, /api/, stream.php, fonts, analytics) passes straight through.
 *  - Static files are cached for speed: CSS/JS network-first (a deploy shows up immediately; the
 *    cached copy is only a fallback), images cache-first.
 *
 * Bump CACHE_VERSION to drop every cached file on the next visit.
 */
const CACHE_VERSION = 'v1';
const STATIC_CACHE = 'obin-static-' + CACHE_VERSION;
const OFFLINE_URL = new URL('offline.html', self.location).href;
const ICON_URL = new URL('assets/icons/icon-192.png', self.location).href;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll([OFFLINE_URL, ICON_URL]))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k.startsWith('obin-') && k !== STATIC_CACHE).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

function isStaticAsset(url) {
  return url.pathname.indexOf('/assets/') !== -1 || url.pathname.indexOf('/uploads/') !== -1;
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;       // fonts, analytics, etc: not ours
  if (url.pathname.indexOf('/api/') !== -1) return;       // payment polling etc: always live
  if (req.headers.has('range')) return;                   // media seeking is never cached

  // Page navigations: network only, offline page as the fallback.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  if (!isStaticAsset(url)) return;

  if (/\.(css|js)$/.test(url.pathname)) {
    // network-first: always the newest deploy, the cached copy only if offline
    event.respondWith(
      fetch(req).then((res) => {
        if (res.ok) { const copy = res.clone(); caches.open(STATIC_CACHE).then((c) => c.put(req, copy)); }
        return res;
      }).catch(() => caches.match(req))
    );
    return;
  }

  // images / icons: cache-first
  event.respondWith(
    caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(STATIC_CACHE).then((c) => c.put(req, copy)); }
      return res;
    }))
  );
});