// CLUK website · slideshows from Media › Website: one picture or video at a time, dots, pause; videos only play while shown.
(function () {
  'use strict';
  var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-wslides]').forEach(function (box) {
    var slides = Array.prototype.slice.call(box.querySelectorAll('.wsl-s')), dots = box.querySelector('.wsl-dots'), pz = box.querySelector('.wsl-pause');
    var i = 0, t = null, paused = calm, ms = Math.max(3, parseInt(box.dataset.seconds, 10) || 6) * 1000;
    dots.innerHTML = slides.map(function (s, k) { return '<button type="button" aria-label="' + (s.getAttribute('aria-label') || k + 1).toString().replace(/"/g, '&quot;') + '" aria-current="' + (k === 0) + '"></button>'; }).join('');
    function media(k, on) { var v = slides[k].querySelector('video'); if (!v) return; if (on && !paused) { v.muted = true; var p = v.play(); if (p && p.catch) p.catch(function () {}); } else v.pause(); }
    function go(k) {
      media(i, false); slides[i].classList.remove('on');
      i = (k + slides.length) % slides.length;
      slides[i].classList.add('on'); media(i, true);
      Array.prototype.forEach.call(dots.children, function (d, n) { d.setAttribute('aria-current', n === i); });
    }
    function play() { clearInterval(t); if (!paused) t = setInterval(function () { go(i + 1); }, ms); }
    dots.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { go(Array.prototype.indexOf.call(dots.children, b)); play(); } });
    pz.addEventListener('click', function () { paused = !paused; pz.textContent = paused ? '▶' : '❚❚'; pz.setAttribute('aria-label', paused ? pz.dataset.play : pz.dataset.pause); media(i, !paused); play(); });
    box.addEventListener('mouseenter', function () { clearInterval(t); });
    box.addEventListener('mouseleave', play);
    if (paused) { pz.textContent = '▶'; media(0, false); }
    play();
  });
})();
