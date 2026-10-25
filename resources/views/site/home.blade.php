@extends('site.layout', ['active' => 'home'])
@section('title', __('site.home.title'))
@section('description', \App\Services\Website\SiteSettings::text('home.hero_text'))
@php($app = rtrim(config('app.url'), '/'))
@php($sales = Route::has('site.sales') ? route('site.sales') : '#sales')
@php($tiles = collect(array_keys(\App\Services\Website\SiteSettings::TILES))->mapWithKeys(fn ($t) => [$t => \App\Services\Website\SiteSettings::tile($t)])->all())
@section('content')
<main id="top">
<section class="home-hero">
  <div class="slab gut">
    <h1>{{ \App\Services\Website\SiteSettings::text('home.hero_title') }}</h1>
    <div><p class="lab">{{ \App\Services\Website\SiteSettings::text('home.hero_text') }}</p><div class="bar"></div></div>
  </div>
  @php($hero = \App\Services\Website\SiteSettings::slides('home_hero'))
  @if ($hero->isNotEmpty())
    @include('site._slides', ['slides' => $hero, 'class' => 'photo'])
  @else
  <div class="photo" role="img" aria-label="{{ __('site.home.photo') }}" style="background-image:url('{{ asset('images/site/hero.jpg') }}')"></div>
  @endif
</section>

<section class="tiles" aria-label="{{ __('site.home.what') }}">
  <a class="tile dark" href="{{ route('site.dryhire') }}"><div class="txt"><h2>{{ __('site.nav.dryhire') }}</h2><div class="lab">{{ $tiles['dryhire']['text'] }}</div></div><div class="img" style="background-image:url('{{ $tiles['dryhire']['img'] }}')"></div><div class="cta"><span>{{ $tiles['dryhire']['cta'] }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
  <a class="tile light" id="sales" href="{{ $sales }}"><div class="txt"><h2>{{ __('site.nav.sales') }}</h2><div class="lab">{{ $tiles['sales']['text'] }}</div></div><div class="img" style="background-image:url('{{ $tiles['sales']['img'] }}')"></div><div class="cta"><span>{{ $tiles['sales']['cta'] }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
  <a class="tile dark" href="{{ $app }}/book?lang={{ app()->getLocale() }}"><div class="txt"><h2>{{ __('site.nav.factory') }}</h2><div class="lab">{{ $tiles['factory']['text'] }}</div></div><div class="img" style="background-image:url('{{ $tiles['factory']['img'] }}')"></div><div class="cta"><span>{{ $tiles['factory']['cta'] }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
  <a class="tile light" href="{{ $app }}/afrit6"><div class="txt"><h2>{{ __('site.nav.afrit6') }}</h2><div class="lab">{{ $tiles['afrit6']['text'] }}</div></div><div class="img" style="background-image:url('{{ $tiles['afrit6']['img'] }}')"></div><div class="cta"><span>{{ $tiles['afrit6']['cta'] }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
</section>

@if ($mix->isNotEmpty())
<section class="hnews gut">
  <div class="hn-head">
    <h2>{{ \App\Services\Website\SiteSettings::text('home.news_title') }}</h2>
    <p class="lab">{{ \App\Services\Website\SiteSettings::text('home.news_text') }}</p>
    <div class="hn-links"><a class="follow" href="{{ route('site.news') }}"><span>{{ __('site.news.all_news') }}</span>@include('site._icon', ['i' => 'arrow'])</a></div>
  </div>
  <div class="hn-body">
    <div class="hn-list">
      <h3 class="lab">{{ __('site.news.latest') }}</h3>
      <ol>
        @foreach ($list as $p)
          <li><a href="{{ $p['url'] }}" target="_blank" rel="noopener">
            <span class="hl-meta">@include('site._icon', ['i' => $p['platform'] === 'instagram' ? 'ig' : 'fb'])<time datetime="{{ $p['date']->toIso8601String() }}">{{ $p['date']->locale(app()->getLocale())->translatedFormat('j M') }}</time>@foreach ($p['sections'] as $sct)<span class="ntag">{{ __('site.nav.'.$sct) }}</span>@endforeach</span>
            <b>{{ $p['title'] ?: (\Illuminate\Support\Str::limit($p['text'], 80) ?: ucfirst((string) $p['platform']).' · '.$p['date']->locale(app()->getLocale())->translatedFormat('j F Y')) }}</b></a></li>
        @endforeach
      </ol>
    </div>
    <div class="hn-frames">@foreach ($mix as $p)@include('site._post', ['p' => $p, 'size' => 'big'])@endforeach</div>
  </div>
</section>
@else
<section class="home-news gut">
  <div class="intro">
    <h2>{{ \App\Services\Website\SiteSettings::text('home.news_title') }}</h2>
    <p class="lab" style="margin:0">{{ \App\Services\Website\SiteSettings::text('home.news_text') }}</p>
    <div class="bar"></div>
    <a class="follow" href="{{ \App\Services\Website\SiteSettings::social('instagram') }}" target="_blank" rel="noopener"><span>{{ __('site.home.follow') }}</span>@include('site._icon', ['i' => 'arrow'])</a>
  </div>
  <a class="post big" href="{{ \App\Services\Website\SiteSettings::social('instagram') }}" target="_blank" rel="noopener"><div class="img" style="background-image:url('{{ asset('images/site/n1.jpg') }}')"></div><div class="src">@include('site._icon', ['i' => 'ig'])Instagram</div><h3><span>{{ __('site.home.posts.0') }}</span>@include('site._icon', ['i' => 'arrow'])</h3></a>
  <div class="side">
    <a class="post" href="{{ \App\Services\Website\SiteSettings::social('facebook') }}" target="_blank" rel="noopener"><div class="img" style="background-image:url('{{ asset('images/site/n2.jpg') }}')"></div><div class="src">@include('site._icon', ['i' => 'fb'])Facebook</div><h3><span>{{ __('site.home.posts.1') }}</span>@include('site._icon', ['i' => 'arrow'])</h3></a>
    <a class="post" href="{{ \App\Services\Website\SiteSettings::social('linkedin') }}" target="_blank" rel="noopener"><div class="img" style="background-image:url('{{ asset('images/site/n3.jpg') }}')"></div><div class="src">@include('site._icon', ['i' => 'li'])LinkedIn</div><h3><span>{{ __('site.home.posts.2') }}</span>@include('site._icon', ['i' => 'arrow'])</h3></a>
  </div>
</section>
@endif
</main>
@endsection
@push('scripts')<script src="{{ asset('js/site-slides.js') }}?v={{ @filemtime(public_path('js/site-slides.js')) }}" defer></script>@endpush
