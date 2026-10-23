@extends('site.layout', ['active' => 'sales'])
@section('title', __('site.sales.title'))
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
          <li><a href="{{ $p['url'] }}" target="_blank" rel="noopener"><span class="meta">@include('site._icon', ['i' => $p['platform'] === 'instagram' ? 'ig' : 'fb'])<time datetime="{{ $p['date']->toIso8601String() }}">{{ $p['date']->locale(app()->getLocale())->translatedFormat('j M') }}</time></span><b>{{ $p['title'] ?: \Illuminate\Support\Str::limit($p['text'], 80) }}</b></a></li>
        @endforeach
      </ol>
      @else
      <ol>
        @foreach (__('site.sales.news_items') as $k => $n)
          <li><a href="{{ route('site.sales.new') }}"><span class="meta"><span class="tag {{ ['promo-t', 'new-t', '', ''][$k] ?? '' }}">{{ __('site.sales.'.['tag_promo', 'tag_new', 'tag_event', 'tag_tip'][$k % 4]) }}</span></span><b>{{ $n }}</b></a></li>
        @endforeach
      </ol>
      @endif
      <a class="all" href="{{ route('site.news', ['part' => 'sales']) }}">{{ __('site.sales.all_news') }} <span aria-hidden="true">→</span></a>
    </div>
    <div class="slider" id="slider" aria-roledescription="carousel" aria-label="{{ __('site.sales.news') }}">
      @foreach (__('site.sales.slides') as $k => $s)
        <div class="slide {{ $k === 0 ? 'on' : '' }}" style="background-image:url('{{ asset('images/site/'.['sales', 'n1', 'hero'][$k % 3].'.jpg') }}')" aria-roledescription="slide" aria-label="{{ __('site.sales.slide', ['n' => $k + 1]) }}">
          <div class="txt"><span class="eyebrow">{{ $s['eyebrow'] }}</span><h3>{{ $s['title'] }}</h3><p>{{ $s['text'] }}</p><div><a class="btn" href="{{ [route('site.sales.new'), route('site.sales.new'), route('site.sales.search')][$k % 3] }}">{{ $s['cta'] }}</a></div></div>
        </div>
      @endforeach
      <div class="ctrl"><div class="dots" id="dots"></div><div class="arrows"><button id="prev" type="button" aria-label="{{ __('site.sales.prev') }}">‹</button><button id="pause" type="button" aria-label="{{ __('site.sales.pause') }}" data-play="{{ __('site.sales.play') }}" data-pause="{{ __('site.sales.pause') }}">❚❚</button><button id="next" type="button" aria-label="{{ __('site.sales.next') }}">›</button></div></div>
    </div>
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
