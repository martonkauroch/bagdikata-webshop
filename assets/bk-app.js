(function () {
  var A = window.BKApp = { deferred: null, reg: null };
  var isAdmin = function () { return /^#\/admin/.test(location.hash); };
  var fire = function () { try { window.dispatchEvent(new Event('bkapp')); } catch (e) {} };
  var setup = function () {
    if (A.ready || !isAdmin()) { return; }
    A.ready = true;
    var h = document.head;
    if (!document.querySelector('link[rel="manifest"]')) { var l = document.createElement('link'); l.rel = 'manifest'; l.href = '/admin.webmanifest'; h.appendChild(l); }
    if (!document.querySelector('meta[name="theme-color"]')) { var m = document.createElement('meta'); m.name = 'theme-color'; m.content = '#231f20'; h.appendChild(m); }
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/bk-sw.js', { scope: '/' }).then(function (r) { A.reg = r; fire(); }).catch(function () {});
    }
  };
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); A.deferred = e; fire(); });
  window.addEventListener('appinstalled', function () { A.deferred = null; fire(); });
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function (e) { if (e.data && typeof e.data.bkNav === 'string') { var u = e.data.bkNav, i = u.indexOf('#'); if (i >= 0) { location.hash = u.slice(i); } } });
  }
  A.supported = function () { return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window; };
  A.standalone = function () { try { return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true; } catch (e) { return false; } };
  A.canPrompt = function () { return !!A.deferred; };
  A.prompt = function () { var d = A.deferred; if (!d) { return Promise.resolve(false); } A.deferred = null; d.prompt(); return d.userChoice.then(function (c) { fire(); return c && c.outcome === 'accepted'; }); };
  A.registration = function () { setup(); return navigator.serviceWorker.ready; };
  A.label = function () {
    var u = navigator.userAgent, m = /Android[^;)]*;\s*([^;)]+?)(?:\s+Build|\))/i.exec(u), dev = m ? m[1].trim() : (/Android/i.test(u) ? 'Android' : /iPhone|iPad/i.test(u) ? 'iOS' : /Windows/i.test(u) ? 'Windows' : /Mac/i.test(u) ? 'Mac' : 'Számítógép');
    if (dev === 'K') { dev = 'Android'; }
    var br = /SamsungBrowser/i.test(u) ? 'Samsung Internet' : /Edg\//i.test(u) ? 'Edge' : /Firefox/i.test(u) ? 'Firefox' : /Chrome/i.test(u) ? 'Chrome' : 'böngésző';
    return dev + ' · ' + (A.standalone() ? 'admin app' : br);
  };
  A.subscribe = function (key) {
    return Notification.requestPermission().then(function (p) {
      if (p !== 'granted') { throw new Error('Az értesítések engedélyezése szükséges (a telefon/böngésző megtagadta).'); }
      return A.registration();
    }).then(function (r) {
      var raw = atob(key.replace(/-/g, '+').replace(/_/g, '/')), k = new Uint8Array(raw.length);
      for (var i = 0; i < raw.length; i++) { k[i] = raw.charCodeAt(i); }
      return r.pushManager.getSubscription().then(function (s) {
        if (s) {
          var cur = s.options && s.options.applicationServerKey ? new Uint8Array(s.options.applicationServerKey) : null, same = cur && cur.length === k.length;
          for (var j = 0; same && j < k.length; j++) { if (cur[j] !== k[j]) { same = false; } }
          if (same) { return s; }
          return s.unsubscribe().then(function () { return r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: k }); });
        }
        return r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: k });
      });
    }).then(function (s) { return s.toJSON(); });
  };
  A.current = function () {
    if (!A.supported()) { return Promise.resolve(null); }
    return navigator.serviceWorker.getRegistration('/').then(function (r) { return r ? r.pushManager.getSubscription() : null; }).catch(function () { return null; });
  };
  A.endpoint = function () {
    return A.current().then(function (s) {
      if (!s || !window.crypto || !crypto.subtle) { return null; }
      return crypto.subtle.digest('SHA-256', new TextEncoder().encode(s.endpoint)).then(function (b) { return Array.prototype.map.call(new Uint8Array(b), function (x) { return ('0' + x.toString(16)).slice(-2); }).join('').slice(0, 16); });
    });
  };
  A.unsubscribe = function () { return A.current().then(function (s) { if (!s) { return ''; } var ep = s.endpoint; return s.unsubscribe().then(function () { return ep; }); }); };
  setup();
  setInterval(setup, 700);
})();
