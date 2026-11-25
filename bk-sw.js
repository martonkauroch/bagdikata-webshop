self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = { t: 'Bagdi Kata', b: e.data ? e.data.text() : '' }; }
  var opt = {
    body: d.b || '',
    icon: '/assets/app-icon-192.png',
    badge: '/assets/app-badge-96.png',
    tag: d.g || 'bk',
    renotify: true,
    timestamp: Date.now(),
    vibrate: [200, 100, 200],
    requireInteraction: d.y === 'order',
    data: { u: d.u || '/#/admin' }
  };
  e.waitUntil(self.registration.showNotification(d.t || 'Bagdi Kata', opt));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var u = (e.notification.data && e.notification.data.u) || '/#/admin';
  var url = new URL(u, self.location.origin).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) {
      var c = list[i];
      if (c.url.indexOf(self.location.origin) === 0 && 'focus' in c) {
        try { c.postMessage({ bkNav: u }); } catch (x) {}
        return c.focus();
      }
    }
    return self.clients.openWindow(url);
  }));
});
self.addEventListener('pushsubscriptionchange', function (e) {
  e.waitUntil(fetch('/api/push.php', { credentials: 'same-origin', headers: { 'X-BK': '1' } }).then(function (r) { return r.json(); }).then(function (j) {
    if (!j || !j.ok) { return; }
    var raw = atob(j.key.replace(/-/g, '+').replace(/_/g, '/')), key = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) { key[i] = raw.charCodeAt(i); }
    return self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }).then(function (s) {
      return fetch('/api/push.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-BK': '1', 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'subscribe', sub: s.toJSON(), label: 'Android (megújítva)' }) });
    });
  }).catch(function () {}));
});
