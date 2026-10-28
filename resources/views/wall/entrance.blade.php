@php($media = fn ($p) => route('site.webmedia', ['f' => basename($p)]))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Afrit 6 · {{ __('wall.admin.screen_entrance') }}</title>
@include('partials.favicon')
<style>
:root{--green:#52ae32;--ink:#f3f6f2;--muted:#b9c2ba;--panel:rgba(16,20,17,.86)}
*{box-sizing:border-box}
html,body{margin:0;height:100%;background:#0f1310;color:var(--ink);font-family:Arial,"Helvetica Neue",Helvetica,sans-serif;overflow:hidden}
body.idle{cursor:none}
.en-show{position:fixed;inset:0}
.en-slide{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 1.2s ease}
.en-slide.on{opacity:1}
.en-empty{position:absolute;inset:0;display:grid;place-items:center;background:radial-gradient(circle at 30% 40%,#2c5b1d,#0f1310 70%)}
.en-empty img{width:22vw;opacity:.9}
.en-panel{position:fixed;top:0;right:0;bottom:0;width:31vw;min-width:360px;background:var(--panel);backdrop-filter:blur(8px);padding:3.2vw 2.6vw;display:flex;flex-direction:column;gap:2.4vw}
.en-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1vw}
.en-head img{height:2.6vw;min-height:28px}
.en-clock{text-align:right}
.en-clock b{display:block;font-size:4.2vw;line-height:1;font-weight:700;letter-spacing:-.02em}
.en-clock span{font-size:1.15vw;color:var(--muted)}
.en-agenda{display:flex;flex-direction:column;gap:2.4vw;flex:1;min-height:0;overflow:hidden}
.en-block h2{margin:0 0 1.1vw;font-size:2.6vw;line-height:1.1;color:var(--green)}
.en-block h3{margin:0 0 .9vw;font-size:1.05vw;letter-spacing:.18em;text-transform:uppercase;color:var(--muted)}
.en-hello p{margin:0;font-size:1.4vw;color:var(--muted);line-height:1.4}
.en-guests{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:1.1vw}
.en-guests li{display:flex;gap:1.2vw;align-items:baseline;border-left:.3vw solid var(--green);padding-left:1vw}
.en-guests li.en-past{opacity:.55}
.en-guests time{font-size:1.3vw;color:var(--muted);min-width:4.2vw}
.en-guests b{display:block;font-size:2vw;line-height:1.15}
.en-guests span{display:block;font-size:1.3vw;color:var(--ink);opacity:.85}
.en-guests small{display:block;font-size:1.05vw;color:var(--muted);margin-top:.2vw}
.en-room{display:flex;gap:1vw;align-items:baseline;padding:.7vw 0;border-top:1px solid rgba(255,255,255,.12);font-size:1.3vw}
.en-room time{color:var(--muted);min-width:8.5vw}
.en-room small{color:var(--muted)}
.en-room em{margin-left:auto;font-style:normal;font-size:.95vw;font-weight:700;background:var(--green);color:#0f1a0b;border-radius:99px;padding:.2vw .7vw}
.en-muted{margin:0;font-size:1.2vw;color:var(--muted)}
.en-foot{font-size:.95vw;color:var(--muted);letter-spacing:.2em}
.en-full{position:fixed;left:2vw;bottom:2vw;font:inherit;font-size:16px;font-weight:700;background:var(--green);color:#0f1a0b;border:0;border-radius:8px;padding:14px 20px;cursor:pointer;transition:opacity .4s}
body.idle .en-full,:fullscreen .en-full{opacity:0;pointer-events:none}
@media (max-aspect-ratio:1/1){.en-panel{width:auto;left:0;top:auto;height:55vh;min-width:0}.en-block h2{font-size:5vw}.en-guests b{font-size:4vw}.en-room,.en-hello p{font-size:3vw}.en-clock b{font-size:8vw}.en-clock span,.en-guests time,.en-guests span,.en-guests small,.en-block h3{font-size:2.4vw}}
@media (max-aspect-ratio:1/1){.en-full{top:2vw;bottom:auto}}
@media (prefers-reduced-motion:reduce){.en-slide{transition:none}}
</style>
</head>
<body>
<div class="en-show" id="show" data-seconds="{{ \App\Services\Afrit6\Entrance::SECONDS }}">
    @forelse ($slides as $i => $s)
        @if ($s->kind === 'video')
            <video class="en-slide" data-kind="video" src="{{ $media($s->path) }}" @if ($s->poster_path) poster="{{ $media($s->poster_path) }}" @endif muted playsinline preload="{{ $i === 0 ? 'auto' : 'metadata' }}"></video>
        @else
            <img class="en-slide" data-kind="image" src="{{ $media($s->path) }}" alt="{{ $s->alt }}" @if ($i > 1) loading="lazy" @endif>
        @endif
    @empty
        <div class="en-empty"><img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK"></div>
    @endforelse
</div>

<aside class="en-panel">
    <div class="en-head">
        <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK · Afrit 6">
        <div class="en-clock"><b id="clock">{{ now()->format('H:i') }}</b><span id="date">{{ now()->locale(app()->getLocale())->translatedFormat('l j F') }}</span></div>
    </div>
    <div class="en-agenda" id="agenda">@include('wall.entrance-panel')</div>
    <div class="en-foot">AFRIT 6 · CO-WORKING</div>
</aside>

<button class="en-full" id="full" type="button">⛶ {{ __('wall.entrance.fullscreen') }}</button>

<script>
(function () {
    var version = @js($version), panelUrl = @js(route('wall.show', ['view' => 'entrance', 'key' => $key, 'part' => 'panel', 'lang' => app()->getLocale()]));
    var day = @js(today()->toDateString());

    // Slideshow: pictures stay a few seconds, videos play to the end.
    var show = document.getElementById('show'), slides = Array.prototype.slice.call(show.querySelectorAll('.en-slide')), seconds = +show.dataset.seconds, at = -1, timer;
    function next() {
        clearTimeout(timer);
        if (!slides.length) return;
        var prev = slides[at];
        at = (at + 1) % slides.length;
        var s = slides[at];
        if (prev && prev !== s) { prev.classList.remove('on'); if (prev.tagName === 'VIDEO') setTimeout(function () { prev.pause(); }, 1200); }
        s.classList.add('on');
        if (s.tagName === 'VIDEO') {
            s.currentTime = 0;
            var p = s.play();
            if (p && p.catch) p.catch(function () { timer = setTimeout(next, seconds * 1000); });
            s.onended = slides.length > 1 ? next : function () { s.currentTime = 0; s.play(); };
            s.onerror = next;
            timer = setTimeout(next, 5 * 60 * 1000); // safety: a stuck video never blocks the show
        } else if (slides.length > 1) {
            timer = setTimeout(next, seconds * 1000);
        }
    }
    next();

    // Clock.
    function clock() {
        var d = new Date();
        document.getElementById('clock').textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }
    setInterval(clock, 5000);

    // Agenda every minute; a new day or changed slides reload the page.
    function poll() {
        fetch(panelUrl, { cache: 'no-store' }).then(function (r) {
            if (!r.ok) return;
            var v = r.headers.get('X-Slides');
            var today = new Date(); today = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
            if ((v && v !== version) || today !== day) { location.reload(); return; }
            return r.text().then(function (html) { document.getElementById('agenda').innerHTML = html; });
        }).catch(function () {});
    }
    setInterval(poll, 60000);

    // Full screen: the button, a click or a key. The browser only allows it after a touch, click or key press.
    function full() {
        var el = document.documentElement, go = el.requestFullscreen || el.webkitRequestFullscreen;
        if (go && !(document.fullscreenElement || document.webkitFullscreenElement)) { try { var p = go.call(el); if (p && p.catch) p.catch(function () {}); } catch (e) {} }
    }
    document.getElementById('full').addEventListener('click', full);
    document.addEventListener('keydown', full);
    show.addEventListener('click', full);

    // Hide the mouse and the button when nobody moves the mouse.
    var idle;
    function wake() { document.body.classList.remove('idle'); clearTimeout(idle); idle = setTimeout(function () { document.body.classList.add('idle'); }, 4000); }
    document.addEventListener('mousemove', wake);
    wake();
})();
</script>
</body>
</html>
