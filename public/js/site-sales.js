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
  // Guests: kept in this browser, sent as a quote request. Signed-in customers: kept on the
  // server at their own prices and ordered on invoice; products without a price stay a quote.
  var scrim = $('scrim'), bk = $('bk'), added = $('added'), openD = null;
  var CU = C.customer || null, A = C.acct || {}, SV = null;
  var B = { lines: {}, delivery: 'pick', form: {} };
  try { var saved = JSON.parse(localStorage.getItem(STORE) || 'null'); if (saved && saved.lines) B = Object.assign(B, saved); } catch (e) {}
  if (CU) { B.form.name = B.form.name || CU.name; B.form.email = B.form.email || CU.email; B.form.company = B.form.company || CU.company || ''; }
  function save() { try { localStorage.setItem(STORE, JSON.stringify(B)); } catch (e) {} }
  function api(url, method, body) {
    return fetch(url, { method: method || 'GET', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
  }
  function count() {
    var n = 0; Object.keys(B.lines).forEach(function (k) { n += B.lines[k].q; });
    if (SV) SV.lines.forEach(function (l) { n += l.qty; });
    return n;
  }
  function badge(bump) { var n = $('basketN'); if (n) n.textContent = count(); var b = $('basketBtn'); if (bump && b) { b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); } }
  function open(d) { openD = d; d.classList.add('on'); scrim.classList.add('on'); }
  function close() { if (openD) openD.classList.remove('on'); if (filters) filters.classList.remove('on'); scrim.classList.remove('on'); openD = null; }
  scrim.addEventListener('click', function () { keep(); close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); added.hidden = true; megaOpen(false); } });
  function serverQty(id) { var l = SV && SV.lines.find(function (x) { return x.id === +id; }); return l ? l.qty : 0; }
  function setServer(id, qty) { return api(A.set, 'POST', { id: +id, qty: qty }).then(function (r) { if (r.j && r.j.lines) SV = r.j; badge(); return r; }); }
  function add(btn, q) {
    var id = btn.dataset.add, priced = +btn.dataset.price > 0;
    q = q || 1;
    if (window.cluk) window.cluk.track('add', { section: 'sales', item_id: +id, label: btn.dataset.name, meta: { qty: q } });
    if (CU && priced) { setServer(id, serverQty(id) + q); }
    else { var l = B.lines[id]; B.lines[id] = { q: (l ? l.q : 0) + q, name: btn.dataset.name, price: +btn.dataset.price || 0, code: btn.dataset.code }; save(); }
    badge(true);
    if (!priced) { showBasket(); open(bk); return; }
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
  if ($('basketBtn')) $('basketBtn').addEventListener('click', function () { openBasket(); });
  // Clukie (chat helper) puts products in the basket through this.
  window.clukSales = { add: function (a) { add({ dataset: { add: String(a.id), name: a.name, price: String(a.price || 0), code: a.code } }, a.qty || 1); } };
  function openBasket() { if (CU) { api(A.show).then(function (r) { if (r.ok) SV = r.j; badge(); showBasket(); }); } showBasket(); open(bk); }

  var FIELDS = ['name', 'company', 'vat', 'email', 'phone', 'message', 'po'];
  function keep() { FIELDS.forEach(function (k) { var el = $('b_' + k); if (el) B.form[k] = el.value; }); var d = bk.querySelector('input[name=dl]:checked'); if (d) B.delivery = d.value; save(); }
  var check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="m5 12 5 5 9-10"/></svg>';
  function done(title, hello, text) { bk.innerHTML = '<div class="dhead"><h2 id="bkTitle">' + esc(title) + '</h2><button class="x" type="button" data-close aria-label="' + esc(T.close) + '">✕</button></div><div class="dbody"><div class="tick">' + check + '</div><h3 style="margin:0;font-size:22px">' + esc(hello) + '</h3><p style="margin:0">' + esc(text) + '</p></div>'; }
  function qtyBox(id, q, kind) { return '<div class="qty"><button type="button" data-st="-1" data-id="' + id + '" data-k="' + kind + '" aria-label="' + esc(T.one_less) + '">−</button><input type="number" min="1" data-q="' + id + '" data-k="' + kind + '" value="' + q + '" aria-label="' + esc(T.quantity) + '"><button type="button" data-st="1" data-id="' + id + '" data-k="' + kind + '" aria-label="' + esc(T.one_more) + '">+</button></div>'; }
  var f = function (k, label, type, ac) { return '<div class="field"><label for="b_' + k + '">' + esc(label) + '</label>' + (type === 'textarea' ? '<textarea id="b_' + k + '" placeholder="' + esc(T.message_hint) + '">' + esc(B.form[k] || '') + '</textarea>' : '<input id="b_' + k + '" type="' + type + '" value="' + esc(B.form[k] || '') + '"' + (ac ? ' autocomplete="' + ac + '"' : '') + '>') + '</div>'; };
  function localLines() {
    var ids = Object.keys(B.lines), total = 0;
    var html = ids.map(function (id) {
      var l = B.lines[id]; total += l.price * l.q;
      return '<div class="bline"><div><div class="nm">' + esc(l.name) + '</div><div class="pp num">' + esc(l.code) + ' · ' + (l.price ? eur(l.price) : esc(T.on_request)) + '</div><button class="rm" type="button" data-rm="' + id + '" data-k="l">' + esc(T.remove) + '</button></div>' +
        qtyBox(id, l.q, 'l') + '<div class="num" style="font-weight:700;min-width:72px;text-align:right">' + (l.price ? eur(l.price * l.q) : '–') + '</div></div>';
    }).join('');
    return { ids: ids, html: html, total: total };
  }
  function quoteForm(btnId) {
    return '<div class="field"><span class="fl">' + esc(T.delivery) + '</span><div class="seg"><label><input type="radio" name="dl" value="pick"' + (B.delivery !== 'ship' ? ' checked' : '') + '>' + esc(T.pick) + '</label><label><input type="radio" name="dl" value="ship"' + (B.delivery === 'ship' ? ' checked' : '') + '>' + esc(T.ship) + '</label></div></div>' +
      (CU ? '' : '<div class="two">' + f('name', T.name, 'text', 'name') + f('company', T.company, 'text', 'organization') + '</div><div class="two">' + f('email', T.email, 'email', 'email') + f('phone', T.phone, 'tel', 'tel') + '</div>' + f('vat', T.vat, 'text')) +
      f('message', T.message, 'textarea') + '<input class="hp" id="b_website_url" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">';
  }
  function showBasket() {
    var L = localLines(), head = '<div class="dhead"><h2 id="bkTitle">' + esc(T.basket) + '</h2><button class="x" type="button" data-close aria-label="' + esc(T.close) + '">✕</button></div>';
    if (!CU) {
      bk.innerHTML = head + '<div class="dbody">' +
        (L.ids.length ? L.html + '<div class="sum num"><span>' + esc(T.total) + '</span><span>' + eur(L.total) + '</span></div>' : '<p class="note">' + esc(T.empty) + '</p>') +
        '<p class="note-box">' + esc(T.quote_note) + ' <a href="' + esc(A.login) + '" style="text-decoration:underline;font-weight:700">' + esc(T.log_in) + '</a></p>' +
        quoteForm() + '<p class="note">' + esc(T.privacy) + '</p><p class="err" id="bErr" role="alert"></p></div>' +
        '<div class="dfoot"><button class="btn" type="button" id="bSend"' + (L.ids.length ? '' : ' disabled style="opacity:.5"') + '>' + esc(T.send) + '</button></div>';
      return;
    }
    var sl = SV ? SV.lines : [];
    var server = sl.map(function (l) {
      var off = l.discount_pct ? ' · <s>' + eur(l.list_price_cents) + '</s> ' + esc(tr('off', { p: String(l.discount_pct).replace('.', C.locale === 'en' ? '.' : ',') })) : '';
      return '<div class="bline"><div><div class="nm">' + esc(l.name) + '</div><div class="pp num">' + esc(l.number) + ' · ' + eur(l.price_cents) + off + ' · ' + esc(l.in_stock > 0 ? tr('in_stock', { n: l.in_stock }) : T.lead) + '</div><button class="rm" type="button" data-rm="' + l.id + '" data-k="s">' + esc(T.remove) + '</button></div>' +
        qtyBox(l.id, l.qty, 's') + '<div class="num" style="font-weight:700;min-width:72px;text-align:right">' + eur(l.amount_cents) + '</div></div>';
    }).join('');
    var vatP = SV ? String(SV.vat_pct).replace('.', C.locale === 'en' ? '.' : ',') : '';
    bk.innerHTML = head + '<div class="dbody"><p class="note">' + esc(tr('signed_in', { name: CU.name })) + '</p>' +
      (sl.length ? server + '<div class="sum num" style="font-size:15px;font-weight:600"><span>' + esc(T.net) + '</span><span>' + eur(SV.net_cents) + '</span></div>' +
        '<div class="sum num" style="font-size:15px;font-weight:500;padding:0"><span>' + esc(tr('vat', { p: vatP })) + '</span><span>' + eur(SV.vat_cents) + '</span></div>' +
        '<div class="sum num"><span>' + esc(T.gross) + '</span><span>' + eur(SV.gross_cents) + '</span></div>' +
        f('po', T.po, 'text') + '<p class="note">' + esc(T.order_note) + '</p><p class="err" id="oErr" role="alert"></p><button class="btn" type="button" id="bOrder">' + esc(T.order) + '</button>'
        : (L.ids.length ? '' : '<p class="note">' + (SV ? esc(T.empty) : '…') + '</p>')) +
      (L.ids.length ? '<h3 style="margin:18px 0 0;font-size:16px">' + esc(T.no_price) + '</h3>' + L.html + quoteForm() + '<p class="err" id="bErr" role="alert"></p><button class="btn ghost" type="button" id="bSend">' + esc(T.send) + '</button>' : '') +
      '</div>';
  }
  bk.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) { keep(); close(); return; }
    var s = e.target.closest('[data-st]');
    if (s) {
      keep();
      if (s.dataset.k === 's') { setServer(s.dataset.id, Math.max(0, serverQty(s.dataset.id) + (+s.dataset.st))).then(showBasket); return; }
      var l = B.lines[s.dataset.id]; l.q += +s.dataset.st; if (l.q < 1) delete B.lines[s.dataset.id]; save(); badge(); showBasket(); return;
    }
    var r = e.target.closest('[data-rm]');
    if (r) { keep(); if (r.dataset.k === 's') { setServer(r.dataset.rm, 0).then(showBasket); return; } delete B.lines[r.dataset.rm]; save(); badge(); showBasket(); return; }
    if (e.target.id === 'bSend') send(e.target);
    if (e.target.id === 'bOrder') order(e.target);
  });
  bk.addEventListener('change', function (e) {
    if (!e.target.dataset.q) return;
    keep();
    var v = Math.max(1, +e.target.value || 1);
    if (e.target.dataset.k === 's') { setServer(e.target.dataset.q, v).then(showBasket); return; }
    B.lines[e.target.dataset.q].q = v; save(); badge(); showBasket();
  });
  function send(btn) {
    keep();
    var err = $('bErr'), fm = B.form;
    if (!(fm.name || '').trim() || !/^\S+@\S+\.\S+$/.test((fm.email || '').trim())) { err.textContent = T.need_name_email; return; }
    btn.disabled = true; btn.textContent = T.sending;
    var lines = Object.keys(B.lines).map(function (id) { return { id: +id, qty: B.lines[id].q }; });
    api(C.urls.request, 'POST', { name: fm.name, company: fm.company, vat: fm.vat, email: fm.email, phone: fm.phone, message: fm.message, delivery: B.delivery, lines: lines, page: location.pathname + location.search, website_url: ($('b_website_url') || {}).value || '' })
      .then(function (res) {
        if (!res.ok) { err.textContent = res.j.errors ? Object.values(res.j.errors)[0][0] : (res.j.message || T.error); btn.disabled = false; btn.textContent = T.send; return; }
        B.lines = {}; B.form.message = ''; save(); badge(); done(T.sent_title, tr('sent_hello', { name: (fm.name || '').trim().split(' ')[0] }), T.sent_text);
      }).catch(function () { err.textContent = T.error; btn.disabled = false; btn.textContent = T.send; });
  }
  function order(btn) {
    keep();
    var err = $('oErr');
    btn.disabled = true; btn.textContent = T.ordering;
    api(A.order, 'POST', { po_number: B.form.po || '', message: B.form.message || '' }).then(function (res) {
      if (!res.ok) { err.textContent = res.j.errors ? Object.values(res.j.errors)[0][0] : (res.j.message || T.error); btn.disabled = false; btn.textContent = T.order; return; }
      SV = null; B.form.po = ''; B.form.message = ''; save(); badge();
      done(T.ordered_title, tr('sent_hello', { name: CU.first }), tr('ordered_text', { number: res.j.number }));
    }).catch(function () { err.textContent = T.error; btn.disabled = false; btn.textContent = T.order; });
  }
  // signed in: move the priced lines kept in this browser to the account basket
  if (CU) {
    var priced = Object.keys(B.lines).filter(function (id) { return B.lines[id].price > 0; });
    api(A.merge, 'POST', { lines: priced.map(function (id) { return { id: +id, qty: B.lines[id].q }; }) }).then(function (r) {
      if (!r.ok) return;
      SV = r.j; priced.forEach(function (id) { delete B.lines[id]; }); save(); badge();
      if (new URLSearchParams(location.search).get('basket') !== null) openBasket();
    }).catch(function () {});
  } else if (new URLSearchParams(location.search).get('basket') !== null) openBasket();
  badge();

  // ---------- slideshow ----------
  var slides = Array.prototype.slice.call(document.querySelectorAll('.slide')), dots = $('dots');
  if (slides.length > 1 && dots) {
    var i = 0, t = null, paused = matchMedia('(prefers-reduced-motion: reduce)').matches, pz = $('pause');
    dots.innerHTML = slides.map(function (s, k) { return '<button type="button" aria-label="' + esc(s.getAttribute('aria-label')) + '" aria-current="' + (k === 0) + '"></button>'; }).join('');
    var ms = Math.max(3, parseInt($('slider').dataset.seconds, 10) || 6) * 1000;
    var vid = function (k, on) { var v = slides[k].querySelector('video'); if (!v) return; if (on && !paused) { v.muted = true; var p = v.play(); if (p && p.catch) p.catch(function () {}); } else v.pause(); };
    var go = function (k) { vid(i, false); slides[i].classList.remove('on'); i = (k + slides.length) % slides.length; slides[i].classList.add('on'); vid(i, true); Array.prototype.forEach.call(dots.children, function (d, n) { d.setAttribute('aria-current', n === i); }); };
    var play = function () { clearInterval(t); if (!paused) t = setInterval(function () { go(i + 1); }, ms); };
    dots.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { go(Array.prototype.indexOf.call(dots.children, b)); play(); } });
    $('prev').addEventListener('click', function () { go(i - 1); play(); });
    $('next').addEventListener('click', function () { go(i + 1); play(); });
    pz.addEventListener('click', function () { paused = !paused; pz.textContent = paused ? '▶' : '❚❚'; pz.setAttribute('aria-label', paused ? pz.dataset.play : pz.dataset.pause); vid(i, !paused); play(); });
    $('slider').addEventListener('mouseenter', function () { clearInterval(t); });
    $('slider').addEventListener('mouseleave', play);
    if (paused) { pz.textContent = '▶'; vid(0, false); }
    play();
  }
})();
