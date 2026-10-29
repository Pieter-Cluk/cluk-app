<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>CLUK · @yield('title')</title>
<link rel="stylesheet" href="{{ asset('css/wall.css') }}?v={{ @filemtime(public_path('css/wall.css')) }}">
@include('partials.favicon')
<style>
.wall-full{position:fixed;left:24px;bottom:24px;z-index:50;font:inherit;font-size:16px;font-weight:700;background:#52ae32;color:#0f1a0b;border:0;border-radius:8px;padding:14px 20px;cursor:pointer;transition:opacity .4s}
body.idle{cursor:none}
body.idle .wall-full,:fullscreen .wall-full{opacity:0;pointer-events:none}
</style>
<noscript><meta http-equiv="refresh" content="30"></noscript>
</head>
<body class="wall">
<div id="wall-root">
<div class="top">
    <div class="top-l"><img src="{{ asset('images/cluk-logo.png') }}" alt="cluk" height="40"><h1>@yield('title')</h1>@yield('nav')</div>
    <div class="clock"><b id="clock">{{ now()->format('H:i') }}</b><span>{{ now()->locale(app()->getLocale())->translatedFormat('D j M') }} · {{ __('wall.refresh') }}</span></div>
</div>
@yield('body')
</div>
<button class="wall-full" id="wall-full" type="button">⛶ {{ __('wall.entrance.fullscreen') }}</button>
<script>
(function () {
    // The page refreshes itself without reloading, so full screen stays on (a reload would end it).
    var root = document.getElementById('wall-root');
    function swap(url, push) {
        return fetch(url, { cache: 'no-store', credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) return;
            return r.text().then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html'), next = doc.getElementById('wall-root');
                if (!next) return;
                root.innerHTML = next.innerHTML;
                document.title = doc.title;
                if (push) history.pushState(null, '', url);
            });
        }).catch(function () {});
    }
    setInterval(function () { swap(location.href, false); }, 30000);
    // Buttons on the screen (days, weeks) also change without a reload.
    root.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a || a.target || a.origin !== location.origin || a.pathname !== location.pathname) return;
        e.preventDefault();
        swap(a.href, true);
    });
    window.addEventListener('popstate', function () { swap(location.href, false); });

    setInterval(function () { var c = document.getElementById('clock'), d = new Date(); if (c) c.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }, 5000);

    // Full screen: the button or any key (a browser only allows it after a click or key press).
    function full() {
        var el = document.documentElement, go = el.requestFullscreen || el.webkitRequestFullscreen;
        if (go && !(document.fullscreenElement || document.webkitFullscreenElement)) { try { var p = go.call(el); if (p && p.catch) p.catch(function () {}); } catch (e) {} }
    }
    document.getElementById('wall-full').addEventListener('click', full);
    document.addEventListener('keydown', full);
    var idle;
    function wake() { document.body.classList.remove('idle'); clearTimeout(idle); idle = setTimeout(function () { document.body.classList.add('idle'); }, 4000); }
    document.addEventListener('mousemove', wake);
    wake();
})();
</script>
</body>
</html>
