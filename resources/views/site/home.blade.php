@extends('site.layout', ['active' => 'home'])
@section('title', __('site.home.title'))
@section('description', __('site.home.hero_text'))
@php($app = rtrim(config('app.url'), '/'))
@php($sales = Route::has('site.sales') ? route('site.sales') : '#sales')
@section('content')
<main id="top">
<section class="home-hero">
  <div class="slab gut">
    <h1>{{ __('site.home.hero_title') }}</h1>
    <div><p class="lab">{{ __('site.home.hero_text') }}</p><div class="bar"></div></div>
  </div>
  <div class="photo" role="img" aria-label="{{ __('site.home.photo') }}" style="background-image:url('{{ asset('images/site/hero.jpg') }}')"></div>
</section>

<section class="tiles" aria-label="{{ __('site.home.what') }}">
  <a class="tile dark" href="{{ route('site.dryhire') }}"><div class="txt"><h2>{{ __('site.nav.dryhire') }}</h2><div class="lab">{{ __('site.home.dryhire_t') }}</div></div><div class="img" style="background-image:url('{{ asset('images/site/dryhire.jpg') }}')"></div><div class="cta"><span>{{ __('site.home.dryhire_c') }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
  <a class="tile light" id="sales" href="{{ $sales }}"><div class="txt"><h2>{{ __('site.nav.sales') }}</h2><div class="lab">{{ __('site.home.sales_t') }}</div></div><div class="img" style="background-image:url('{{ asset('images/site/sales.jpg') }}')"></div><div class="cta"><span>{{ __('site.home.sales_c') }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
  <a class="tile dark" href="{{ $app }}/book?lang={{ app()->getLocale() }}"><div class="txt"><h2>{{ __('site.nav.factory') }}</h2><div class="lab">{{ __('site.home.factory_t') }}</div></div><div class="img" style="background-image:url('{{ asset('images/site/factory.jpg') }}')"></div><div class="cta"><span>{{ __('site.home.factory_c') }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
  <a class="tile light" href="{{ $app }}/afrit6"><div class="txt"><h2>{{ __('site.nav.afrit6') }}</h2><div class="lab">{{ __('site.home.afrit6_t') }}</div></div><div class="img" style="background-image:url('{{ asset('images/site/afrit6.jpg') }}')"></div><div class="cta"><span>{{ __('site.home.afrit6_c') }}</span>@include('site._icon', ['i' => 'arrow'])</div></a>
</section>

<section class="home-news gut">
  <div class="intro">
    <h2>{{ __('site.home.news_title') }}</h2>
    <p class="lab" style="margin:0">{{ __('site.home.news_text') }}</p>
    <div class="bar"></div>
    <a class="follow" href="{{ config('website.social.instagram') }}" target="_blank" rel="noopener"><span>{{ __('site.home.follow') }}</span>@include('site._icon', ['i' => 'arrow'])</a>
  </div>
  <a class="post big" href="{{ config('website.social.instagram') }}" target="_blank" rel="noopener"><div class="img" style="background-image:url('{{ asset('images/site/n1.jpg') }}')"></div><div class="src">@include('site._icon', ['i' => 'ig'])Instagram</div><h3><span>{{ __('site.home.posts.0') }}</span>@include('site._icon', ['i' => 'arrow'])</h3></a>
  <div class="side">
    <a class="post" href="{{ config('website.social.facebook') }}" target="_blank" rel="noopener"><div class="img" style="background-image:url('{{ asset('images/site/n2.jpg') }}')"></div><div class="src">@include('site._icon', ['i' => 'fb'])Facebook</div><h3><span>{{ __('site.home.posts.1') }}</span>@include('site._icon', ['i' => 'arrow'])</h3></a>
    <a class="post" href="{{ config('website.social.linkedin') }}" target="_blank" rel="noopener"><div class="img" style="background-image:url('{{ asset('images/site/n3.jpg') }}')"></div><div class="src">@include('site._icon', ['i' => 'li'])LinkedIn</div><h3><span>{{ __('site.home.posts.2') }}</span>@include('site._icon', ['i' => 'arrow'])</h3></a>
  </div>
</section>
</main>
@endsection
