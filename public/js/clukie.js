// Clukie: the CLUK chat helper (animated mascot + chat window).
// Self-contained: reads its settings from <script type="application/json" id="clukie-cfg">.
// Moods: idle, wave, think, thumbs, sleep, hop. When a clip exists for a mood (cfg.clips)
// it plays that video; otherwise the picture is animated with CSS.
(function () {
  'use strict';
  var el = document.getElementById('clukie-cfg');
  if (!el) return;
  var C = JSON.parse(el.textContent || '{}'), T = C.t || {}, STORE = C.store || 'clukie-chat', SEEN = STORE + '-nudged';
  var S = { token: null, last: 0, open: false, busy: false, status: 'bot', unread: 0 };
  try { var saved = JSON.parse(localStorage.getItem(STORE) || 'null'); if (saved && saved.token) { S.token = saved.token; } } catch (e) {}
  function keep() { try { localStorage.setItem(STORE, JSON.stringify({ token: S.token })); } catch (e) {} }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function eur(c) { return '€ ' + (c / 100).toLocaleString(C.locale === 'en' ? 'en-GB' : 'nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function rich(s) {
    return esc(s).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
      .replace(/(https?:\/\/[^\s<)]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
      .replace(/(^|\s)(\/(?:dry-hire|sales)[^\s<,.)]*)/g, '$1<a href="' + (C.base || '') + '$2">$2</a>')
      .replace(/\n/g, '<br>');
  }
  function api(url, method, body) {
    var tok = document.querySelector('meta[name=csrf-token]');
    return fetch(url, { method: method || 'GET', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': tok ? tok.content : '' }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); });
  }

  // ---------- markup ----------
  var root = document.createElement('div');
  root.className = 'clukie';
  root.innerHTML =
    '<div class="ck-nudge" hidden><button type="button" class="ck-nudge-x" aria-label="' + esc(T.close) + '">✕</button><span></span></div>' +
    '<button type="button" class="ck-launch" aria-label="' + esc(T.open_label) + '" aria-expanded="false"><span class="ck-stage"><span class="ck-spark"></span><img alt="" src="' + esc(C.img) + '"></span><span class="ck-unread" hidden>0</span><span class="ck-zz" aria-hidden="true">z<i>z</i></span></button>' +
    '<section class="ck-panel" role="dialog" aria-label="' + esc(T.name) + '" hidden>' +
      '<header><span class="ck-ava"><img alt="" src="' + esc(C.head) + '"></span><div><b>' + esc(T.name) + '</b><small class="ck-state"></small></div>' +
      '<button type="button" class="ck-x" aria-label="' + esc(T.close) + '">✕</button></header>' +
      '<div class="ck-log" aria-live="polite"></div>' +
      '<div class="ck-chips"></div>' +
      '<form class="ck-team" hidden novalidate><h3>' + esc(T.team_title) + '</h3><p class="ck-team-text"></p>' +
        (C.customer ? '' : '<div class="ck-two"><input name="name" autocomplete="name" placeholder="' + esc(T.name_f) + '" required><input name="email" type="email" autocomplete="email" placeholder="' + esc(T.email) + '" required></div><input name="phone" type="tel" autocomplete="tel" placeholder="' + esc(T.phone) + '">') +
        '<textarea name="question" rows="3" placeholder="' + esc(T.question) + '" required></textarea><div class="ck-acts"><button type="button" class="ck-ghost" data-back>' + esc(T.back) + '</button><button type="submit">' + esc(T.team_send) + '</button></div><p class="ck-err" hidden></p></form>' +
      '<form class="ck-in"><textarea rows="1" maxlength="' + (C.max || 1000) + '" placeholder="' + esc(T.placeholder) + '" aria-label="' + esc(T.placeholder) + '"></textarea><button type="submit" aria-label="' + esc(T.send) + '"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l16-8-6 16-2.5-6.5z" fill="currentColor"/></svg></button></form>' +
      '<p class="ck-note">' + esc(T.privacy) + '</p>' +
    '</section>';
  document.body.appendChild(root);
  var $ = function (s) { return root.querySelector(s); };
  var launch = $('.ck-launch'), panel = $('.ck-panel'), log = $('.ck-log'), chips = $('.ck-chips'), inForm = $('.ck-in'), input = $('.ck-in textarea'),
      team = $('.ck-team'), stage = $('.ck-stage'), nudge = $('.ck-nudge'), unread = $('.ck-unread');

  // ---------- moods ----------
  var clips = C.clips || {}, video = null, moodT = null;
  function mood(m, ms) {
    root.dataset.mood = m;
    if (clips[m]) {
      if (!video) { video = document.createElement('video'); video.muted = true; video.playsInline = true; video.setAttribute('playsinline', ''); video.className = 'ck-clip'; stage.appendChild(video); }
      if (video.dataset.m !== m) { video.dataset.m = m; video.loop = m === 'idle' || m === 'think' || m === 'sleep'; video.src = clips[m]; var p = video.play(); if (p && p.catch) p.catch(function () {}); }
      root.classList.add('has-clip');
    } else { root.classList.remove('has-clip'); }
    clearTimeout(moodT);
    if (ms) moodT = setTimeout(function () { mood(rest()); }, ms);
  }
  function rest() { return !C.available && !C.team_open ? 'sleep' : 'idle'; }

  // ---------- state line ----------
  function state() {
    var s = $('.ck-state');
    if (S.status === 'waiting') s.textContent = C.team_open ? T.waiting_open : T.waiting_closed.replace(':next', C.next);
    else if (S.status === 'staff') s.textContent = T.with_team;
    else s.textContent = C.team_open ? T.status_open : T.status_closed.replace(':next', C.next);
  }

  // ---------- messages ----------
  function card(c) {
    var price = c.price > 0 ? '<span class="ck-p">' + eur(c.price) + ' <small>' + esc(T.excl) + '</small></span>' + (c.list > c.price ? '<s>' + eur(c.list) + '</s>' : '') : '<span class="ck-p">' + esc(T.on_request) + '</span>';
    return '<a class="ck-card" href="' + esc(c.url) + '">' + (c.photo ? '<img src="' + esc(c.photo) + '" alt="" loading="lazy">' : '<span class="ck-noimg">' + esc(c.brand || '') + '</span>') +
      '<span class="ck-cn">' + esc(c.name) + '</span>' + price + '<span class="ck-st ' + (c.stock > 0 ? 'ok' : '') + '">' + esc(c.stock > 0 ? T.in_stock.replace(':n', c.stock) : T.lead) + '</span></a>';
  }
  function line(m) {
    if (m.role === 'note') { return '<div class="ck-sys">' + esc(m.body === 'handed_over' ? T.handed : m.body) + '</div>'; }
    var who = m.role === 'visitor' ? 'me' : (m.role === 'staff' ? 'staff' : 'bot');
    return '<div class="ck-msg ' + who + '">' + (who === 'staff' ? '<span class="ck-tag">' + esc(T.staff) + '</span>' : '') + '<div class="ck-b">' + rich(m.body) + '</div>' +
      (m.cards && m.cards.length ? '<div class="ck-cards">' + m.cards.map(card).join('') + '</div>' : '') + '</div>';
  }
  function add(list, silent) {
    var n = 0;
    (list || []).forEach(function (m) {
      if (m.id <= S.last) return;
      S.last = m.id; log.insertAdjacentHTML('beforeend', line(m)); n++;
      if (m.role === 'clukie' && m.cards && m.cards.length) mood('thumbs', 2600);
      if (!silent && (m.role === 'staff' || m.role === 'clukie') && !S.open) { S.unread++; }
    });
    unread.hidden = !S.unread; unread.textContent = S.unread;
    if (n) log.scrollTop = log.scrollHeight;
    var lastBot = (list || []).filter(function (m) { return m.role === 'clukie'; }).pop();
    if (!silent && lastBot && lastBot.offer) showTeam();
  }
  function hello() {
    if (log.childElementCount) return;
    log.insertAdjacentHTML('beforeend', '<div class="ck-msg bot"><div class="ck-b">' + rich(C.available ? T.hello : T.hello_team) + '</div></div>');
    chips.innerHTML = (C.available ? [['find', T.chip_find], ['price', T.chip_price]] : []).concat([['team', T.chip_team]])
      .map(function (c) { return '<button type="button" data-chip="' + c[0] + '">' + esc(c[1]) + '</button>'; }).join('');
  }
  function typing(on) {
    var t = log.querySelector('.ck-typing');
    if (on && !t) { log.insertAdjacentHTML('beforeend', '<div class="ck-msg bot ck-typing" aria-label="' + esc(T.thinking) + '"><div class="ck-b"><i></i><i></i><i></i></div></div>'); log.scrollTop = log.scrollHeight; }
    if (!on && t) t.remove();
  }
  function handle(r, silent) {
    if (!r.j) return;
    if (r.j.token && r.j.token !== S.token) { S.token = r.j.token; keep(); }
    if (r.j.status) S.status = r.j.status;
    if (typeof r.j.open === 'boolean') { C.team_open = r.j.open; C.next = r.j.next || C.next; }
    if ((r.j.messages || []).length) { chips.innerHTML = ''; }
    add(r.j.messages, silent);
    (r.j.actions || []).forEach(function (a) {
      if (a.type === 'add' && window.clukSales && window.clukSales.add) { window.clukSales.add(a); mood('thumbs', 2600); }
    });
    state(); poller();
  }

  // ---------- sending ----------
  function send(text) {
    text = (text || '').trim();
    if (!text || S.busy) return;
    S.busy = true; chips.innerHTML = ''; input.value = ''; grow();
    log.insertAdjacentHTML('beforeend', '<div class="ck-msg me pending"><div class="ck-b">' + rich(text) + '</div></div>'); log.scrollTop = log.scrollHeight;
    if (S.status === 'bot') { typing(true); mood('think'); }
    api(C.url.message, 'POST', { token: S.token, after: S.last, text: text, page: location.pathname + location.search }).then(function (r) {
      typing(false); S.busy = false;
      var p = log.querySelector('.pending'); if (p) p.remove();
      if (!r.ok && r.status !== 422) { log.insertAdjacentHTML('beforeend', '<div class="ck-sys err">' + esc(T.error) + '</div>'); mood(rest()); return; }
      handle(r); if (root.dataset.mood === 'think') mood(rest());
      if (r.status === 422) log.insertAdjacentHTML('beforeend', '<div class="ck-sys">' + esc(T.limit) + '</div>');
    }).catch(function () { typing(false); S.busy = false; log.insertAdjacentHTML('beforeend', '<div class="ck-sys err">' + esc(T.error) + '</div>'); mood(rest()); });
  }
  inForm.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
  function grow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; }
  input.addEventListener('input', grow);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); } });
  chips.addEventListener('click', function (e) {
    var b = e.target.closest('[data-chip]'); if (!b) return;
    if (b.dataset.chip === 'team') { showTeam(); return; }
    if (b.dataset.chip === 'find') { input.value = T.chip_find_text; input.focus(); grow(); return; }
    send(T.chip_price_text);
  });

  // ---------- talk to the team ----------
  function showTeam() {
    if (S.status !== 'bot') return;
    $('.ck-team-text').textContent = C.team_open ? T.team_open : T.team_closed.replace(':next', C.next);
    team.hidden = false; inForm.hidden = true; chips.innerHTML = '';
    var f = team.querySelector('input,textarea'); if (f) f.focus();
  }
  team.querySelector('[data-back]').addEventListener('click', function () { team.hidden = true; inForm.hidden = false; input.focus(); });
  team.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(team), body = { token: S.token, after: S.last, page: location.pathname + location.search }, err = team.querySelector('.ck-err');
    fd.forEach(function (v, k) { body[k] = String(v).trim(); });
    if (!body.question || (!C.customer && (!body.name || !/^\S+@\S+\.\S+$/.test(body.email || '')))) { err.textContent = T.team_missing; err.hidden = false; return; }
    err.hidden = true;
    api(C.url.team, 'POST', body).then(function (r) {
      if (!r.ok) { err.textContent = T.error; err.hidden = false; return; }
      team.reset(); team.hidden = true; inForm.hidden = false; handle(r); mood('thumbs', 2600);
    });
  });

  // ---------- polling (the team answers in the same chat) ----------
  var pollT = null;
  function poller() {
    clearTimeout(pollT);
    if (!S.token || S.status === 'bot' || S.status === 'closed') return;
    pollT = setTimeout(function () {
      api(C.url.poll + '?token=' + encodeURIComponent(S.token) + '&after=' + S.last).then(function (r) { if (r.ok) handle(r); else if (r.status === 404 || r.status === 410) { S.token = null; keep(); } });
    }, S.open ? 5000 : 20000);
  }

  // ---------- open / close ----------
  function openPanel(fromNudge) {
    S.open = true; S.unread = 0; unread.hidden = true; nudge.hidden = true;
    panel.hidden = false; root.classList.add('is-open'); launch.setAttribute('aria-expanded', 'true');
    mood('wave', 1800);
    if (!log.childElementCount) hello();
    setTimeout(function () { input.focus(); }, 60);
    if (window.cluk && fromNudge) window.cluk.track('view', { section: 'sales', label: 'clukie' });
    poller();
  }
  function closePanel() { S.open = false; panel.hidden = true; root.classList.remove('is-open'); launch.setAttribute('aria-expanded', 'false'); launch.focus(); poller(); }
  launch.addEventListener('click', function () { S.open ? closePanel() : openPanel(); });
  $('.ck-x').addEventListener('click', closePanel);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && S.open) closePanel(); });

  // ---------- one gentle nudge per visit (only when someone seems stuck) ----------
  function seen() { try { return sessionStorage.getItem(SEEN); } catch (e) { return '1'; } }
  function showNudge(text) {
    if (S.open || seen()) return;
    try { sessionStorage.setItem(SEEN, '1'); } catch (e) {}
    nudge.querySelector('span').textContent = text; nudge.hidden = false; mood('wave', 2000);
    setTimeout(function () { nudge.hidden = true; }, 12000);
  }
  nudge.addEventListener('click', function (e) { if (e.target.closest('.ck-nudge-x')) { nudge.hidden = true; return; } openPanel(true); });
  if (/\/sales\/search/.test(location.pathname) && !document.querySelector('.scard')) setTimeout(function () { showNudge(T.nudge_search); }, 2500);
  else if (/\/sales\/product\//.test(location.pathname)) setTimeout(function () { showNudge(T.nudge_product); }, 45000);

  // ---------- start ----------
  mood('hop', 1400);
  state();
  // an earlier conversation in this browser: load it quietly (the team may still answer)
  if (S.token) { api(C.url.poll + '?token=' + encodeURIComponent(S.token) + '&after=0').then(function (r) { if (r.ok) handle(r, true); else { S.token = null; keep(); } }).catch(function () {}); }
})();
