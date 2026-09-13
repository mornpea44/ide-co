/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SERVICE WORKER (Phase 6 · Task 6.7)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  A Service Worker is a background script the browser runs separately from
 *  your web page. It can intercept network requests and decide:
 *    "Should I fetch this from the internet, or serve a saved copy?"
 *
 *  This makes the IDE:
 *    • INSTALLABLE — users can "Add to Home Screen" on their phone
 *    • OFFLINE-CAPABLE — vendor libraries load even without internet
 *    • FASTER — cached files load instantly instead of over the network
 *
 *  CACHING STRATEGY (plain English):
 *    • Vendor assets (?vendor=...)  → Cache-first: use saved copy instantly,
 *      quietly refresh in the background. These files (CodeMirror, js-beautify)
 *      almost never change, so a cached copy is always correct.
 *
 *    • App bundles (?bundle=...)    → Network-first: try to fetch the live
 *      version first (so you always get fresh code while developing). If the
 *      network fails (offline), fall back to the last saved copy.
 *
 *    • Static assets (?asset=...)   → Cache-first: same as vendor. CSS, JS
 *      modules, icons, and the manifest are saved and served instantly.
 *
 *    • API calls (?api=...)         → Network-only: NEVER cached. File content,
 *      directory trees, and search results must always be fresh from the server.
 *
 *    • Workspace files (?workspace=...) → Network-only: same reason.
 *
 *  CACHE VERSIONING:
 *    The VERSION constant below acts like a "generation number". When you
 *    deploy a new version of the IDE, bump this string. The 'activate' handler
 *    then deletes all caches from previous generations, so users never get
 *    stale files mixing old and new code.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ── Bump this string whenever you deploy a new version. ──
   It ensures old caches are cleaned up and replaced.       */
var VERSION = 'quirky-ide-v2.0.0';

/* Two separate "storage boxes" (caches):
   STATIC  = vendor libs + IDE assets (rarely change)
   APP     = bundles + HTML shell (changes with each deploy)  */
var CACHE_STATIC = VERSION + '-static';
var CACHE_APP = VERSION + '-app';

/* ═══════════════════════════════════════════════════════════════
INSTALL — runs once when the SW is first registered.
We pre-cache the absolute essentials so the app shell can
load even if the network drops immediately after install.
═══════════════════════════════════════════════════════════════ */
self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_STATIC)
      .then(function (cache) {
        /* Pre-cache the PWA manifest and icon so the
           "Add to Home Screen" prompt works offline. */
        return cache.addAll([
          'index.php?asset=manifest.json',
          'index.php?asset=icons/icon.svg'
        ]);
      })
      .then(function () {
        /* skipWaiting() = "Don't wait for old tabs to close.
           Activate me immediately." Without this, the new SW
           would sit idle until every old tab is closed. */
        return self.skipWaiting();
      })
  );
});

/* ═══════════════════════════════════════════════════════════════
ACTIVATE — runs when this SW takes control (after install).
Its main job: delete caches from OLD versions so users don't
get a mix of old and new files.
═══════════════════════════════════════════════════════════════ */
self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys()
      .then(function (cacheNames) {
        return Promise.all(
          cacheNames
            .filter(function (name) {
              /* Keep only caches that belong to THIS version.
                 Delete anything from a previous generation. */
              return name.indexOf('quirky-ide-') === 0 &&
                name !== CACHE_STATIC &&
                name !== CACHE_APP;
            })
            .map(function (name) {
              return caches.delete(name);
            })
        );
      })
      .then(function () {
        /* clients.claim() = "Take control of all open tabs RIGHT NOW."
           Without this, the SW wouldn't control tabs that were already
           open when it activated. */
        return self.clients.claim();
      })
  );
});

/* ═══════════════════════════════════════════════════════════════
FETCH — the heart of the Service Worker.
Every network request from the IDE passes through here.
We inspect the URL and decide which caching strategy to use.
═══════════════════════════════════════════════════════════════ */
self.addEventListener('fetch', function (event) {
  var url;
  try {
    url = new URL(event.request.url);
  } catch (e) {
    return; /* Malformed URL — let it pass through */
  }

  /* Only handle requests to our own server.
     External requests (fonts from Google, etc.) pass through. */
  if (url.origin !== self.location.origin) return;

  var params = url.searchParams;

  /* ── API calls: ALWAYS go to network ──
     File content, directory trees, search results, terminal output —
     these must NEVER be served from cache. Stale file content would
     be confusing and potentially destructive. */
  if (params.has('api')) return;

  /* ── Service Worker itself: pass through ──
     The browser handles SW updates on its own. */
  if (params.has('sw')) return;

  /* ── Workspace files (?workspace=...): Network-only (pass through) ──
    These are ALWAYS local (served by the PHP server on localhost).
    They change every time the user saves a file, so caching them
    provides no benefit and actively causes harm: when the device
    has no internet, the Android WebView's network stack can reject
    fetch() calls even for localhost, making the SW's internal
    fetch() fail silently. Passing through (like ?api= requests)
    lets the browser hit the local server directly — no SW
    interference, no offline white-screen.                    */
  if (params.has('workspace')) return;

  /* ── Diagnostic page: pass through ── */
  if (params.has('diagnose')) return;

  /* ── Vendor assets (?vendor=...): Cache-first ──
     CodeMirror, js-beautify, etc. These are downloaded libraries
     that almost never change. Serve the cached copy instantly,
     and quietly update the cache in the background. */
  if (params.has('vendor')) {
    event.respondWith(cacheFirstWithUpdate(event.request, CACHE_STATIC));
    return;
  }

  /* ── IDE static assets (?asset=...): Cache-first ──
     The IDE's own CSS, JS modules, icons, and manifest.
     Same reasoning as vendor: serve fast, update quietly. */
  if (params.has('asset')) {
    event.respondWith(cacheFirstWithUpdate(event.request, CACHE_STATIC));
    return;
  }

  /* ── App bundles (?bundle=...): Network-first ──
     The concatenated CSS/JS bundles. These change whenever you
     edit a file, so we try the network first. If offline, we
     fall back to the last successfully loaded copy. */
  if (params.has('bundle')) {
    event.respondWith(networkFirstWithFallback(event.request, CACHE_APP));
    return;
  }

  /* ── Page navigations (the IDE shell itself): Network-first ──
     When the user opens or refreshes the IDE page, try the network
     first. If offline, serve the cached shell. */
  if (event.request.mode === 'navigate') {
    event.respondWith(networkFirstWithFallback(event.request, CACHE_APP));
    return;
  }

  /* Everything else: let it pass through to the network normally. */
});

