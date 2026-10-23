// CLUK website: header (login menu, mobile menu, language), cookie consent and visitor statistics.
(function () {
  var login = document.getElementById('login');
  document.addEventListener('click', function (e) { if (login && login.open && !login.contains(e.target)) login.open = false; });
  var nav = document.getElementById('nav'), mb = document.getElementById('menuBtn');
  if (mb) mb.addEventListener('click', function () { var o = nav.classList.toggle('open'); mb.setAttribute('aria-expanded', o); });
  var lang = document.getElementById('lang');
  if (lang) lang.addEventListener('change', function () { location.href = lang.value; });

  // ---------- consent ----------
  var cfg = {}; try { cfg = JSON.parse(document.getElementById('site-cfg').textContent); } catch (e) {}
  function cookie(n) { var m = document.cookie.match('(?:^|; )' + n + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : null; }
  function setCookie(n, v, days) { var d = new Date(Date.now() + days * 864e5); document.cookie = n + '=' + encodeURIComponent(v) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); }
  function uuid() { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }); }
  var box = document.getElementById('consent');
  function decided(v) {
    setCookie('cluk_consent', v, 365);
    if (v === 'all') { if (!cookie('cluk_vid')) setCookie('cluk_vid', uuid(), 395); track('view'); }
    else { setCookie('cluk_vid', '', -1); }
    if (box) box.hidden = true;
  }
  if (box) {
    if (!cookie('cluk_consent')) box.hidden = false;
    box.addEventListener('click', function (e) { var b = e.target.closest('[data-consent]'); if (b) decided(b.dataset.consent); });
  }
  document.addEventListener('click', function (e) { if (e.target.closest('[data-cookie-settings]')) { e.preventDefault(); if (box) box.hidden = false; } });

  // ---------- statistics (only with consent) ----------
  var token = (document.querySelector('meta[name=csrf-token]') || {}).content;
  function track(type, d) {
    if (cookie('cluk_consent') !== 'all' || !cfg.track) return;
    if (!cookie('cluk_vid')) setCookie('cluk_vid', uuid(), 395);
    d = d || {};
    var body = { type: type, section: d.section || cfg.section || null, path: location.pathname + location.search, label: d.label || null, item_id: d.item_id || null, meta: d.meta || null };
    if (type === 'view') {
      var p = new URLSearchParams(location.search);
      body.referrer = document.referrer && document.referrer.indexOf(location.host) < 0 ? document.referrer : null;
      body.utm = { source: p.get('utm_source'), medium: p.get('utm_medium'), campaign: p.get('utm_campaign') };
    }
    try { fetch(cfg.track, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(body) }).catch(function () {}); } catch (e) {}
  }
  window.cluk = { track: track };
  track('view');
})();

// Post videos: play muted while on screen, pause when scrolled away (not with reduced motion or data saver).
(function () {
  var vids = document.querySelectorAll('video[data-auto]');
  if (!vids.length) return;
  var calm = matchMedia('(prefers-reduced-motion: reduce)').matches || (navigator.connection && navigator.connection.saveData);
  if (calm || !('IntersectionObserver' in window)) { vids.forEach(function (v) { v.preload = 'metadata'; }); return; }
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      var v = e.target;
      if (e.isIntersecting && e.intersectionRatio >= 0.5) { v.muted = true; var p = v.play(); if (p && p.catch) p.catch(function () {}); }
      else v.pause();
    });
  }, { threshold: [0, 0.5] });
  vids.forEach(function (v) { v.muted = true; io.observe(v); });
})();
