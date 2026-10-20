/* ==========================================================================
   service-worker.js — Green Valley SRMS offline layer
   --------------------------------------------------------------------------
   This file turns the site into an installable Progressive Web App that keeps
   working when the network drops.

   SECURITY (per the project rules):
     • The service worker caches STATIC FILES only (CSS, JS, icons, the offline
       page). It never connects to PostgreSQL and never stores credentials.
     • PHP remains the only thing that touches the database. The worker sits in
       front of the browser's network requests — nothing more.
     • Pages you open are cached at runtime so you can re-open recently viewed
       screens while offline. That page cache is WIPED on sign-out (js/pwa.js
       sends a CLEAR_PAGES message) so student records don't linger on a shared
       school device.

   Bump VERSION whenever you change cached files, to retire the old caches.
   ========================================================================== */

const VERSION     = 'gv-srms-v1';
const SHELL_CACHE = `shell-${VERSION}`;
const PAGE_CACHE  = `pages-${VERSION}`;

/* Static app shell — contains no student data, safe to precache. */
const SHELL_ASSETS = [
  './offline.html',
  './css/style.css',
  './js/script.js',
  './js/pwa.js',
  './manifest.webmanifest',
  './icons/icon-192.png',
  './icons/icon-512.png',
  './icons/icon-maskable-512.png',
  './icons/apple-touch-icon.png',
  './icons/favicon-32.png',
];

/* ---- Install: precache the shell, then take over as soon as possible. ---- */
self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(SHELL_CACHE);
    await cache.addAll(SHELL_ASSETS);
    await self.skipWaiting();
  })());
});

/* ---- Activate: delete caches from older versions. ---- */
self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(
      keys.filter((k) => k !== SHELL_CACHE && k !== PAGE_CACHE)
          .map((k) => caches.delete(k))
    );
    await self.clients.claim();
  })());
});

/* ---- Message channel: let the page clear cached pages on sign-out. ---- */
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'CLEAR_PAGES') {
    event.waitUntil(caches.delete(PAGE_CACHE));
  }
});

/* ---- Fetch strategy ---- */
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Never intercept non-GET (logins, form POSTs) — straight to the network.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Only handle our own origin; let fonts/CDNs go to the network untouched.
  if (url.origin !== self.location.origin) return;

  // Opening a page (navigation): network-first, then cached page, then offline.
  if (req.mode === 'navigate') {
    event.respondWith(networkFirst(req, url));
    return;
  }

  // Everything else (css/js/icons): cache-first, fill cache in the background.
  event.respondWith(cacheFirst(req));
});

/* Network-first for navigations. Caches a fresh copy of normal pages so they
   can be re-opened offline; skips redirects (e.g. logout) and non-OK responses. */
async function networkFirst(req, url) {
  try {
    const res = await fetch(req);
    const cacheable = res.ok && !res.redirected && !url.pathname.endsWith('logout.php');
    if (cacheable) {
      const copy = res.clone();
      const cache = await caches.open(PAGE_CACHE);
      cache.put(req, copy);
    }
    return res;
  } catch (err) {
    const cached = await caches.match(req);
    if (cached) return cached;
    const offline = await caches.match('./offline.html');
    return offline || new Response('You are offline.', {
      status: 503,
      headers: { 'Content-Type': 'text/plain' },
    });
  }
}

/* Cache-first for static assets; adds newly fetched same-origin files to the
   shell cache so they are available next time offline. */
async function cacheFirst(req) {
  const cached = await caches.match(req);
  if (cached) return cached;
  try {
    const res = await fetch(req);
    if (res && res.ok && res.type === 'basic') {
      const copy = res.clone();
      const cache = await caches.open(SHELL_CACHE);
      cache.put(req, copy);
    }
    return res;
  } catch (err) {
    return cached || Response.error();
  }
}
