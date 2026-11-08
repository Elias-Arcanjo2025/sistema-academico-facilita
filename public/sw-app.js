/* Facilita — Service Worker (staff + portal, apenas mobile) */

const CACHE = 'facilita-app-v2';
const PRECACHE = ['/images/facilitaicon.png'];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return cache.addAll(PRECACHE).catch(function () {});
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) {
                return caches.delete(k);
            }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    var url = new URL(event.request.url);
    var path = url.pathname;

    if (path.indexOf('/portal/aluno') === -1 && path.indexOf('/app') === -1) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(function (response) {
                if (response && response.status === 200 && response.type === 'basic') {
                    var clone = response.clone();
                    caches.open(CACHE).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            })
            .catch(function () {
                return caches.match(event.request);
            })
    );
});