/* ═══════════════════════════════════════════════════════════════
STRATEGY: Cache-first with background update
─────────────────────────────────────────────
1. Check the cache. If a copy exists → serve it IMMEDIATELY.
2. Meanwhile, fetch the live version in the background.
3. If the live fetch succeeds → update the cache silently.
4. If the live fetch fails (offline) → the cached copy is already
   serving the user, so nothing breaks.

Result: instant loading, and the cache stays fresh over time.
═══════════════════════════════════════════════════════════════ */
function cacheFirstWithUpdate(request, cacheName) {
  return caches.open(cacheName).then(function (cache) {
    return cache.match(request).then(function (cachedResponse) {
      /* Start a background fetch regardless of cache hit/miss */
      var networkFetch = fetch(request)
        .then(function (networkResponse) {
          /* Only cache successful responses */
          if (networkResponse && networkResponse.status === 200) {
            cache.put(request, networkResponse.clone());
          }
          return networkResponse;
        })
        .catch(function () {
          /* Network failed (offline). If we had a cached copy,
             it's already being served. If not, return undefined
             and the browser will show its own error. */
          return cachedResponse;
        });

      /* Serve the cached copy immediately if available.
         Otherwise, wait for the network. */
      return cachedResponse || networkFetch;
    });
  });
}

/* ═══════════════════════════════════════════════════════════════
STRATEGY: Network-first with cache fallback
─────────────────────────────────────────────
1. Try to fetch from the network.
2. If successful → serve it AND save a copy to the cache.
3. If the network fails (offline) → serve the cached copy.
4. If neither works → show a friendly "offline" message.

Result: always fresh when online. Still works offline with the
last successfully loaded version.
═══════════════════════════════════════════════════════════════ */
function networkFirstWithFallback(request, cacheName) {
  return caches.open(cacheName).then(function (cache) {
    return fetch(request)
      .then(function (networkResponse) {
        /* Save a copy for offline use */
        if (networkResponse && networkResponse.status === 200) {
          cache.put(request, networkResponse.clone());
        }
        return networkResponse;
      })
      .catch(function () {
        /* Network failed → try the cache */
        return cache.match(request).then(function (cachedResponse) {
          if (cachedResponse) return cachedResponse;

          /* Nothing in cache either. Return a friendly message
             instead of the browser's default "no internet" page. */
          return new Response(
            '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
            '<meta name="viewport" content="width=device-width,initial-scale=1">' +
            '<title>Quirky IDE — Offline</title>' +
            '<style>body{font-family:system-ui,sans-serif;background:#0d1420;' +
            'color:#e8eef7;display:flex;align-items:center;justify-content:center;' +
            'min-height:100vh;margin:0;text-align:center}' +
            '.box{max-width:400px;padding:2rem}' +
            'h1{color:#f5a524;font-size:1.4rem}' +
            'p{color:#7186a5;line-height:1.6}</style></head><body>' +
            '<div class="box"><h1>📡 You\'re Offline</h1>' +
            '<p>This page hasn\'t been cached yet. Reconnect to the internet ' +
            'and reload. Once loaded, it will be available offline.</p>' +
            '</div></body></html>',
            {
              status: 503,
              statusText: 'Service Unavailable',
              headers: { 'Content-Type': 'text/html; charset=utf-8' }
            }
          );
        });
      });
  });
}

/* ═══════════════════════════════════════════════════════════════
STRATEGY: Stale-while-revalidate
─────────────────────────────────
1. Check the cache. If a copy exists → serve it IMMEDIATELY.
2. Regardless of cache hit/miss, fire a network fetch in the
   background.
3. When the network response arrives → update the cache silently.
4. If the network fails (offline) → the cached copy is already
   serving the user, so nothing breaks.

This gives the speed of cache-first with the freshness of
network-first, at the cost of one extra background request.
═══════════════════════════════════════════════════════════════ */
function staleWhileRevalidate(request, cacheName) {
  return caches.open(cacheName).then(function (cache) {
    return cache.match(request).then(function (cachedResponse) {
      // Always kick off a background refresh
      var networkFetch = fetch(request)
        .then(function (networkResponse) {
          if (networkResponse && networkResponse.status === 200) {
            cache.put(request, networkResponse.clone());
          }
          return networkResponse;
        })
        .catch(function () {
          // Offline — cached copy is already serving
          return cachedResponse;
        });

      // Serve cache immediately if available, else wait for network
      return cachedResponse || networkFetch;
    });
  });
}