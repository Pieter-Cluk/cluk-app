{{-- One social post: picture or muted looping video, title, where and when it was posted. $p from SocialFeed. --}}
@php
    $labels = ['dryhire' => __('site.nav.dryhire'), 'sales' => __('site.nav.sales'), 'factory' => __('site.nav.factory'), 'afrit6' => __('site.nav.afrit6')];
    $pf = (['linkedin' => 'LinkedIn'][$p['platform']] ?? ucfirst((string) $p['platform']));
@endphp
<article class="npost {{ $size ?? '' }}">
  <a class="nmedia" href="{{ $p['url'] }}" target="_blank" rel="noopener" aria-label="{{ __('site.news.open', ['platform' => $pf]) }}: {{ $p['title'] }}">
    @if ($p['video'])
      <video muted loop playsinline preload="none" @if ($p['image']) poster="{{ $p['image'] }}" @endif data-auto><source src="{{ $p['video'] }}" type="video/mp4"></video>
      <span class="nvid" aria-hidden="true">▶ {{ __('site.news.video') }}</span>
    @elseif ($p['image'])
      <img src="{{ $p['image'] }}" alt="" loading="lazy" decoding="async">
    @else
      <span class="ntext">{{ \Illuminate\Support\Str::limit($p['text'], 160) }}</span>
    @endif
  </a>
  <div class="nmeta">
    <span class="nsrc">@include('site._icon', ['i' => ['instagram' => 'ig', 'linkedin' => 'li'][$p['platform']] ?? 'fb']){{ __('site.news.on', ['platform' => $pf]) }} · <time datetime="{{ $p['date']->toIso8601String() }}">{{ $p['date']->locale(app()->getLocale())->translatedFormat('j M Y') }}</time></span>
    @foreach ($p['sections'] as $s)<span class="ntag">{{ $labels[$s] }}</span>@endforeach
  </div>
  <h3><a href="{{ $p['url'] }}" target="_blank" rel="noopener">{{ $p['title'] ?: (\Illuminate\Support\Str::limit($p['text'], 90) ?: (['linkedin' => 'LinkedIn'][$p['platform']] ?? ucfirst((string) $p['platform'])).' · '.$p['date']->locale(app()->getLocale())->translatedFormat('j F Y')) }}</a></h3>
</article>
