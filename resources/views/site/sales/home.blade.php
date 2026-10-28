@extends('site.layout', ['active' => 'sales'])
@section('title', \App\Services\Website\SiteSettings::text('sales.title'))
@section('description', __('site.sales.meta'))
@section('body', 'sl')
@section('content')
@include('site.sales._bar')
<main class="swrap gut" id="top">
  <section class="promo" aria-label="{{ __('site.sales.news') }}">
    <div class="newsf">
      <h2>{{ __('site.sales.news') }}</h2>
      @if ($news->isNotEmpty())
      <ol>
        @foreach ($news as $p)
          <li><a href="{{ $p['url'] }}" target="_blank" rel="noopener"><span class="meta">@if ($p['topic'] ?? null)<span class="tag {{ ['promo' => 'promo-t', 'new' => 'new-t'][$p['topic']] ?? '' }}">{{ __('site.sales.tag_'.$p['topic']) }}</span>@endif @include('site._icon', ['i' => $p['platform'] === 'instagram' ? 'ig' : 'fb'])<time datetime="{{ $p['date']->toIso8601String() }}">{{ $p['date']->locale(app()->getLocale())->translatedFormat('j M') }}</time></span><b>{{ $p['title'] ?: (\Illuminate\Support\Str::limit($p['text'], 80) ?: ucfirst((string) $p['platform']).' · '.$p['date']->locale(app()->getLocale())->translatedFormat('j F Y')) }}</b></a></li>
        @endforeach
      </ol>
      @else
      <p class="muted">{{ __('site.sales.no_news') }}</p>
      @endif
      <a class="all" href="{{ route('site.news', ['part' => 'sales']) }}">{{ __('site.sales.all_news') }} <span aria-hidden="true">→</span></a>
    </div>
    @php($own = \App\Services\Website\SiteSettings::slides('sales_slider'))
    @if ($own->isNotEmpty())
    <div class="slider" id="slider" aria-roledescription="carousel" aria-label="{{ __('site.sales.news') }}" data-seconds="{{ (int) \App\Services\Website\SiteSettings::get('general.slide_seconds', 3) }}">
      @foreach ($own as $k => $s)
        @php($go = \App\Services\Website\SiteSettings::link($s->link_type, $s->link_value))
        @php($src = \App\Services\Website\SiteSettings::mediaUrl($s->path))
        <div class="slide {{ $k === 0 ? 'on' : '' }}" @if ($s->kind === 'image') style="background-image:url('{{ $src }}')@if ($s->mobile_path);--bg-phone:url('{{ \App\Services\Website\SiteSettings::mediaUrl($s->mobile_path) }}')@endif" @endif aria-roledescription="slide" aria-label="{{ $s->alt ?: __('site.sales.slide', ['n' => $k + 1]) }}">
          @if ($s->kind === 'video')<video class="bg" src="{{ $src }}" @if ($s->poster_path) poster="{{ \App\Services\Website\SiteSettings::mediaUrl($s->poster_path) }}" @endif muted loop playsinline preload="metadata" @if ($k === 0) autoplay @endif></video>@endif
          @if ($go)<a class="slide-link" href="{{ $go }}" aria-label="{{ $s->text('title') ?: ($s->alt ?: __('site.sales.slide', ['n' => $k + 1])) }}" @if ($s->text('cta')) tabindex="-1" @endif></a>@endif
          @if ($s->text('title') || $s->text('text') || $s->text('eyebrow'))
          <div class="txt">@if ($s->text('eyebrow'))<span class="eyebrow">{{ $s->text('eyebrow') }}</span>@endif @if ($s->text('title'))<h3>{{ $s->text('title') }}</h3>@endif @if ($s->text('text'))<p>{{ $s->text('text') }}</p>@endif @if ($go && $s->text('cta'))<div><a class="btn" href="{{ $go }}">{{ $s->text('cta') }}</a></div>@endif</div>
          @endif
        </div>
      @endforeach
      @if ($own->count() > 1)
      <div class="ctrl"><div class="dots" id="dots"></div><div class="arrows"><button id="prev" type="button" aria-label="{{ __('site.sales.prev') }}">‹</button><button id="pause" type="button" aria-label="{{ __('site.sales.pause') }}" data-play="{{ __('site.sales.play') }}" data-pause="{{ __('site.sales.pause') }}">❚❚</button><button id="next" type="button" aria-label="{{ __('site.sales.next') }}">›</button></div></div>
      @endif
    </div>
    @else
    <div class="slider" id="slider" aria-roledescription="carousel" aria-label="{{ __('site.sales.news') }}">
      @foreach (__('site.sales.slides') as $k => $s)
        <div class="slide {{ $k === 0 ? 'on' : '' }}" style="background-image:url('{{ asset('images/site/'.['sales', 'n1', 'hero'][$k % 3].'.jpg') }}')" aria-roledescription="slide" aria-label="{{ __('site.sales.slide', ['n' => $k + 1]) }}">
          <div class="txt"><span class="eyebrow">{{ $s['eyebrow'] }}</span><h3>{{ $s['title'] }}</h3><p>{{ $s['text'] }}</p><div><a class="btn" href="{{ [route('site.sales.new'), route('site.sales.new'), route('site.sales.search')][$k % 3] }}">{{ $s['cta'] }}</a></div></div>
        </div>
      @endforeach
      <div class="ctrl"><div class="dots" id="dots"></div><div class="arrows"><button id="prev" type="button" aria-label="{{ __('site.sales.prev') }}">‹</button><button id="pause" type="button" aria-label="{{ __('site.sales.pause') }}" data-play="{{ __('site.sales.play') }}" data-pause="{{ __('site.sales.pause') }}">❚❚</button><button id="next" type="button" aria-label="{{ __('site.sales.next') }}">›</button></div></div>
    </div>
    @endif
  </section>

  <section class="sblock">
    <div class="shead"><h2>{{ __('site.sales.popular') }}</h2><span class="muted num">{{ __('site.sales.products_n', ['n' => number_format($total, 0, ',', '.')]) }}</span></div>
    @if ($tree->isEmpty())
      <p class="muted">{{ __('site.sales.empty') }}</p>
    @else
      <div class="cattiles">
        @foreach ($tree as $c)
          <a class="cattile" href="{{ route('site.sales.category', $c->slug) }}"><span class="code">{{ $c->code }}</span><span class="nm">{{ $c->label($locale) }}</span><span class="n num">{{ __('site.sales.products_n', ['n' => $c->n]) }}</span></a>
        @endforeach
      </div>
    @endif
  </section>

  @if ($brands->isNotEmpty())
  <section class="sblock">
    <div class="shead"><h2>{{ __('site.sales.our_brands') }}</h2><a href="{{ route('site.sales.brands') }}">{{ __('site.sales.all_brands') }} →</a></div>
    <div class="brandrow">
      @foreach ($brands->take(12) as $b)
        <a class="brandtile" href="{{ route('site.sales.brand', $b->slug) }}" title="{{ $b->name }}">@if ($b->logo_path)<img src="{{ route('brand.logo', $b) }}" alt="{{ $b->name }}" loading="lazy">@else<span>{{ $b->name }}</span>@endif</a>
      @endforeach
    </div>
  </section>
  @endif

  @if ($new->isNotEmpty())
  <section class="sblock">
    <div class="shead"><h2>{{ __('site.sales.new_in') }}</h2><a href="{{ route('site.sales.new') }}">{{ __('site.sales.see_all') }} →</a></div>
    <div class="sgrid">@foreach ($new as $i)@include('site.sales._card', ['i' => $i])@endforeach</div>
  </section>
  @endif
</main>
@include('site.sales._basket')
@endsection
