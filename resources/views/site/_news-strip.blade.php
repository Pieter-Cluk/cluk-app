{{--
  News strip for pages outside the website (Factory booking page, Afrit 6 page…):
    @include('site._news-strip', ['part' => 'afrit6'])   // or 'factory', 'dryhire', 'sales'
  Shows the newest posts with that hashtag (#CLUKafrit6, #CLUKfactory…). Shows nothing when there are none.
  Self-contained: its own styles, videos play muted while on screen.
--}}
@php
    $stripPosts = \App\Services\Website\SocialFeed::for($part ?? null, $limit ?? 4);
    $stripName = ['dryhire' => __('site.nav.dryhire'), 'sales' => __('site.nav.sales'), 'factory' => __('site.nav.factory'), 'afrit6' => __('site.nav.afrit6')][$part ?? ''] ?? 'CLUK';
@endphp
@if ($stripPosts->isNotEmpty())
<section class="cluk-strip" aria-label="{{ __('site.news.strip_title', ['part' => $stripName]) }}">
  <style>
    .cluk-strip{max-width:1200px;margin:40px auto;padding:0 max(16px,env(safe-area-inset-left,0px));font-family:inherit;color:inherit}
    .cluk-strip .cs-head{display:flex;justify-content:space-between;align-items:baseline;gap:16px;border-bottom:2px solid currentColor;padding-bottom:8px;margin-bottom:16px}
    .cluk-strip h2{margin:0;font-size:clamp(22px,2.6vw,32px);line-height:1.1}
    .cluk-strip .cs-more{font-weight:700;text-decoration:none;color:inherit;border-bottom:2px solid #5cbf3a}
    .cluk-strip .cs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
    .cluk-strip a.cs-card{display:grid;gap:8px;text-decoration:none;color:inherit}
    .cluk-strip .cs-media{position:relative;aspect-ratio:4/3;max-width:100%;background:#0a0a0a;overflow:hidden}
    .cluk-strip .cs-media img,.cluk-strip .cs-media video{width:100%;height:100%;object-fit:cover;display:block}
    .cluk-strip .cs-media span{position:absolute;inset:0;padding:14px;display:flex;align-items:flex-end;background:#5cbf3a;color:#0a0a0a;font-weight:800}
    .cluk-strip .cs-meta{font-size:12px;opacity:.7}
    .cluk-strip b{font-size:15px;line-height:1.3}
    .cluk-strip a.cs-card:hover b{text-decoration:underline;text-decoration-color:#5cbf3a;text-decoration-thickness:2px}
  </style>
  <div class="cs-head"><h2>{{ __('site.news.strip_title', ['part' => $stripName]) }}</h2><a class="cs-more" href="{{ route('site.news', array_filter(['part' => $part ?? null])) }}">{{ __('site.news.more') }} →</a></div>
  <div class="cs-grid">
    @foreach ($stripPosts as $p)
      <a class="cs-card" href="{{ $p['url'] }}" target="_blank" rel="noopener">
        <span class="cs-media">
          @if ($p['video'])<video muted loop playsinline preload="none" @if ($p['image']) poster="{{ $p['image'] }}" @endif data-auto><source src="{{ $p['video'] }}" type="video/mp4"></video>
          @elseif ($p['image'])<img src="{{ $p['image'] }}" alt="" loading="lazy">
          @else<span>{{ \Illuminate\Support\Str::limit($p['text'], 120) }}</span>@endif
        </span>
        <span class="cs-meta">{{ (['linkedin' => 'LinkedIn'][$p['platform']] ?? ucfirst((string) $p['platform'])) }} · {{ $p['date']->locale(app()->getLocale())->translatedFormat('j M Y') }}</span>
        <b>{{ $p['title'] ?: (\Illuminate\Support\Str::limit($p['text'], 80) ?: (['linkedin' => 'LinkedIn'][$p['platform']] ?? ucfirst((string) $p['platform'])).' · '.$p['date']->locale(app()->getLocale())->translatedFormat('j F Y')) }}</b>
      </a>
    @endforeach
  </div>
  <script>
    (function(){var v=document.querySelectorAll('.cluk-strip video[data-auto]');if(!v.length||!('IntersectionObserver' in window)||matchMedia('(prefers-reduced-motion: reduce)').matches)return;
    var io=new IntersectionObserver(function(es){es.forEach(function(e){var x=e.target;if(e.isIntersecting&&e.intersectionRatio>=.5){x.muted=true;var p=x.play();if(p&&p.catch)p.catch(function(){})}else x.pause()})},{threshold:[0,.5]});v.forEach(function(x){io.observe(x)})})();
  </script>
</section>
@endif
