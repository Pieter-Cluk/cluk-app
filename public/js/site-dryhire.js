// CLUK website · dry-hire catalogue: instant search, categories, filters, product panel and request basket.
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var D = JSON.parse($('dh-data').textContent), C = JSON.parse($('dh-cfg').textContent), T = C.t;
  var CHUNK = 120, STORE = 'cluk-dryhire-request';

  // ---------- lookups ----------
  var catById = {}, subById = {}, optById = {}, attrById = {}, tagById = {}, catOrder = {}, subOrder = {};
  D.cats.forEach(function (c, i) { catById[c.id] = c; catOrder[c.id] = i; });
  D.subs.forEach(function (s, i) { subById[s.id] = s; subOrder[s.id] = i; });
  D.attrs.forEach(function (a) { attrById[a.id] = a; a.o.forEach(function (o) { optById[o.id] = { a: a.id, l: o.l }; }); });
  D.tags.forEach(function (t) { tagById[t.id] = t; });
  var fold = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var compact = function (s) { return fold(s).replace(/[^a-z0-9]/g, ''); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var tr = function (k, r) { var s = T[k] || k; Object.keys(r || {}).forEach(function (x) { s = s.split(':' + x).join(r[x]); }); return s; };
  var nfLocale = C.locale === 'en' ? 'en-GB' : C.locale + '-BE';
  var eur = function (c) { return '€ ' + (c / 100).toLocaleString(nfLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var fnum = function (f) { return String(f).replace('.', C.locale === 'en' ? '.' : ','); };
  var ITEMS = D.items, byId = {};
  ITEMS.forEach(function (it) {
    byId[it.id] = it;
    var sub = subById[it.s] || {}, cat = catById[it.c] || {};
    var opts = (it.o || []).map(function (o) { return optById[o] ? optById[o].l : ''; }).join(' ');
    it.f = { type: compact(it.t), name: fold(it.nm), brand: fold(it.b), sub: fold(sub.name), cat: fold(cat.name), no: fold(it.n) };
    it.all = [fold(it.nm), fold(it.b), fold(it.t), fold(sub.name), fold(cat.name), fold(it.n), fold(it.w), fold(opts)].join(' ');
    it.allC = compact(it.nm + ' ' + it.b + ' ' + it.t + ' ' + it.n + ' ' + opts);
  });

  // ---------- search ----------
  var reEsc = function (s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); };
  function tokens(q) { return fold(q).trim().split(/\s+/).filter(Boolean); }
  function score(it, toks) {
    var s = 0;
    for (var i = 0; i < toks.length; i++) {
      var t = toks[i], tc = t.replace(/[^a-z0-9]/g, ''), v = 0;
      if (tc && it.f.type === tc) v = 100;
      else if (tc.length > 1 && it.f.type.indexOf(tc) === 0) v = 70;
      else if (it.f.brand && (it.f.brand.split(/\s+/).some(function (w) { return w.indexOf(t) === 0; }) || compact(it.b).indexOf(tc) === 0) && tc) v = 50;
      if (!v && (it.f.sub.indexOf(t) >= 0 || it.f.cat.indexOf(t) >= 0)) v = 45;
      if (!v && new RegExp('(^|[^a-z0-9])' + reEsc(t)).test(it.f.name)) v = 40;
      if (!v && tc.length > 1 && it.allC.indexOf(tc) >= 0) v = 30;
      if (!v && t.length > 1 && it.all.indexOf(t) >= 0) v = 15;
      if (!v && t.length > 3 && t.slice(-1) === 's' && it.all.indexOf(t.slice(0, -1)) >= 0) v = 12;
      if (!v) return 0;
      s += v;
    }
    return s;
  }
  function hl(text, toks) {
    var out = esc(text);
    toks.filter(function (t) { return t.length > 1; }).forEach(function (t) {
      var re = new RegExp('(' + reEsc(esc(t)) + ')', 'ig');
      out = out.replace(/(^|>)([^<]*)(?=<|$)/g, function (m, a, b) { return a + b.replace(re, '<mark>$1</mark>'); });
    });
    return out;
  }

  // ---------- state ----------
  var S = { q: '', cat: null, subs: new Set(), brands: new Set(), opts: new Set(), tags: new Set(), sort: 'rel', view: 'grid', brandsAll: false, basket: new Map(), out: '', back: '' };
  (function load() {
    try {
      var b = JSON.parse(localStorage.getItem(STORE) || '{}');
      (b.lines || []).forEach(function (l) { if (byId[l[0]]) S.basket.set(l[0], l[1]); });
      S.out = b.out || ''; S.back = b.back || '';
    } catch (e) {}
    var today = new Date().toISOString().slice(0, 10);
    if (!S.out || S.out < today) {
      var d = new Date(); d.setDate(d.getDate() + ((5 - d.getDay() + 7) % 7 || 7));
      var e2 = new Date(d); e2.setDate(d.getDate() + 3);
      S.out = d.toISOString().slice(0, 10); S.back = e2.toISOString().slice(0, 10);
    }
    var p = new URLSearchParams(location.search);
    if (p.get('q')) { S.q = p.get('q'); $('q').value = S.q; }
    if (p.get('cat')) { var c = D.cats.find(function (x) { return x.slug === p.get('cat'); }); if (c) S.cat = c.id; }
    if (p.get('add') && byId[+p.get('add')]) { S.basket.set(+p.get('add'), (S.basket.get(+p.get('add')) || 0) + 1); }
  })();
  function save() {
    try { localStorage.setItem(STORE, JSON.stringify({ lines: Array.from(S.basket.entries()), out: S.out, back: S.back })); } catch (e) {}
  }
  function syncUrl() {
    var p = new URLSearchParams(location.search);
    ['q', 'cat', 'add'].forEach(function (k) { p.delete(k); });
    if (S.q) p.set('q', S.q);
    if (S.cat) p.set('cat', catById[S.cat].slug);
    var u = location.pathname + (p.toString() ? '?' + p.toString() : '');
    try { history.replaceState(null, '', u); } catch (e) {}
  }

  function filtered() {
    var toks = tokens(S.q);
    var base = ITEMS.filter(function (it) { it._s = toks.length ? score(it, toks) : 1; return it._s > 0; });
    var list = S.cat ? base.filter(function (i) { return i.c === S.cat; }) : base;
    var beforeSub = list;
    if (S.subs.size) list = list.filter(function (i) { return S.subs.has(i.s); });
    var beforeOpt = list;
    if (S.opts.size) {
      var want = {};
      S.opts.forEach(function (o) { var a = optById[o].a; (want[a] = want[a] || []).push(o); });
      list = list.filter(function (i) { return Object.keys(want).every(function (a) { return (i.o || []).some(function (o) { return want[a].indexOf(o) >= 0; }); }); });
    }
    if (S.tags.size) list = list.filter(function (i) { return (i.g || []).some(function (g) { return S.tags.has(g); }); });
    var beforeBrand = list;
    if (S.brands.size) list = list.filter(function (i) { return S.brands.has(i.b); });
    var byCat = function (a, b) { return (catOrder[a.c] - catOrder[b.c]) || (subOrder[a.s] - subOrder[b.s]) || a.nm.localeCompare(b.nm); };
    var price = function (i) { return i.p || 1e12; };
    var sorts = {
      rel: function (a, b) { return toks.length ? (b._s - a._s) || a.nm.localeCompare(b.nm) : byCat(a, b); },
      pa: function (a, b) { return price(a) - price(b); },
      pd: function (a, b) { return (b.p || 0) - (a.p || 0); },
      az: function (a, b) { return a.nm.localeCompare(b.nm); }
    };
    list = list.slice().sort(sorts[S.sort]);
    return { list: list, toks: toks, base: base, beforeSub: beforeSub, beforeOpt: beforeOpt, beforeBrand: beforeBrand };
  }

  // ---------- facets ----------
  function count(list, fn) { var m = new Map(); list.forEach(function (i) { [].concat(fn(i)).forEach(function (k) { if (k != null && k !== '') m.set(k, (m.get(k) || 0) + 1); }); }); return m; }
  function opt(kind, val, label, n, on) { return '<label class="opt"><input type="checkbox" data-' + kind + '="' + esc(val) + '"' + (on ? ' checked' : '') + '>' + esc(label) + '<span class="c num">' + n + '</span></label>'; }
  function renderFacets(r) {
    var subs = count(r.beforeSub, function (i) { return i.s; });
    var subIds = Array.from(subs.keys()).sort(function (a, b) { return subOrder[a] - subOrder[b]; });
    $('fSubs').innerHTML = S.cat || S.q ? '<h3 class="eyebrow">' + esc(T.type || 'Type') + '</h3>' + subIds.map(function (s) { return opt('sub', s, subById[s].name, subs.get(s), S.subs.has(s)); }).join('') : '';
    // attributes that apply to the subcategories in view
    var attrIds = new Set();
    subIds.forEach(function (s) { (subById[s].a || []).forEach(function (a) { attrIds.add(a); }); });
    var oc = count(r.beforeOpt, function (i) { return i.o || []; });
    var html = '';
    if (S.cat || S.q || S.subs.size) {
      D.attrs.forEach(function (a) {
        if (!attrIds.has(a.id)) return;
        var rows = a.o.filter(function (o) { return oc.get(o.id) || S.opts.has(o.id); });
        if (!rows.length) return;
        html += '<div><h3 class="eyebrow">' + esc(a.name) + '</h3>' + rows.map(function (o) { return opt('opt', o.id, o.l, oc.get(o.id) || 0, S.opts.has(o.id)); }).join('') + '</div>';
      });
    }
    $('fAttrs').innerHTML = html;
    var tc = count(r.beforeOpt, function (i) { return i.g || []; });
    $('fTags').innerHTML = tc.size ? '<h3 class="eyebrow">' + esc(T.tags || '') + '</h3>' + D.tags.filter(function (t) { return tc.get(t.id); }).map(function (t) { return opt('tag', t.id, t.name, tc.get(t.id), S.tags.has(t.id)); }).join('') : '';
    var bc = count(r.beforeBrand, function (i) { return i.b; });
    var br = Array.from(bc.entries()).sort(function (a, b) { return b[1] - a[1] || a[0].localeCompare(b[0]); });
    var n = br.length; if (!S.brandsAll) br = br.slice(0, 10);
    S.brands.forEach(function (b) { if (!br.some(function (x) { return x[0] === b; })) br.push([b, 0]); });
    $('fBrands').innerHTML = br.map(function (x) { return opt('brand', x[0], x[0], x[1], S.brands.has(x[0])); }).join('') +
      (n > 10 ? '<button class="more" type="button" id="moreBrands">' + esc(S.brandsAll ? (T.show_fewer || '−') : (T.show_all || '+').replace(':n', n)) + '</button>' : '');
  }

  // ---------- cards ----------
  var plus = '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>';
  var check = '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg>';
  function photoUrl(it) { return C.urls.photo.replace('__ID__', it.id).replace('__V__', it.ph); }
  function visual(it, toks) {
    if (it.ph) return '<div class="photo"><img src="' + photoUrl(it) + '" alt="" loading="lazy" decoding="async"></div>';
    var cat = catById[it.c] || {};
    return '<div class="plate"><div class="top"><span class="code">' + esc(cat.code || '') + '</span><span class="brand">' + esc(it.b || (subById[it.s] || {}).name || '') + '</span></div><div class="type">' + hl(it.t || it.nm, toks) + '</div></div>';
  }
  function priceHtml(it) { return it.p ? '<span class="price num">' + eur(it.p) + ' <small>' + esc(T.per_day) + '</small></span>' : '<span class="price" style="font-weight:500;color:var(--muted)">' + esc(T.on_request) + '</span>'; }
  function addBtn(it) { var on = S.basket.has(it.id); return '<button class="add' + (on ? ' in' : '') + '" type="button" data-add="' + it.id + '" aria-label="' + esc(on ? T.in_request : T.add) + '">' + (on ? check : plus) + '</button>'; }
  function card(it, toks) {
    return '<article class="card" data-id="' + it.id + '" tabindex="0" aria-label="' + esc(it.nm) + '">' + addBtn(it) + visual(it, toks) +
      (it.k === 'p' ? '<span class="badge">' + esc(T.system) + '</span>' : '') +
      '<div class="body"><h3>' + hl(it.nm, toks) + '</h3><div class="sub">' + esc((subById[it.s] || {}).name || '') + '</div>' +
      '<div class="meta">' + priceHtml(it) + (it.q ? '<span class="stock num">' + tr('in_stock', { n: '<b>' + it.q + '</b>' }) + '</span>' : '') + '</div></div></article>';
  }
  function lrow(it, toks) {
    return '<div class="lrow" data-id="' + it.id + '" tabindex="0" role="button" aria-label="' + esc(it.nm) + '"><span class="no">' + esc(it.n) + '</span><span><span class="nm">' + hl(it.nm, toks) + '</span><br><span class="sub">' + esc((subById[it.s] || {}).name || '') + '</span></span><span class="sub sub2">' + esc(it.b || '') + '</span><span class="stock num">' + (it.q ? tr('in_stock', { n: '<b>' + it.q + '</b>' }) : '') + '</span>' + priceHtml(it) + addBtn(it) + '</div>';
  }

  // ---------- results, rendered in chunks while scrolling ----------
  var queue = [], target = null;
  function container() { var el = document.createElement('div'); el.className = S.view === 'grid' ? 'grid' : 'list'; return el; }
  function renderMore() {
    var n = 0, res = $('results'), toks = tokens(S.q);
    while (queue.length && n < CHUNK) {
      var g = queue[0];
      if (!g.started) {
        if (g.head) res.insertAdjacentHTML('beforeend', g.head);
        target = container(); res.appendChild(target); g.started = true;
      }
      var take = g.items.splice(0, CHUNK - n);
      target.insertAdjacentHTML('beforeend', take.map(function (i) { return S.view === 'grid' ? card(i, toks) : lrow(i, toks); }).join(''));
      n += take.length;
      if (!g.items.length) queue.shift();
    }
  }
  function render() {
    var r = filtered();
    renderFacets(r);
    // category chips
    var cc = count(r.base, function (i) { return i.c; });
    $('cats').innerHTML = '<button class="chip" type="button" data-cat="" aria-pressed="' + !S.cat + '">' + esc(T.all || 'All') + ' <span class="c num">' + r.base.length + '</span></button>' +
      D.cats.map(function (c) { return '<button class="chip" type="button" data-cat="' + c.id + '" aria-pressed="' + (S.cat === c.id) + '"' + (cc.get(c.id) ? '' : ' disabled style="opacity:.4"') + '>' + esc(c.name) + ' <span class="c num">' + (cc.get(c.id) || 0) + '</span></button>'; }).join('');
    $('count').innerHTML = S.q ? tr('results', { n: r.list.length, q: esc(S.q) }) : tr('products', { n: r.list.length.toLocaleString(nfLocale) });
    var chips = [];
    if (S.cat) chips.push(['cat', S.cat, catById[S.cat].name]);
    S.subs.forEach(function (s) { chips.push(['sub', s, subById[s].name]); });
    S.opts.forEach(function (o) { chips.push(['opt', o, optById[o].l]); });
    S.tags.forEach(function (t) { chips.push(['tag', t, tagById[t].name]); });
    S.brands.forEach(function (b) { chips.push(['brand', b, b]); });
    $('active').innerHTML = chips.map(function (c) { return '<button type="button" data-rm="' + c[0] + '" data-v="' + esc(c[1]) + '">' + esc(c[2]) + ' ✕</button>'; }).join('') + (chips.length ? '<button type="button" data-rm="all">' + esc(T.clear_all) + '</button>' : '');
    $('active').hidden = !chips.length;
    $('clear').hidden = !S.q; $('kbd').hidden = !!S.q;

    $('results').innerHTML = ''; queue = [];
    if (!r.list.length) {
      $('results').innerHTML = '<div class="empty"><h3>' + esc(tr('nothing', { q: S.q ? '“' + S.q + '”' : '' })) + '</h3><p>' + esc(T.nothing_text) + '</p><p><button class="btn" type="button" id="askUs">' + esc(T.ask_else) + '</button></p></div>';
    } else if (!r.toks.length && S.sort === 'rel') {
      // browsing: grouped per category and type
      var lastCat = null;
      var groups = [];
      r.list.forEach(function (it) {
        var g = groups[groups.length - 1];
        if (!g || g.s !== it.s) { g = { s: it.s, c: it.c, items: [] }; groups.push(g); }
        g.items.push(it);
      });
      groups.forEach(function (g) {
        var head = '';
        if (g.c !== lastCat) {
          var n = r.list.filter(function (i) { return i.c === g.c; }).length;
          head += '<div class="sec-head" id="cat-' + esc(catById[g.c].slug) + '"><h2>' + esc(catById[g.c].name) + '</h2><span class="eyebrow num">' + n + ' ' + esc(T.products_word || '') + '</span></div>';
          lastCat = g.c;
        }
        head += '<div class="subhead"><h3>' + esc(subById[g.s].name) + '</h3><span class="num">' + g.items.length + '</span></div>';
        queue.push({ head: head, items: g.items.slice() });
      });
      renderMore();
    } else {
      queue.push({ head: '', items: r.list.slice() });
      renderMore();
    }
    syncUrl();
  }
  T.products_word = (T.products || '').replace(/<b>:n<\/b>\s*/, '');

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (es) { if (es[0].isIntersecting && queue.length) renderMore(); }, { rootMargin: '1200px' }).observe($('sentinel'));
  }
  window.addEventListener('scroll', function () { if (queue.length && $('sentinel').getBoundingClientRect().top < innerHeight + 1200) renderMore(); }, { passive: true });

  // ---------- drawers ----------
  var scrim = $('scrim'), openD = null, lastFocus = null;
  function open(d) { lastFocus = document.activeElement; openD = d; d.classList.add('on'); scrim.classList.add('on'); setTimeout(function () { var f = d.querySelector('button, input'); if (f) f.focus(); }, 60); }
  function close() { if (!openD) return; openD.classList.remove('on'); scrim.classList.remove('on'); $('aside').classList.remove('on'); openD = null; if (lastFocus && lastFocus.focus) lastFocus.focus(); }

  function showItem(id) {
    var it = byId[id]; if (!it) return;
    var q = S.basket.get(id) || 1;
    $('pd').innerHTML = '<div class="dhead"><span class="eyebrow" style="color:var(--muted)">' + esc((catById[it.c] || {}).name) + ' · ' + esc((subById[it.s] || {}).name) + '</span><button class="x" type="button" data-close aria-label="' + esc(T.close) + '">✕</button></div>' +
      '<div class="dbody" id="pdBody"><div class="gallery">' + visual(it, []).replace('class="plate"', 'class="plate big"') + '</div><div><h2 id="pdTitle" style="font:800 30px/1.05 var(--sans);letter-spacing:-.03em;margin:0 0 6px">' + esc(it.nm) + '</h2><span class="note" style="font-family:ui-monospace,Menlo,monospace">' + esc(it.n) + '</span></div><p class="note">…</p></div>' +
      '<div class="dfoot"><div class="row2"><div class="qty"><button type="button" data-step="-1" aria-label="' + esc(T.one_less) + '">−</button><input id="pdQty" type="number" min="1" value="' + q + '" aria-label="' + esc(T.quantity) + '"><button type="button" data-step="1" aria-label="' + esc(T.one_more) + '">+</button></div>' +
      '<button class="btn" type="button" data-addq="' + id + '" style="flex:1">' + esc(S.basket.has(id) ? T.update : T.add) + '</button></div></div>';
    if (openD !== $('pd')) open($('pd'));
    fetch(C.urls.data.replace('__ID__', id), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (!$('pdBody') || openD !== $('pd')) return;
      var g = d.photos.length ? '<div class="photo"><img id="pdPhoto" src="' + esc(d.photos[0]) + '" alt="' + esc(d.name) + '"></div>' +
        (d.photos.length > 1 ? '<div class="thumbs">' + d.photos.map(function (p, k) { return '<button type="button" data-ph="' + esc(p) + '" aria-current="' + (k === 0) + '"><img src="' + esc(p) + '" alt="" loading="lazy"></button>'; }).join('') + '</div>' : '') : visual(it, []).replace('class="plate"', 'class="plate big"');
      var specs = [[T.l_brand, d.brand], [T.l_pieces, d.qty ? '<b style="color:var(--green)">' + d.qty + '</b>' : null], [T.l_weight, d.weight], [T.l_size, d.size],
        [T.l_price, d.price ? '<b>' + eur(d.price) + '</b> ' + esc(T.per_day) + ' · ' + esc(T.price_note) : esc(T.on_request)]];
      $('pdBody').innerHTML = '<div class="gallery">' + g + '</div>' +
        '<div><h2 id="pdTitle" style="font:800 30px/1.05 var(--sans);letter-spacing:-.03em;margin:0 0 6px">' + esc(d.name) + '</h2><span class="note" style="font-family:ui-monospace,Menlo,monospace">' + esc(d.number) + '</span></div>' +
        (d.description ? '<p class="desc">' + esc(d.description) + '</p>' : '') +
        (d.options.length || d.tags.length ? '<div class="chips-s">' + d.options.map(function (o) { return '<span>' + esc(o[0]) + ': ' + esc(o[1]) + '</span>'; }).join('') + d.tags.map(function (t) { return '<span>' + esc(t) + '</span>'; }).join('') + '</div>' : '') +
        '<dl class="specs num">' + specs.filter(function (s) { return s[1]; }).map(function (s) { return '<dt>' + esc(s[0]) + '</dt><dd>' + (s[0] === T.l_brand || s[0] === T.l_weight || s[0] === T.l_size ? esc(s[1]) : s[1]) + '</dd>'; }).join('') + '</dl>' +
        (d.contents.length ? '<div><h3 class="eyebrow" style="color:var(--muted);margin:0 0 8px">' + esc(T.contents) + '</h3><ul style="margin:0;padding-left:18px">' + d.contents.map(function (c) { return '<li>' + c[0] + ' × ' + esc(c[1]) + '</li>'; }).join('') + '</ul></div>' : '') +
        (d.related.length ? '<div><h3 class="eyebrow" style="color:var(--muted);margin:0 0 8px">' + esc(T.often) + '</h3><div class="related">' + d.related.map(function (r) { return '<button type="button" data-show="' + r.id + '"><span>' + esc(r.name) + '</span><span class="num">' + (r.p ? eur(r.p) : esc(T.on_request)) + '</span></button>'; }).join('') + '</div></div>' : '') +
        '<p><a class="note" href="' + esc(d.url) + '" style="text-decoration:underline">' + esc(T.open_page) + ' →</a></p>';
    }).catch(function () {});
  }

  // ---------- basket ----------
  function factor(days) {
    var f = D.factor; if (days <= 1) return f.day;
    var w = Math.ceil(days / 7);
    return w === 1 ? f.week : w === 2 ? f.two_weeks : f.three_weeks + (w - 3) * f.per_extra_week;
  }
  function days() { if (!S.out || !S.back) return 0; var d = Math.round((new Date(S.back) - new Date(S.out)) / 864e5) + 1; return d > 0 ? d : 0; }
  function fab(bump) {
    var n = 0; S.basket.forEach(function (q) { n += q; });
    $('fab').hidden = !n; $('fabN').textContent = n;
    if (bump) { $('fab').classList.remove('bump'); void $('fab').offsetWidth; $('fab').classList.add('bump'); }
  }
  function add(id, q) {
    q = Math.max(1, Math.min(99999, q || ((S.basket.get(id) || 0) + 1)));
    S.basket.set(id, q); save(); fab(true);
    document.querySelectorAll('[data-add="' + id + '"]').forEach(function (b) { b.classList.add('in'); b.innerHTML = check; b.setAttribute('aria-label', T.in_request); });
  }
  var form = { name: '', company: '', vat: '', email: '', phone: '', message: '' };
  function keepForm() { Object.keys(form).forEach(function (k) { var el = $('f_' + k); if (el) form[k] = el.value; }); }
  function showBasket(sent) {
    if (sent) {
      $('bk').innerHTML = '<div class="dhead"><h2 id="bkTitle">' + esc(T.sent_title) + '</h2><button class="x" type="button" data-close aria-label="' + esc(T.close) + '">✕</button></div><div class="dbody"><div class="tick">' + check + '</div><h3 style="margin:0;font-size:22px">' + esc(tr('sent_hello', { name: sent })) + '</h3><p style="margin:0">' + esc(T.sent_text) + '</p></div>';
      return;
    }
    var d = days(), f = d ? factor(d) : 0, perDay = 0, lines = [];
    S.basket.forEach(function (q, id) { var it = byId[id]; if (it) { lines.push([it, q]); perDay += it.p * q; } });
    var field = function (k, label, type, extra) { return '<div class="field"><label for="f_' + k + '">' + esc(label) + '</label>' + (type === 'textarea' ? '<textarea id="f_' + k + '" placeholder="' + esc(T.message_hint) + '">' + esc(form[k]) + '</textarea>' : '<input id="f_' + k + '" type="' + type + '" value="' + esc(form[k]) + '" ' + (extra || '') + '>') + '</div>'; };
    $('bk').innerHTML = '<div class="dhead"><h2 id="bkTitle">' + esc(T.your_request) + '</h2><button class="x" type="button" data-close aria-label="' + esc(T.close) + '">✕</button></div>' +
      '<div class="dbody">' +
      '<div class="dates"><div class="field"><label for="dOut">' + esc(T.first_day) + '</label><input id="dOut" type="date" value="' + S.out + '" min="' + new Date().toISOString().slice(0, 10) + '"></div><div class="field"><label for="dBack">' + esc(T.last_day) + '</label><input id="dBack" type="date" value="' + S.back + '" min="' + (S.out || '') + '"></div></div>' +
      '<div>' + (lines.length ? lines.map(function (l) {
        var it = l[0], q = l[1];
        return '<div class="bline"><div><div class="nm">' + esc(it.nm) + '</div><div class="pp num">' + (it.p ? eur(it.p) + ' ' + esc(T.per_day) : esc(T.on_request)) + '</div><button class="rm" type="button" data-rmb="' + it.id + '">' + esc(T.remove) + '</button></div>' +
          '<div class="qty"><button type="button" data-bstep="-1" data-id="' + it.id + '" aria-label="' + esc(T.one_less) + '">−</button><input type="number" data-bq="' + it.id + '" value="' + q + '" min="1" aria-label="' + esc(T.quantity) + '"><button type="button" data-bstep="1" data-id="' + it.id + '" aria-label="' + esc(T.one_more) + '">+</button></div>' +
          '<div class="num" style="font-weight:700;min-width:70px;text-align:right">' + (it.p ? eur(it.p * q) : '–') + '</div></div>';
      }).join('') : '<p class="note">' + esc(T.empty) + '</p>') + '</div>' +
      '<div class="sum num"><div><span>' + esc(T.per_day_total) + '</span><span>' + eur(perDay) + '</span></div>' +
      (d ? '<div><span>' + esc(tr('days_factor', { d: d, f: fnum(f) })) + '</span><span>× ' + fnum(f) + '</span></div><div class="tot"><span>' + esc(T.estimate) + '</span><span>' + eur(Math.round(perDay * f)) + '</span></div>' : '<div><span class="note">' + esc(T.pick_dates) + '</span></div>') + '</div>' +
      '<p class="note">' + esc(T.factor_note) + '</p>' +
      field('name', T.name, 'text', 'autocomplete="name" required') + field('company', T.company, 'text', 'autocomplete="organization"') + field('vat', T.vat, 'text') +
      field('email', T.email, 'email', 'autocomplete="email" required') + field('phone', T.phone, 'tel', 'autocomplete="tel"') + field('message', T.message, 'textarea') +
      '<input class="hp" id="f_website_url" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true">' +
      '<p class="note">' + esc(T.privacy) + '</p><p class="err" id="err" role="alert"></p></div>' +
      '<div class="dfoot"><button class="btn" type="button" id="send">' + esc(T.send) + '</button></div>';
  }
  function send(btn) {
    keepForm();
    var err = $('err');
    if (!form.name.trim() || !/^\S+@\S+\.\S+$/.test(form.email.trim())) { err.textContent = T.need_name_email; return; }
    if (!days()) { err.textContent = T.need_dates; return; }
    if (!S.basket.size && !form.message.trim()) { err.textContent = T.need_lines; return; }
    btn.disabled = true; btn.textContent = T.sending;
    var lines = []; S.basket.forEach(function (q, id) { lines.push({ id: id, qty: q }); });
    var body = { name: form.name, company: form.company, vat: form.vat, email: form.email, phone: form.phone, message: form.message, first_day: S.out, last_day: S.back, lines: lines, page: location.pathname + location.search, website_url: ($('f_website_url') || {}).value || '' };
    fetch(C.urls.request, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) { var e = res.j.errors ? Object.values(res.j.errors)[0][0] : (res.j.message || T.error); err.textContent = e; btn.disabled = false; btn.textContent = T.send; return; }
        var first = form.name.trim().split(' ')[0];
        S.basket.clear(); save(); fab(); form.message = '';
        document.querySelectorAll('.add.in').forEach(function (b) { b.classList.remove('in'); b.innerHTML = plus; b.setAttribute('aria-label', T.add); });
        showBasket(first);
      })
      .catch(function () { err.textContent = T.error; btn.disabled = false; btn.textContent = T.send; });
  }

  // ---------- events ----------
  var timer, scrollToResults = function () { var top = $('cats').closest('nav').getBoundingClientRect().top + scrollY - 64; if (scrollY < top - 10 || scrollY > top + 400) window.scrollTo({ top: top, behavior: 'smooth' }); };
  $('q').addEventListener('input', function (e) { clearTimeout(timer); timer = setTimeout(function () { S.q = e.target.value.trim(); render(); }, 110); });
  $('q').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); scrollToResults(); } });
  $('clear').addEventListener('click', function () { $('q').value = ''; S.q = ''; render(); $('q').focus(); });
  document.querySelectorAll('[data-q]').forEach(function (b) { b.addEventListener('click', function () { $('q').value = b.dataset.q; S.q = b.dataset.q; render(); scrollToResults(); }); });
  $('cats').addEventListener('click', function (e) { var b = e.target.closest('[data-cat]'); if (!b) return; S.cat = b.dataset.cat ? +b.dataset.cat : null; S.subs.clear(); S.opts.clear(); render(); scrollToResults(); });
  $('aside').addEventListener('change', function (e) {
    var x = e.target, set = x.dataset.sub ? S.subs : x.dataset.opt ? S.opts : x.dataset.tag ? S.tags : x.dataset.brand ? S.brands : null;
    if (!set) return;
    var v = x.dataset.brand !== undefined ? x.dataset.brand : +(x.dataset.sub || x.dataset.opt || x.dataset.tag);
    x.checked ? set.add(v) : set.delete(v); render();
  });
  $('aside').addEventListener('click', function (e) { if (e.target.id === 'moreBrands') { S.brandsAll = !S.brandsAll; render(); } });
  $('active').addEventListener('click', function (e) {
    var b = e.target.closest('[data-rm]'); if (!b) return;
    var k = b.dataset.rm, v = b.dataset.v;
    if (k === 'all') { S.cat = null; S.subs.clear(); S.opts.clear(); S.tags.clear(); S.brands.clear(); }
    else if (k === 'cat') { S.cat = null; S.subs.clear(); S.opts.clear(); }
    else if (k === 'brand') S.brands.delete(v);
    else ({ sub: S.subs, opt: S.opts, tag: S.tags })[k].delete(+v);
    render();
  });
  $('sort').addEventListener('change', function (e) { S.sort = e.target.value; render(); });
  $('vGrid').addEventListener('click', function () { S.view = 'grid'; $('vGrid').setAttribute('aria-pressed', true); $('vList').setAttribute('aria-pressed', false); render(); });
  $('vList').addEventListener('click', function () { S.view = 'list'; $('vList').setAttribute('aria-pressed', true); $('vGrid').setAttribute('aria-pressed', false); render(); });
  $('results').addEventListener('click', function (e) {
    var a = e.target.closest('[data-add]'); if (a) { e.stopPropagation(); add(+a.dataset.add); return; }
    if (e.target.id === 'askUs') { showBasket(); open($('bk')); return; }
    var c = e.target.closest('[data-id]'); if (c) showItem(+c.dataset.id);
  });
  $('results').addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.matches('[data-id]')) showItem(+e.target.dataset.id); });
  $('pd').addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) close();
    var s = e.target.closest('[data-step]'); if (s) { var i = $('pdQty'); i.value = Math.max(1, (+i.value || 1) + (+s.dataset.step)); }
    var a = e.target.closest('[data-addq]'); if (a) { add(+a.dataset.addq, +$('pdQty').value); close(); }
    var r = e.target.closest('[data-show]'); if (r) showItem(+r.dataset.show);
    var p = e.target.closest('[data-ph]'); if (p) { $('pdPhoto').src = p.dataset.ph; document.querySelectorAll('#pd .thumbs button').forEach(function (x) { x.setAttribute('aria-current', x === p); }); }
  });
  $('fab').addEventListener('click', function () { showBasket(); open($('bk')); });
  $('bk').addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) { keepForm(); close(); return; }
    var s = e.target.closest('[data-bstep]');
    if (s) { keepForm(); var id = +s.dataset.id, q = (S.basket.get(id) || 1) + (+s.dataset.bstep); if (q < 1) { S.basket.delete(id); save(); fab(); render(); } else add(id, q); showBasket(); return; }
    var r = e.target.closest('[data-rmb]'); if (r) { keepForm(); S.basket.delete(+r.dataset.rmb); save(); fab(); render(); showBasket(); return; }
    if (e.target.id === 'send') send(e.target);
  });
  $('bk').addEventListener('change', function (e) {
    if (e.target.id === 'dOut') { keepForm(); S.out = e.target.value; if (S.back && S.back < S.out) S.back = S.out; save(); showBasket(); }
    else if (e.target.id === 'dBack') { keepForm(); S.back = e.target.value; save(); showBasket(); }
    else if (e.target.dataset.bq) { keepForm(); add(+e.target.dataset.bq, +e.target.value); showBasket(); }
  });
  $('filtersBtn').addEventListener('click', function () { $('aside').classList.add('on'); $('asideHead').hidden = false; scrim.classList.add('on'); openD = $('aside'); });
  $('asideClose').addEventListener('click', close);
  scrim.addEventListener('click', function () { keepForm(); close(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
    if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) { e.preventDefault(); $('q').focus(); $('q').select(); }
  });

  render(); fab();
  if (new URLSearchParams(location.search).get('add') !== null) { showBasket(); open($('bk')); }
})();
