const shell = 'oneflo-shell'

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(shell).then((cache) => cache.addAll(['/', '/manifest.webmanifest'])))
})

self.addEventListener('fetch', (event) => {
  const request = event.request
  if (request.method !== 'GET' || new URL(request.url).pathname.startsWith('/api/')) {
    return
  }
  event.respondWith(fetch(request).catch(() => caches.match(request)))
})
