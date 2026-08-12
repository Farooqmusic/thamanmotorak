/* ---------------------------------------------------------------------------
   service worker — keeps the app opening instantly and working offline.

   The rule that matters here: this file must never be able to hold an old
   design on somebody's phone.  Two things guarantee that.

   1. Every address for app.css / app.js / cars.js now carries the file's own
      modification time (see asset() in lib.php).  Change a file and its
      address changes, so nothing that was stored before can ever be served in
      its place — a stored copy belongs to a different address.

   2. Anything that is served from storage is refreshed from the network in the
      background at the same time ("stale while revalidate").  So even if an
      old copy somehow survives, it is replaced on that very visit and the next
      opening of the site is correct.  No cache to clear by hand.

   The page itself (index.php) is always fetched from the network first; the
   stored copy is only a fallback for when there is no signal.
   --------------------------------------------------------------------------- */

var CACHE = 'tm-v5';

/* Only unversioned things belong here. Versioned assets are stored on first
   use instead, because their addresses are not known until the page is built. */
var SHELL = [
  './',
  './index.php',
  './manifest.php'
];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE)
      .then(function (c) { return c.addAll(SHELL).catch(function () {}); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) {
        return k === CACHE ? null : caches.delete(k);
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

/* lets the page ask for an immediate handover after an update */
self.addEventListener('message', function (e) {
  if (e.data === 'skipWaiting') self.skipWaiting();
});

self.addEventListener('fetch', function (e) {
  var url;
  try { url = new URL(e.request.url); } catch (err) { return; }

  /* never touch the api, the uploads, the admin panel, or another site */
  if (e.request.method !== 'GET' ||
      url.origin !== self.location.origin ||
      url.pathname.indexOf('api.php')   > -1 ||
      url.pathname.indexOf('admin.php') > -1 ||
      url.pathname.indexOf('file.php')  > -1) {
    return;
  }

  /* the page: network first, stored copy only if the network fails */
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).then(function (r) {
        var copy = r.clone();
        caches.open(CACHE).then(function (c) { c.put(e.request, copy); });
        return r;
      }).catch(function () {
        return caches.match(e.request).then(function (m) {
          return m || caches.match('./index.php');
        });
      })
    );
    return;
  }

  /* php that is not the page (manifest, generated bits) — always fresh */
  if (/\.php$/.test(url.pathname)) return;

  /* everything else: answer from storage at once, and refresh it in the
     background so a stored copy can never survive a second visit */
  e.respondWith(
    caches.open(CACHE).then(function (c) {
      return c.match(e.request).then(function (m) {
        var live = fetch(e.request).then(function (r) {
          if (r && r.status === 200 && r.type === 'basic') c.put(e.request, r.clone());
          return r;
        }).catch(function () { return m; });
        return m || live;
      });
    })
  );
});
