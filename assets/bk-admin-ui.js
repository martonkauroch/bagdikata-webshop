(function () {
  var KC = 'bk_admin_collapsed', KS = 'bk_admin_scale';
  var get = function (k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } };
  var put = function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} };
  var css = document.createElement('style');
  css.textContent = 'html[data-bk-admin][data-bk-scale="m"]{font-size:15px}html[data-bk-admin][data-bk-scale="s"]{font-size:14px}'
    + '[data-bk-head] h2{cursor:pointer;user-select:none}'
    + '[data-bk-head] h2::after{content:"";display:inline-block;width:.42em;height:.42em;border-right:2px solid #9ca3af;border-bottom:2px solid #9ca3af;transform:rotate(45deg);margin-left:.6em;vertical-align:.18em;transition:transform .15s}'
    + '[data-bk-col="1"] [data-bk-head] h2::after{transform:rotate(-45deg);vertical-align:.05em}'
    + '[data-bk-head] h2:hover{color:#4f46e5}'
    + '[data-bk-card][data-bk-col="1"]>:not([data-bk-head]){display:none!important}'
    + '[data-bk-card][data-bk-col="1"]{padding-bottom:0}'
    + '[data-bk-card][data-bk-col="1"]>[data-bk-head]{border-bottom:0!important;margin-bottom:0!important}';
  document.head.appendChild(css);
  var isAdmin = function () { return /^#\/admin(\/|$|\?)/.test(location.hash) && !/^#\/admin\/belepes/.test(location.hash); };
  var page = function () { return location.hash.replace(/\?.*$/, ''); };
  var state = function () { try { return JSON.parse(get(KC, '{}')) || {}; } catch (e) { return {}; } };
  var keyOf = function (h2) { return page() + '|' + (h2.textContent || '').trim().slice(0, 80); };
  var isCard = function (el) {
    if (!el || el === document.body || el.tagName === 'MAIN') { return false; }
    var cs = getComputedStyle(el);
    return parseFloat(cs.borderTopWidth) > 0 && parseFloat(cs.borderTopLeftRadius) > 3 && el.offsetWidth > 240;
  };
  var scan = function () {
    var root = document.documentElement;
    if (!isAdmin()) { root.removeAttribute('data-bk-admin'); return; }
    root.setAttribute('data-bk-admin', '1');
    root.setAttribute('data-bk-scale', get(KS, 'm'));
    var st = state();
    Array.prototype.forEach.call(document.querySelectorAll('#root h2'), function (h2) {
      if (h2.closest('[role=dialog],[aria-modal=true],[data-bk-nocollapse]')) { return; }
      var card = null, head = h2, el = h2;
      for (var i = 0; i < 4 && el.parentElement; i++) {
        var p = el.parentElement;
        if (isCard(p)) { card = p; head = el; break; }
        el = p;
      }
      if (!card) { return; }
      if (card.querySelector('[data-bk-head]') && card.querySelector('[data-bk-head]') !== head) { return; }
      var k = keyOf(h2);
      card.setAttribute('data-bk-card', k);
      head.setAttribute('data-bk-head', '1');
      card.setAttribute('data-bk-col', st[k] ? '1' : '0');
    });
  };
  var pending = false;
  var later = function () { if (pending) { return; } pending = true; requestAnimationFrame(function () { pending = false; scan(); }); };
  document.addEventListener('click', function (ev) {
    var h2 = ev.target && ev.target.closest && ev.target.closest('[data-bk-head] h2');
    if (!h2) { return; }
    var card = h2.closest('[data-bk-card]');
    if (!card) { return; }
    var k = card.getAttribute('data-bk-card'), st = state(), col = card.getAttribute('data-bk-col') !== '1';
    if (col) { st[k] = 1; } else { delete st[k]; }
    put(KC, JSON.stringify(st));
    card.setAttribute('data-bk-col', col ? '1' : '0');
  });
  window.BKAdminUI = {
    scale: function () { return get(KS, 'm'); },
    setScale: function (v) { put(KS, v); document.documentElement.setAttribute('data-bk-scale', v); },
    expandAll: function () { var st = state(), p = page() + '|'; Object.keys(st).forEach(function (k) { if (k.indexOf(p) === 0) { delete st[k]; } }); put(KC, JSON.stringify(st)); scan(); },
    collapsedCount: function () { return Object.keys(state()).length; },
    resetAll: function () { put(KC, '{}'); scan(); }
  };
  try { new MutationObserver(later).observe(document.documentElement, { childList: true, subtree: true }); } catch (e) {}
  setInterval(scan, 1000);
  scan();
})();
