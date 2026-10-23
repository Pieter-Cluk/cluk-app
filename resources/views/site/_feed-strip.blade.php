{{-- A few website posts (SocialFeed) on a page outside the website layout: own styling, videos play when in view.
     $posts from SocialFeed::for(...), $title. Shows nothing when there are no posts. --}}
@if ($posts->isNotEmpty())
<section class="feed-strip" aria-label="{{ $title }}">
    <style>
        .feed-strip{max-width:1100px;margin:40px auto;padding:0 16px;font-family:inherit}
        .feed-strip>h2{margin:0 0 14px;font-size:22px}
        .feed-strip .fs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px}
        .feed-strip .npost{display:grid;gap:8px;align-content:start;min-width:0}
        .feed-strip .nmedia{position:relative;display:block;aspect-ratio:4/3;background:#0a0a0a;overflow:hidden;border-radius:6px}
        .feed-strip .nmedia img,.feed-strip .nmedia video{width:100%;height:100%;object-fit:cover;display:block}
        .feed-strip .ntext{position:absolute;inset:0;padding:14px;display:flex;align-items:flex-end;background:#52ae32;color:#0a0a0a;font-weight:700}
        .feed-strip .nvid{position:absolute;left:8px;top:8px;background:rgba(10,10,10,.75);color:#fff;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:3px 7px}
        .feed-strip .nmeta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;opacity:.75}
        .feed-strip .nsrc{display:flex;gap:6px;align-items:center}.feed-strip .nsrc svg{width:14px;height:14px}
        .feed-strip .ntag{background:#52ae32;color:#0a0a0a;padding:2px 6px;font-size:10px}
        .feed-strip h3{margin:0;font-size:15px;line-height:1.3}.feed-strip h3 a{color:inherit;text-decoration:none}
    </style>
    <h2>{{ $title }}</h2>
    <div class="fs-grid">@foreach ($posts as $p)@include('site._post', ['p' => $p])@endforeach</div>
    <script>
        (function () {
            var vids = document.querySelectorAll('.feed-strip video[data-auto]');
            if (!vids.length || !('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.preload = 'auto'; e.target.play().catch(function () {}); } else { e.target.pause(); } }); }, { threshold: .5 });
            vids.forEach(function (v) { io.observe(v); });
        })();
    </script>
</section>
@endif
