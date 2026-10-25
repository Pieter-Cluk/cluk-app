/*
 * Type-to-search for long drop-downs (customers, suppliers…), handy on a phone.
 * A <select> with data-pick, or with 40 options or more, gets a text field on top:
 * type part of a name (any word, accents ignored) and pick from the matches.
 * The <select> stays in the form and keeps the value, so nothing changes for the server.
 */
(function () {
  'use strict';
  var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var uid = 0;

  function enhance(sel) {
    if (sel.dataset.pickDone || sel.multiple || sel.dataset.nopick !== undefined) return;
    sel.dataset.pickDone = '1';
    var opts = Array.prototype.slice.call(sel.options).map(function (o) {
      return { value: o.value, text: o.text, key: norm(o.text), group: o.parentNode.tagName === 'OPTGROUP' ? o.parentNode.label : '' };
    });
    var wrap = document.createElement('div');
    wrap.className = 'pick';
    var input = document.createElement('input');
    var list = document.createElement('ul');
    var id = 'pick-' + (++uid);
    input.type = 'search';
    input.className = 'pick-input';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', id);
    input.setAttribute('aria-label', sel.getAttribute('aria-label') || (sel.closest('label') && sel.closest('label').querySelector('span') ? sel.closest('label').querySelector('span').textContent : ''));
    input.placeholder = document.documentElement.dataset.pickHint || 'Type to search…';
    if (sel.required) input.required = true;
    list.id = id;
    list.className = 'pick-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(input);
    wrap.appendChild(list);
    wrap.appendChild(sel);
    sel.classList.add('pick-hidden');
    sel.tabIndex = -1;
    sel.setAttribute('aria-hidden', 'true');
    sel.required = false;

    var shown = [];
    var active = -1;
    var current = function () { var o = sel.options[sel.selectedIndex]; return o ? o.text : ''; };
    input.value = sel.value !== '' ? current() : '';

    function render(q) {
      var words = norm(q).split(/\s+/).filter(Boolean);
      shown = opts.filter(function (o) { return words.every(function (w) { return o.key.indexOf(w) !== -1; }); }).slice(0, 40);
      list.innerHTML = '';
      shown.forEach(function (o, i) {
        var li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.id = id + '-' + i;
        li.textContent = o.text;
        if (o.group) { var g = document.createElement('small'); g.textContent = ' · ' + o.group; li.appendChild(g); }
        if (o.value === sel.value) li.setAttribute('aria-selected', 'true');
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
        list.appendChild(li);
      });
      if (!shown.length) { var none = document.createElement('li'); none.className = 'pick-none'; none.textContent = '—'; list.appendChild(none); }
      active = -1;
      open(true);
    }
    function open(on) { list.hidden = !on; input.setAttribute('aria-expanded', on ? 'true' : 'false'); }
    function mark(i) {
      var items = list.querySelectorAll('[role=option]');
      items.forEach(function (li, n) { li.classList.toggle('on', n === i); });
      active = i;
      if (items[i]) { input.setAttribute('aria-activedescendant', items[i].id); items[i].scrollIntoView({ block: 'nearest' }); }
    }
    function choose(i) {
      var o = shown[i];
      if (!o) return;
      sel.value = o.value;
      input.value = o.value === '' ? '' : o.text;
      open(false);
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    input.addEventListener('focus', function () { input.select(); render(''); });
    input.addEventListener('input', function () { render(input.value); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) render(input.value); mark(Math.min(active + 1, shown.length - 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mark(Math.max(active - 1, 0)); }
      else if (e.key === 'Enter' && !list.hidden) { e.preventDefault(); choose(active >= 0 ? active : 0); }
      else if (e.key === 'Escape') { open(false); input.value = current(); }
    });
    input.addEventListener('blur', function () {
      open(false);
      // An emptied field means "none" when the list allows it; otherwise the last choice stays.
      if (input.value.trim() === '' && opts.some(function (o) { return o.value === ''; })) { if (sel.value !== '') { sel.value = ''; sel.dispatchEvent(new Event('change', { bubbles: true })); } }
      input.value = sel.value !== '' ? current() : '';
    });
  }

  function run(root) {
    (root || document).querySelectorAll('select').forEach(function (s) {
      if (s.dataset.pick !== undefined || s.options.length >= 40) enhance(s);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { run(); }); else run();
  window.cluckPick = run;
})();
