(function () {
  try {
    var evq = [], RE = /^G-[A-Z0-9]{4,15}$/, id = null, loaded = false, prevC, lastSent = null, pending = null, bannerEl = null, linkEl = null;
    function B() { var b = window.__BK; return b && b.shared ? b : null; }
    function consent() { try { return localStorage.getItem('bk_consent'); } catch (e) { return null; } }
    function setConsent(v) { try { localStorage.setItem('bk_consent', v ? '1' : '0'); if (!v) localStorage.removeItem('bk_vid'); } catch (e) {} }
    function isAdminPage() { return /^#\/admin/.test(location.hash); }
    function vpath() {
      var h = (location.hash || '').replace(/^#/, '').split('?')[0];
      if (!h || h === '/') return '/';
      if (/^\/hamarosan-elonezet/.test(h)) return '/';
      return h.replace(/\/+$/, '') || '/';
    }
    function hu() { var l = (document.documentElement.lang || navigator.language || 'hu').toLowerCase(); return l.indexOf('en') !== 0; }
    function gtag() { window.dataLayer.push(arguments); }
    function load() {
      if (loaded) return; loaded = true;
      window.dataLayer = window.dataLayer || [];
      window.gtag = gtag;
      gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'granted' });
      gtag('js', new Date());
      gtag('config', id, { send_page_view: false });
      while (evq.length) { var q = evq.shift(); gtag('event', q[0], q[1]); }
      var s = document.createElement('script'); s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
      document.head.appendChild(s);
    }
    function revoke() {
      if (loaded) gtag('consent', 'update', { analytics_storage: 'denied' });
      document.cookie.split(';').forEach(function (c) {
        var n = c.split('=')[0].trim(); if (!/^_ga/.test(n)) return;
        var h = location.hostname, ds = ['', h, '.' + h, '.' + h.replace(/^www\./, '')];
        ds.forEach(function (d) { document.cookie = n + '=; Max-Age=0; path=/' + (d ? '; domain=' + d : ''); });
      });
    }
    function pageView() {
      if (!loaded || isAdminPage()) { pending = null; return; }
      var p = vpath();
      if (pending && pending === p && p !== lastSent) {
        lastSent = p; pending = null;
        gtag('event', 'page_view', { page_location: location.origin + p, page_path: p, page_title: document.title });
        return;
      }
      if (p !== lastSent) pending = p;
    }
    function closeBanner() { if (bannerEl) { bannerEl.remove(); bannerEl = null; } }
    function landingBanner() {
      if (bannerEl) return;
      var H = hu(), d = document.createElement('div');
      d.setAttribute('role', 'dialog'); d.setAttribute('aria-label', H ? 'Süti-beállítások' : 'Cookie settings'); d.setAttribute('data-testid', 'ga-consent');
      d.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:60;max-width:40rem;margin:0 auto;background:#fff;border:1px solid #e5e0d8;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.18);padding:16px 18px;font:14px/1.5 Inter,system-ui,sans-serif;color:#222';
      var p = document.createElement('p'); p.style.margin = '0';
      p.textContent = H
        ? 'Ha elfogadod, a Google Analytics (Google Ireland Ltd.) sütijeivel névtelen látogatottsági statisztikát készítünk, hogy lássuk, hányan és honnan érdeklődnek. Hirdetési célra nem használjuk. Döntésed bármikor módosítható az oldal alján a „Süti-beállítások” linkkel.'
        : 'If you accept, we use Google Analytics (Google Ireland Ltd.) cookies to create anonymous visitor statistics, so we can see how many people are interested and where they come from. They are not used for advertising. You can change your choice any time with the “Cookie settings” link at the bottom of the page.';
      var row = document.createElement('div'); row.style.cssText = 'display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap';
      function btn(t, dark, v) {
        var b = document.createElement('button'); b.type = 'button'; b.textContent = t;
        b.style.cssText = 'cursor:pointer;border-radius:999px;padding:9px 18px;font:500 14px Inter,system-ui,sans-serif;' + (dark ? 'background:#161314;color:#fff;border:1px solid #161314' : 'background:#fff;color:#161314;border:1px solid #d6d0c7');
        b.setAttribute('data-testid', v ? 'ga-consent-yes' : 'ga-consent-no');
        b.onclick = function () { setConsent(v); closeBanner(); tick(); };
        return b;
      }
      row.appendChild(btn(H ? 'Nem fogadom el' : 'No, thanks', false, false));
      row.appendChild(btn(H ? 'Elfogadom' : 'Accept', true, true));
      d.appendChild(p); d.appendChild(row); document.body.appendChild(d); bannerEl = d;
    }
    function landingLink() {
      if (linkEl) return;
      var a = document.createElement('button'); a.type = 'button'; a.setAttribute('data-testid', 'ga-consent-open');
      a.textContent = hu() ? 'Süti-beállítások' : 'Cookie settings';
      a.style.cssText = 'display:block;margin:0 auto 18px;background:none;border:0;cursor:pointer;color:#6b6468;font:13px Inter,system-ui,sans-serif;text-decoration:underline';
      a.onclick = function () { landingBanner(); };
      document.body.appendChild(a); linkEl = a;
    }
    function tick() {
      var b = B(); if (!b) return;
      if (!b.ga || !RE.test(b.ga) || b.admin) { closeBanner(); return; }
      id = b.ga;
      var landing = b.site === false;
      var c = consent();
      if (landing) { landingLink(); if (c === null) landingBanner(); }
      if (c === '1') { if (!loaded) load(); else if (prevC === '0') gtag('consent', 'update', { analytics_storage: 'granted' }); pageView(); }
      else if (c === '0' && prevC === '1') revoke();
      prevC = c;
    }
    function prod(pid) { var a = window.__bkProds || []; for (var i = 0; i < a.length; i++) { if (a[i] && a[i].id === pid) return a[i]; } return null; }
    window.bkGaEvent = function (name, params) {
      try {
        if (isAdminPage() || consent() !== '1') return;
        if (!loaded) { evq.push([name, params || {}]); if (evq.length > 20) evq.shift(); return; }
        gtag('event', name, params || {});
      } catch (e) {}
    };
    window.bkGaItem = function (type, pid, ch) {
      if (!pid) return;
      var p = prod(pid), it = { item_id: pid, item_name: p ? p.name : pid, price: p ? p.price : undefined, item_category: p && p.category ? p.category : undefined, quantity: 1 };
      if (type === 'view') window.bkGaEvent('view_item', { currency: 'HUF', value: it.price || 0, items: [it] });
      else if (type === 'cart') window.bkGaEvent('add_to_cart', { currency: 'HUF', value: it.price || 0, items: [it] });
      else if (type === 'click') window.bkGaEvent('select_item', { items: [it] });
      else if (type === 'share') window.bkGaEvent('share', { method: ch || '', content_type: 'product', item_id: pid });
    };
    window.__bkGaTick = tick;
    setInterval(tick, 700);
  } catch (e) {}
})();
