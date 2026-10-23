@extends('site.layout', ['active' => 'sales'])
@section('title', strip_tags($title).' · '.__('site.sales.title'))
@section('description', ($intro ?? null) ? \Illuminate\Support\Str::limit(strip_tags($intro), 155) : __('site.sales.meta'))
@section('body', 'sl')
@section('content')
@php
    $items = $list['items'];
    $fx = $list['facets'];
    $req = request();
@endphp
@include('site.sales._bar')
<main class="swrap gut">
  <nav class="crumbs" aria-label="breadcrumb">
    <a href="{{ route('site.sales') }}">{{ __('site.sales.home') }}</a>
    @if ($mode === 'category')
      <span>›</span>@if ($subcategory)<a href="{{ route('site.sales.category', $category->slug) }}">{{ $category->label($locale) }}</a><span>›</span><span>{{ $subcategory->label($locale) }}</span>@else<span>{{ $category->label($locale) }}</span>@endif
    @elseif ($mode === 'brand')
      <span>›</span><a href="{{ route('site.sales.brands') }}">{{ __('site.sales.brands') }}</a><span>›</span><span>{{ $brand->name }}</span>
    @elseif ($mode === 'new')
      <span>›</span><span>{{ __('site.sales.new_items') }}</span>
    @endif
  </nav>
  <header class="lhead">
    @if ($mode === 'brand' && $brand->logo_path)<img class="blogo" src="{{ route('brand.logo', $brand) }}" alt="{{ $brand->name }}">@endif
    <h1>{{ $title }}</h1>
    @if (! empty($intro))<p class="intro">{{ $intro }}</p>@endif
  </header>

  @if (isset($tiles) && $tiles->isNotEmpty())
    <div class="subtiles">
      @foreach ($tiles as $s)<a class="subtile" href="{{ route('site.sales.category', [$category->slug, $s->slug]) }}"><span class="nm">{{ $s->label($locale) }}</span><span class="n num">{{ __('site.sales.products_n', ['n' => $s->n]) }}</span></a>@endforeach
    </div>
  @endif

  <div class="lgrid">
    <aside class="filters" id="filters" aria-label="{{ __('site.sales.filters') }}">
      <form id="ff" method="get" action="{{ url()->current() }}">
        <div class="fhead"><h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>{{ __('site.sales.filters') }}</h2>
          <button class="x" type="button" id="fClose" aria-label="{{ __('site.sjs.close') }}">✕</button></div>
        @if (! empty($q))<input type="hidden" name="q" value="{{ $q }}">@endif

        @if ($fx['subs']->isNotEmpty())
          <details class="fblock" open><summary>{{ __('site.sales.type') }}</summary>
            @foreach ($fx['subs'] as $o)<label class="opt"><input type="checkbox" name="subs[]" value="{{ $o['id'] }}" @checked($o['on'])>{{ $o['name'] }}<span class="c">({{ $o['n'] }})</span></label>@endforeach
          </details>
        @endif
        @if ($mode !== 'brand' && $fx['brands']->isNotEmpty())
          <details class="fblock" open><summary>{{ __('site.sales.brand') }}</summary>
            <div class="more-list" data-limit="8">
              @foreach ($fx['brands'] as $o)<label class="opt"><input type="checkbox" name="brands[]" value="{{ $o['id'] }}" @checked($o['on'])>{{ $o['name'] }}<span class="c">({{ $o['n'] }})</span></label>@endforeach
            </div>
          </details>
        @endif
        <details class="fblock" {{ $fx['stock']['on'] ? 'open' : '' }}><summary>{{ __('site.sales.availability') }}</summary>
          <label class="opt"><input type="checkbox" name="stock" value="1" @checked($fx['stock']['on'])>{{ __('site.sales.in_stock_now') }}<span class="c">({{ $fx['stock']['n'] }})</span></label>
        </details>
        @if ($fx['tags']->isNotEmpty())
          <details class="fblock" open><summary>{{ __('site.sales.show') }}</summary>
            @foreach ($fx['tags'] as $o)<label class="opt"><input type="checkbox" name="tags[]" value="{{ $o['id'] }}" @checked($o['on'])>{{ $o['name'] }}<span class="c">({{ $o['n'] }})</span></label>@endforeach
          </details>
        @endif
        @foreach ($fx['attrs'] as $a)
          <details class="fblock" {{ collect($a['options'])->contains('on', true) ? 'open' : '' }}><summary>{{ $a['name'] }}</summary>
            <div class="more-list" data-limit="8">
              @foreach ($a['options'] as $o)<label class="opt"><input type="checkbox" name="opts[]" value="{{ $o['id'] }}" @checked($o['on'])>{{ $o['name'] }}<span class="c">({{ $o['n'] }})</span></label>@endforeach
            </div>
          </details>
        @endforeach
        @if ($fx['price']['max'])
          <details class="fblock" {{ $fx['price']['from'] !== '' || $fx['price']['to'] !== '' ? 'open' : '' }}><summary>{{ __('site.sales.price') }}</summary>
            <div class="prange">
              <label><span>{{ __('site.sales.from') }}</span><input type="number" name="min" min="0" step="1" inputmode="decimal" value="{{ $fx['price']['from'] }}" placeholder="{{ floor($fx['price']['min'] / 100) }}"></label>
              <label><span>{{ __('site.sales.to') }}</span><input type="number" name="max" min="0" step="1" inputmode="decimal" value="{{ $fx['price']['to'] }}" placeholder="{{ ceil($fx['price']['max'] / 100) }}"></label>
            </div>
          </details>
        @endif
        <div class="factions"><button class="btn" type="submit">{{ __('site.sales.apply') }}</button>
          @if ($list['active'])<a class="clearf" href="{{ url()->current() }}{{ ! empty($q) ? '?q='.urlencode($q) : '' }}">{{ __('site.sales.clear') }}</a>@endif</div>
      </form>
    </aside>

    <section class="lmain" aria-live="polite">
      <div class="ltool">
        <span class="count num">{{ __('site.sales.results', ['n' => number_format($list['total'], 0, ',', '.')]) }}</span>
        <button class="btn ghost fbtn" type="button" id="fOpen">{{ __('site.sales.filters') }}</button>
        <div class="views" role="group"><button type="button" data-view="grid" aria-pressed="true">▦ {{ __('site.sales.grid') }}</button><button type="button" data-view="list" aria-pressed="false">☰ {{ __('site.sales.list') }}</button></div>
        <label class="sortl"><span>{{ __('site.sales.sort') }}</span>
          <select id="sortSel" form="ff" name="sort">
            @foreach (['rel', 'new', 'pa', 'pd', 'az'] as $s)<option value="{{ $s === 'rel' ? '' : $s }}" @selected(($req->query('sort') ?: 'rel') === $s)>{{ __('site.sales.sort_'.$s) }}</option>@endforeach
          </select></label>
      </div>
      @if ($items->isEmpty())
        <div class="empty"><h3>{{ ! empty($q) ? __('site.sales.nothing', ['q' => $q]) : __('site.sales.empty') }}</h3><p>{{ __('site.sales.nothing_text') }}</p></div>
      @else
        <div class="sgrid" id="sgrid">@foreach ($items as $i)@include('site.sales._card', ['i' => $i])@endforeach</div>
        @if ($items->lastPage() > 1)
          <nav class="pager" aria-label="pagination">
            @if ($items->currentPage() > 1)<a href="{{ $req->fullUrlWithQuery(['page' => $items->currentPage() - 1]) }}" rel="prev">‹ {{ __('site.sales.prev') }}</a>@endif
            @for ($p = max(1, $items->currentPage() - 2); $p <= min($items->lastPage(), $items->currentPage() + 2); $p++)
              <a href="{{ $req->fullUrlWithQuery(['page' => $p]) }}" @if ($p === $items->currentPage()) aria-current="page" @endif>{{ $p }}</a>
            @endfor
            @if ($items->hasMorePages())<a href="{{ $req->fullUrlWithQuery(['page' => $items->currentPage() + 1]) }}" rel="next">{{ __('site.sales.next') }} ›</a>@endif
          </nav>
        @endif
      @endif
    </section>
  </div>
</main>
@include('site.sales._basket')
@endsection
