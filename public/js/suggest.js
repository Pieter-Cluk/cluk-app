/*
 * Typing suggestions for text fields with a list of known values (<input list="…"> + <datalist>), e.g. "Where is it?":
 * the browser's own list is replaced by one that opens on focus and narrows down while you type (code or name, any part,
 * accents ignored). Click or Enter takes the highlighted value; the field stays free text, so nothing changes for the server.
 */
(function () {
  'use strict';
  var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var uid = 0;
  var MAX = 60;

  function enhance(input) {
    if (input.dataset.suggestDone) return;
    var dl = document.getElementById(input.getAttribute('list'));
    if (!dl) return;
    input.dataset.suggestDone = '1';
    var opts = Array.prototype.slice.call(dl.options).map(function (o) {
      return { value: o.value, label: o.label && o.label !== o.value ? o.label : (o.textContent !== o.value ? o.textContent : ''), key: norm(o.value + ' ' + (o.label || o.textContent)) };
    });
    input.removeAttribute('list');
    input.setAttribute('autocomplete', 'off');
    var id = 'sugg-' + (++uid);
    var wrap = document.createElement('span');
    wrap.className = 'pick sugg';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var list = document.createElement('ul');
    list.className = 'pick-list';
    list.id = id;
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    wrap.appendChild(list);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-controls', id);
    input.setAttribute('aria-expanded', 'false');
    var shown = [], on = -1;

    function render() {
      var words = norm(input.value).split(/\s+/).filter(Boolean);
      shown = opts.filter(function (o) { return words.every(function (w) { return o.key.indexOf(w) !== -1; }); });
      // Codes that start with what was typed come first (GA2… before places that only mention it).
      var first = norm(input.value).replace(/\s+/g, '');
      if (first) shown.sort(function (a, b) { return (norm(b.value).indexOf(first) === 0) - (norm(a.value).indexOf(first) === 0); });
      list.innerHTML = '';
      shown.slice(0, MAX).forEach(function (o, i) {
        var li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.innerHTML = '<b></b>' + (o.label ? ' <small></small>' : '');
        li.firstChild.textContent = o.value;
        if (o.label) li.lastChild.textContent = o.label;
        li.addEventListener('mousedown', function (e) { e.preventDefault(); take(i); });
        list.appendChild(li);
      });
      if (!shown.length) { list.innerHTML = '<li class="pick-none">—</li>'; }
      on = shown.length && words.length ? 0 : -1;
      mark();
      open(true);
    }
    function mark() {
      Array.prototype.forEach.call(list.children, function (li, i) { li.classList.toggle('on', i === on); if (i === on) li.scrollIntoView({ block: 'nearest' }); });
    }
    function open(yes) { list.hidden = !yes; input.setAttribute('aria-expanded', yes ? 'true' : 'false'); }
    function take(i) {
      if (!shown[i]) return;
      input.value = shown[i].value;
      open(false);
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    input.addEventListener('focus', render);
    input.addEventListener('input', render);
    input.addEventListener('blur', function () { setTimeout(function () { open(false); }, 120); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { render(); return; }
      if (e.key === 'ArrowDown') { e.preventDefault(); on = Math.min(on + 1, Math.min(shown.length, MAX) - 1); mark(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); on = Math.max(on - 1, 0); mark(); }
      else if (e.key === 'Enter' && !list.hidden && on >= 0 && shown[on] && norm(shown[on].value) !== norm(input.value)) { e.preventDefault(); take(on); }
      else if (e.key === 'Escape') { open(false); }
    });
  }

  function run(root) { (root || document).querySelectorAll('input[list]').forEach(enhance); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { run(); }); else run();
  window.clukSuggest = run;
})();
