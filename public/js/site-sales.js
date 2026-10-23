// CLUK website · sales: product menu, search suggestions, filter sidebar, view, basket (quote request) and slideshow.
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  document.documentElement.classList.add('js');
  var C = JSON.parse(($('s-cfg') || { textContent: '{}' }).textContent || '{}'), T = C.t || {}, STORE = 'cluk-sales-basket';
  C.s = C.s || {};
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var loc = C.locale === 'en' ? 'en-GB' : (C.locale || 'nl') + '-BE';
  var eur = function (c) { return '€ ' + (c / 100).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var tr = function (k, r) { var s = T[k] || k; Object.keys(r || {}).forEach(function (x) { s = s.split(':' + x).join(r[x]); }); return s; };

  // ---------- product menu ----------
  var mega = $('mega'), megaBtn = $('megaBtn');
  function megaOpen(o) { if (!mega) return; mega.hidden = !o; megaBtn.setAttribute('aria-expanded', o); }
  if (megaBtn) megaBtn.addEventListener('click', function (e) { e.stopPropagation(); megaOpen(mega.hidden); });
  document.addEventListener('click', function (e) { if (mega && !mega.hidden && !mega.contains(e.target)) megaOpen(false); });

  // ---------- search suggestions ----------
  var sq = $('sq'), box = $('suggest'), form = $('ssearch'), timer, last = '', active = -1;
  function links() { return box ? Array.prototype.slice.call(box.querySelectorAll('a')) : []; }
  function showSuggest(d, q) {
    if (!d || (!d.products.length && !d.categories.length && !d.brands.length)) {
      box.innerHTML = '<div class="sg-none">' + esc(T.s_none || '') + '</div>'; box.hidden = false; return;
    }
    var left = d.products.length ? '<h4>' + esc(C.s.products) + '</h4>' + d.products.map(function (p) {
      return '<a class="sg-p" role="option" href="' + esc(p.url) + '"><span class="im">' + (p.photo ? '<img src="' + esc(p.photo) + '" alt="">' : '') + '</span><span><b>' + esc(p.name) + '</b><small>' + esc(p.code || '') + '</small></span><span class="pr">' + (p.price ? eur(p.price) : '') + '</span></a>';
    }).join('') : '';
    var right = (d.categories.length ? '<h4>' + esc(C.s.categories) + '</h4>' + d.categories.map(function (c) { return '<a class="sg-l" role="option" href="' + esc(c.url) + '">' + esc(c.name) + '</a>'; }).join('') : '') +
      (d.brands.length ? '<h4>' + esc(C.s.brands) + '</h4>' + d.brands.map(function (b) { return '<a class="sg-l" role="option" href="' + esc(b.url) + '">' + esc(b.name) + ' <small>(' + b.n + ')</small></a>'; }).join('') : '');
    box.innerHTML = '<div class="col">' + (left || right) + '</div>' + (left && right ? '<div class="col">' + right + '</div>' : '') +
      (d.total ? '<a class="sg-all" href="' + esc(C.urls.search) + '?q=' + encodeURIComponent(q) + '">' + esc((C.s.view_all || '').replace(':n', d.total)) + ' →</a>' : '');
    box.hidden = false; active = -1;
  }
  if (sq && box) {
    sq.addEventListener('input', function () {
      clearTimeout(timer);
      var q = sq.value.trim();
      if (q.length < 2) { box.hidden = true; return; }
      timer = setTimeout(function () {
        last = q;
        fetch(C.urls.suggest + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); })
          .then(function (d) { if (last === q && sq.value.trim() === q) showSuggest(d, q); }).catch(function () {});
      }, 140);
    });
    sq.addEventListener('keydown', function (e) {
      var l = links();
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (box.hidden || !l.length) return;
        e.preventDefault();
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + l.length) % l.length;
        l.forEach(function (a, i) { a.classList.toggle('on', i === active); });
      } else if (e.key === 'Enter' && active >= 0 && l[active]) { e.preventDefault(); location.href = l[active].href; }
      else if (e.key === 'Escape') { box.hidden = true; }
    });
    form.addEventListener('submit', function (e) { if (!sq.value.trim()) e.preventDefault(); });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) box.hidden = true; });
    document.addEventListener('keydown', function (e) { if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) { e.preventDefault(); sq.focus(); } });
  }

  // ---------- filters ----------
  var ff = $('ff'), filters = $('filters'), narrow = function () { return matchMedia('(max-width: 1000px)').matches; };
  if (ff) {
    ff.addEventListener('change', function (e) {
      if (e.target.matches('input[type=number]') && !narrow()) { ff.requestSubmit(); return; }
      if (!narrow()) ff.requestSubmit();
    });
    // empty fields stay out of the address
    ff.addEventListener('submit', function () { ff.querySelectorAll('input[type=number], select').forEach(function (i) { if (!i.value) i.disabled = true; }); });
    document.querySelectorAll('.more-list').forEach(function (l) {
      var rows = l.querySelectorAll('.opt'), lim = +l.dataset.limit || 8;
      if (rows.length <= lim) return;
      var hidden = Array.prototype.slice.call(rows, lim).filter(function (r) { return !r.querySelector('input').checked; });
      if (!hidden.length) return;
      hidden.forEach(function (r) { r.classList.add('hide'); });
      var b = document.createElement('button'); b.type = 'button'; b.className = 'more-t'; b.textContent = (C.s && C.s.more) || '+';
      b.addEventListener('click', function () { var o = b.dataset.open !== '1'; hidden.forEach(function (r) { r.classList.toggle('hide', !o); }); b.dataset.open = o ? '1' : ''; b.textContent = o ? C.s.less : C.s.more; });
      l.appendChild(b);
    });
  }
  var sortSel = $('sortSel'); if (sortSel && ff) sortSel.addEventListener('change', function () { ff.requestSubmit(); });
  if ($('fOpen')) $('fOpen').addEventListener('click', function () { filters.classList.add('on'); scrim.classList.add('on'); openD = filters; });
  if ($('fClose')) $('fClose').addEventListener('click', function () { close(); });

  // ---------- grid / list ----------
  var grid = $('sgrid');
  function view(v) {
    if (!grid) return;
    grid.classList.toggle('as-list', v === 'list');
    document.querySelectorAll('[data-view]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.view === v); });
    try { localStorage.setItem('cluk-sales-view', v); } catch (e) {}
  }
  document.querySelectorAll('[data-view]').forEach(function (b) { b.addEventListener('click', function () { view(b.dataset.view); }); });
  try { view(localStorage.getItem('cluk-sales-view') || 'grid'); } catch (e) {}

  // ---------- basket ----------
  var scrim = $('scrim'), bk = $('bk'), added = $('added'), openD = null;
  var B = { lines: {}, delivery: 'pick', form: {} };
  try { var saved = JSON.parse(localStorage.getItem(STORE) || 'null'); if (saved && saved.lines) B = Object.assign(B, saved); } catch (e) {}
  function save() { try { localStorage.setItem(STORE, JSON.stringify(B)); } catch (e) {} }
  function count() { var n = 0; Object.keys(B.lines).forEach(function (k) { n += B.lines[k].q; }); return n; }
  function badge(bump) { var n = $('basketN'); if (n) n.textContent = count(); var b = $('basketBtn'); if (bump && b) { b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); } }
  function open(d) { openD = d; d.classList.add('on'); scrim.classList.add('on'); }
  function close() { if (openD) openD.classList.remove('on'); if (filters) filters.classList.remove('on'); scrim.classList.remove('on'); openD = null; }
  scrim.addEventListener('click', function () { keep(); close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); added.hidden = true; megaOpen(false); } });
  function add(btn, q) {
    var id = btn.dataset.add, l = B.lines[id];
    B.lines[id] = { q: (l ? l.q : 0) + (q || 1), name: btn.dataset.name, price: +btn.dataset.price || 0, code: btn.dataset.code };
    save(); badge(true);
    if (!(+btn.dataset.price)) { showBasket(); open(bk); return; }
    // "would you also like" from the related products or the list on this page
    var others = Array.prototype.slice.call(document.querySelectorAll(($('related') ? '#related ' : '') + '.scard')).filter(function (c) { return c.dataset.id !== id; }).slice(0, 3);
    added.innerHTML = '<h3 id="addedTitle">' + esc(T.added) + '</h3><div>' + esc(btn.dataset.name) + (q > 1 ? ' × ' + q : '') + '</div>' +
      (others.length ? '<div class="also"><span class="note">' + esc(T.also) + '</span>' + others.map(function (c) { var a = c.querySelector('h3 a'), p = c.querySelector('.sprice'); return '<a href="' + esc(a.href) + '"><span>' + esc(a.textContent) + '</span><span class="num">' + esc(p ? p.firstChild.textContent.trim() : '') + '</span></a>'; }).join('') + '</div>' : '') +
      '<div class="acts"><button class="btn ghost" type="button" data-x>' + esc(T['continue']) + '</button><button class="btn" type="button" data-open>' + esc(T.view_basket) + '</button></div>';
    added.hidden = false;
    clearTimeout(add.t); add.t = setTimeout(function () { added.hidden = true; }, 9000);
  }
  added.addEventListener('click', function (e) {
    if (e.target.closest('[data-x]')) added.hidden = true;
    if (e.target.closest('[data-open]')) { added.hidden = true; showBasket(); open(bk); }
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.sadd'); if (b) { e.preventDefault(); add(b, 1); return; }
    var p = e.target.closest('#pAdd'); if (p) { add(p, Math.max(1, +$('pQty').value || 1)); return; }
    var s = e.target.closest('.pbuy [data-step]'); if (s) { var i = $('pQty'); i.value = Math.max(1, (+i.value || 1) + (+s.dataset.step)); }
  });
  if ($('basketBtn')) $('basketBtn').addEventListener('click', function () { showBasket(); open(bk); });

  var FIELDS = ['name', 'company', 'vat', 'email', 'phone', 'message'];
  function keep() { FIELDS.forEach(function (k) { var el = $('b_' + k); if (el) B.form[k] = el.value; }); var d = bk.querySelector('input[name=dl]:checked'); if (d) B.delivery = d.value; save(); }
  function showBasket(sent) {
    var check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="m5 12 5 5 9-10"/></svg>';
    if (sent) { bk.innerHTML = '<div class="dhead"><h2 id="bkTitle">' + esc(T.sent_title) + '</h2><button class="x" type="button" data-close aria-label="' + esc(T.close) + '">✕</button></div><div class="dbody"><div class="tick">' + check + '</div><h3 style="margin:0;font-size:22px">' + esc(tr('sent_hello', { name: sent })) + '</h3><p style="margin:0">' + esc(T.sent_text) + '</p></div>'; return; }
    var ids = Object.keys(B.lines), total = 0;
    var lines = ids.map(function (id) {
      var l = B.lines[id]; total += l.price * l.q;
      return '<div class="bline"><div><div class="nm">' + esc(l.name) + '</div><div class="pp num">' + esc(l.code) + ' · ' + (l.price ? eur(l.price) : esc(T.on_request)) + '</div><button class="rm" type="button" data-rm="' + id + '">' + esc(T.remove) + '</button></div>' +
        '<div class="qty"><button type="button" data-st="-1" data-id="' + id + '" aria-label="' + esc(T.one_less) + '">−</button><input type="number" min="1" data-q="' + id + '" value="' + l.q + '" aria-label="' + esc(T.quantity) + '"><button type="button" data-st="1" data-id="' + id + '" aria-label="' + esc(T.one_more) + '">+</button></div>' +
        '<div class="num" style="font-weight:700;min-width:72px;text-align:right">' + (l.price ? eur(l.price * l.q) : '–') + '</div></div>';
    }).join('');
    var f = function (k, type, ac) { return '<div class="field"><label for="b_' + k + '">' + esc(T[k]) + '</label>' + (type === 'textarea' ? '<textarea id="b_' + k + '" placeholder="' + esc(T.message_hint) + '">' + esc(B.form[k] || '') + '</textarea>' : '<input id="b_' + k + '" type="' + type + '" value="' + esc(B.form[k] || '') + '"' + (ac ? ' autocomplete="' + ac + '"' : '') + '>') + '</div>'; };
    bk.innerHTML = '<div class="dhead"><h2 id="bkTitle">' + esc(T.basket) + '</h2><button class="x" type="button" data-close aria-label="' + esc(T.close) + '">✕</button></div><div class="dbody">' +
      (ids.length ? lines + '<div class="sum num"><span>' + esc(T.total) + '</span><span>' + eur(total) + '</span></div>' : '<p class="note">' + esc(T.empty) + '</p>') +
      '<p class="note-box">' + esc(T.quote_note) + '</p>' +
      '<div class="field"><span class="fl">' + esc(T.delivery) + '</span><div class="seg"><label><input type="radio" name="dl" value="pick"' + (B.delivery !== 'ship' ? ' checked' : '') + '>' + esc(T.pick) + '</label><label><input type="radio" name="dl" value="ship"' + (B.delivery === 'ship' ? ' checked' : '') + '>' + esc(T.ship) + '</label></div></div>' +
      '<div class="two">' + f('name', 'text', 'name') + f('company', 'text', 'organization') + '</div><div class="two">' + f('email', 'email', 'email') + f('phone', 'tel', 'tel') + '</div>' + f('vat', 'text') + f('message', 'textarea') +
      '<input class="hp" id="b_website_url" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">' +
      '<p class="note">' + esc(T.privacy) + '</p><p class="err" id="bErr" role="alert"></p></div>' +
      '<div class="dfoot"><button class="btn" type="button" id="bSend"' + (ids.length ? '' : ' disabled style="opacity:.5"') + '>' + esc(T.send) + '</button></div>';
  }
  bk.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) { keep(); close(); return; }
    var s = e.target.closest('[data-st]');
    if (s) { keep(); var l = B.lines[s.dataset.id]; l.q += +s.dataset.st; if (l.q < 1) delete B.lines[s.dataset.id]; save(); badge(); showBasket(); return; }
    var r = e.target.closest('[data-rm]'); if (r) { keep(); delete B.lines[r.dataset.rm]; save(); badge(); showBasket(); return; }
    if (e.target.id === 'bSend') send(e.target);
  });
  bk.addEventListener('change', function (e) { if (e.target.dataset.q) { keep(); B.lines[e.target.dataset.q].q = Math.max(1, +e.target.value || 1); save(); badge(); showBasket(); } });
  function send(btn) {
    keep();
    var err = $('bErr'), fm = B.form;
    if (!(fm.name || '').trim() || !/^\S+@\S+\.\S+$/.test((fm.email || '').trim())) { err.textContent = T.need_name_email; return; }
    btn.disabled = true; btn.textContent = T.sending;
    var lines = Object.keys(B.lines).map(function (id) { return { id: +id, qty: B.lines[id].q }; });
    fetch(C.urls.request, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify({ name: fm.name, company: fm.company, vat: fm.vat, email: fm.email, phone: fm.phone, message: fm.message, delivery: B.delivery, lines: lines, page: location.pathname + location.search, website_url: ($('b_website_url') || {}).value || '' }) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) { err.textContent = res.j.errors ? Object.values(res.j.errors)[0][0] : (res.j.message || T.error); btn.disabled = false; btn.textContent = T.send; return; }
        var first = (fm.name || '').trim().split(' ')[0];
        B.lines = {}; B.form.message = ''; save(); badge(); showBasket(first);
      }).catch(function () { err.textContent = T.error; btn.disabled = false; btn.textContent = T.send; });
  }
  badge();

  // ---------- slideshow ----------
  var slides = Array.prototype.slice.call(document.querySelectorAll('.slide')), dots = $('dots');
  if (slides.length > 1 && dots) {
    var i = 0, t = null, paused = matchMedia('(prefers-reduced-motion: reduce)').matches, pz = $('pause');
    dots.innerHTML = slides.map(function (s, k) { return '<button type="button" aria-label="' + esc(s.getAttribute('aria-label')) + '" aria-current="' + (k === 0) + '"></button>'; }).join('');
    var go = function (k) { slides[i].classList.remove('on'); i = (k + slides.length) % slides.length; slides[i].classList.add('on'); Array.prototype.forEach.call(dots.children, function (d, n) { d.setAttribute('aria-current', n === i); }); };
    var play = function () { clearInterval(t); if (!paused) t = setInterval(function () { go(i + 1); }, 6000); };
    dots.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { go(Array.prototype.indexOf.call(dots.children, b)); play(); } });
    $('prev').addEventListener('click', function () { go(i - 1); play(); });
    $('next').addEventListener('click', function () { go(i + 1); play(); });
    pz.addEventListener('click', function () { paused = !paused; pz.textContent = paused ? '▶' : '❚❚'; pz.setAttribute('aria-label', paused ? pz.dataset.play : pz.dataset.pause); play(); });
    $('slider').addEventListener('mouseenter', function () { clearInterval(t); });
    $('slider').addEventListener('mouseleave', play);
    if (paused) pz.textContent = '▶';
    play();
  }
})();
