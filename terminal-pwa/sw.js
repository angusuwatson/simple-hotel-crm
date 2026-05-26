var CACHE = 'lgf-terminal-v2';
var SHELL = ['/manifest.json', '/icon-152.png', '/icon-167.png', '/icon-180.png', '/icon-512.png'];

self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open(CACHE).then(function(cache) {
      return cache.addAll(SHELL);
    })
  );
});

self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(
        keys.map(function(k) {
          if (k !== CACHE) return caches.delete(k);
        })
      );
    })
  );
});

self.addEventListener('fetch', function(e) {
  var url = new URL(e.request.url);
  if (url.origin !== location.origin) return;
  if (url.pathname === '/' || url.pathname === '/index.html') {
    e.respondWith(
      fetch(e.request).catch(function() {
        return caches.match(e.request);
      })
    );
    return;
  }
  if (SHELL.indexOf(url.pathname) >= 0) {
    e.respondWith(caches.match(e.request));
    return;
  }
  e.respondWith(
    caches.match(e.request).then(function(cached) {
      return cached || fetch(e.request).then(function(resp) {
        if (resp && resp.ok && resp.type === 'basic') {
          var clone = resp.clone();
          caches.open(CACHE).then(function(cache) { cache.put(e.request, clone); });
        }
        return resp;
      });
    })
  );
});
