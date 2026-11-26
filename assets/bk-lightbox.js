(function () {
  var ROOT = 'main[data-testid="coming-soon"]';
  var css = document.createElement('style');
  css.textContent = ROOT + ' img:not([src$=".svg"]){cursor:zoom-in}'
    + '.bk-lb{position:fixed;inset:0;z-index:2147483000;display:flex;flex-direction:column;background:rgba(22,19,20,.97);color:#fff;-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);animation:bkLbIn .2s ease-out}'
    + '.bk-lb-top{display:flex;align-items:center;justify-content:space-between;padding:12px 16px}'
    + '.bk-lb-cnt{font-size:14px;color:rgba(255,255,255,.7);font-variant-numeric:tabular-nums;font-family:system-ui,sans-serif}'
    + '.bk-lb-btn{background:none;border:0;color:rgba(255,255,255,.85);cursor:pointer;border-radius:999px;padding:8px;line-height:0}'
    + '.bk-lb-btn:hover,.bk-lb-btn:focus-visible{background:rgba(255,255,255,.12);color:#fff;outline:none}'
    + '.bk-lb-stage{position:relative;flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:0 16px 24px}'
    + '.bk-lb-img{max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;box-shadow:0 25px 50px rgba(0,0,0,.5);animation:bkLbPop .25s ease-out;user-select:none;-webkit-user-drag:none}'
    + '.bk-lb-nav{position:absolute;top:50%;transform:translateY(-50%);background:rgba(22,19,20,.55);box-shadow:0 0 0 1px rgba(255,255,255,.15);border:0;color:#fff;cursor:pointer;border-radius:999px;padding:10px;line-height:0}'
    + '.bk-lb-nav:hover,.bk-lb-nav:focus-visible{background:rgba(22,19,20,.8);outline:none}'
    + '.bk-lb-prev{left:12px}.bk-lb-next{right:12px}'
    + '@media (min-width:640px){.bk-lb-stage{padding:0 72px 32px}.bk-lb-top{padding:12px 24px}.bk-lb-prev{left:20px}.bk-lb-next{right:20px}}'
    + '@keyframes bkLbIn{from{opacity:0}to{opacity:1}}@keyframes bkLbPop{from{opacity:0;transform:scale(.97)}to{opacity:1;transform:none}}'
    + '@media (prefers-reduced-motion:reduce){.bk-lb,.bk-lb-img{animation:none}}';
  document.head.appendChild(css);
  var ico = function (d) { return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>'; };
  var X = ico('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'), L = ico('<path d="m15 18-6-6 6-6"/>'), R = ico('<path d="m9 18 6-6-6-6"/>');
  var en = function () { return (document.documentElement.lang || '').toLowerCase().indexOf('en') === 0; };
  var pics = function (root) {
    return Array.prototype.filter.call(root.querySelectorAll('img'), function (i) {
      return !/\.svg(\?|$)/i.test(i.currentSrc || i.src) && !i.closest('[data-testid="cs-logo"]') && i.offsetWidth > 60;
    });
  };
  var open = function (list, start) {
    var srcs = list.map(function (i) { return i.currentSrc || i.src; }), n = srcs.length, idx = start, prevFocus = document.activeElement, sx = null;
    var box = document.createElement('div');
    box.className = 'bk-lb'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('data-testid', 'cs-lightbox');
    box.innerHTML = '<div class="bk-lb-top"><span class="bk-lb-cnt"></span><button type="button" class="bk-lb-btn bk-lb-close">' + X + '</button></div>'
      + '<div class="bk-lb-stage"><img class="bk-lb-img" alt="">' + (n > 1 ? '<button type="button" class="bk-lb-nav bk-lb-prev">' + L + '</button><button type="button" class="bk-lb-nav bk-lb-next">' + R + '</button>' : '') + '</div>';
    var img = box.querySelector('.bk-lb-img'), cnt = box.querySelector('.bk-lb-cnt'), stage = box.querySelector('.bk-lb-stage'), close = box.querySelector('.bk-lb-close');
    var e = en();
    close.setAttribute('aria-label', e ? 'Close' : 'Bezárás');
    if (n > 1) { box.querySelector('.bk-lb-prev').setAttribute('aria-label', e ? 'Previous picture' : 'Előző kép'); box.querySelector('.bk-lb-next').setAttribute('aria-label', e ? 'Next picture' : 'Következő kép'); }
    var show = function () {
      img.src = srcs[idx]; img.style.animation = 'none'; void img.offsetWidth; img.style.animation = '';
      img.alt = list[idx].alt || ''; cnt.textContent = (idx + 1) + ' / ' + n;
      box.setAttribute('aria-label', (e ? 'Picture ' : 'Kép ') + (idx + 1) + ' / ' + n);
    };
    var go = function (d) { if (n > 1) { idx = (idx + d + n) % n; show(); } };
    var oldOv = document.body.style.overflow;
    var shut = function () {
      document.removeEventListener('keydown', key, true);
      document.body.style.overflow = oldOv;
      if (box.parentNode) { box.parentNode.removeChild(box); }
      try { if (prevFocus && prevFocus.focus) { prevFocus.focus({ preventScroll: true }); } } catch (x) {}
    };
    var key = function (ev) {
      if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); shut(); }
      else if (ev.key === 'ArrowLeft') { ev.preventDefault(); go(-1); }
      else if (ev.key === 'ArrowRight') { ev.preventDefault(); go(1); }
      else if (ev.key === 'Tab') {
        var f = Array.prototype.slice.call(box.querySelectorAll('button'));
        var i = f.indexOf(document.activeElement);
        ev.preventDefault(); f[(i + (ev.shiftKey ? -1 : 1) + f.length) % f.length].focus();
      }
    };
    close.addEventListener('click', shut);
    stage.addEventListener('click', function (ev) { if (ev.target === stage) { shut(); } });
    if (n > 1) {
      box.querySelector('.bk-lb-prev').addEventListener('click', function () { go(-1); });
      box.querySelector('.bk-lb-next').addEventListener('click', function () { go(1); });
    }
    box.addEventListener('touchstart', function (ev) { sx = ev.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', function (ev) { if (sx === null) { return; } var dx = ev.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) { go(dx < 0 ? 1 : -1); } sx = null; }, { passive: true });
    document.addEventListener('keydown', key, true);
    document.body.style.overflow = 'hidden';
    document.body.appendChild(box);
    show();
    close.focus({ preventScroll: true });
  };
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || t.tagName !== 'IMG' || ev.button !== 0) { return; }
    var root = t.closest(ROOT);
    if (!root || document.querySelector('.bk-lb')) { return; }
    var list = pics(root), i = list.indexOf(t);
    if (i < 0) { return; }
    ev.preventDefault();
    open(list, i);
  });
})();
