/* Facilita — Service Worker do Portal do Aluno (cache mínimo offline) */

const CACHE = 'facilita-portal-v1';
const PRECACHE = [
    '/images/facilitaicon.png',
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return cache.addAll(PRECACHE).catch(function () {});
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    var url = new URL(event.request.url);

    if (url.pathname.indexOf('/portal/aluno') === -1) {
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
