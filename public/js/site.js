// CLUK website: header (login menu, mobile menu, language).
(function () {
  var login = document.getElementById('login');
  document.addEventListener('click', function (e) { if (login && login.open && !login.contains(e.target)) login.open = false; });
  var nav = document.getElementById('nav'), mb = document.getElementById('menuBtn');
  if (mb) mb.addEventListener('click', function () { var o = nav.classList.toggle('open'); mb.setAttribute('aria-expanded', o); });
  var lang = document.getElementById('lang');
  if (lang) lang.addEventListener('change', function () { location.href = lang.value; });
})();
