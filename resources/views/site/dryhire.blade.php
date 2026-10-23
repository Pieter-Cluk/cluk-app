@extends('site.layout', ['active' => 'dryhire'])
@section('title', __('site.dryhire.title'))
@section('description', __('site.dryhire.meta', ['n' => number_format($data['stats']['products'], 0, ',', '.')]))
@section('body', 'dh')
@section('content')
@php
    $nf = fn ($n) => number_format($n, 0, ',', '.');
@endphp
<section class="dh-hero" id="top">
  <div class="ph" aria-hidden="true" style="background-image:url('{{ asset('images/site/dryhire.jpg') }}')"></div>
  <div class="in gut">
    <div class="eyebrow">{{ __('site.dryhire.eyebrow') }}</div>
    <h1>{{ __('site.dryhire.title') }}</h1>
    <div class="stats">
      @if ($data['stats']['pieces'] > 0)<div class="stat num"><b>{{ $nf($data['stats']['pieces']) }}</b><span>{{ __('site.dryhire.stat_pieces') }}</span></div>@endif
      <div class="stat num"><b>{{ $nf($data['stats']['products']) }}</b><span>{{ __('site.dryhire.stat_products') }}</span></div>
      <div class="stat num"><b>{{ $nf($data['stats']['brands']) }}</b><span>{{ __('site.dryhire.stat_brands') }}</span></div>
      <div class="stat num"><b>{{ $data['stats']['departments'] }}</b><span>{{ __('site.dryhire.stat_departments') }}</span></div>
    </div>
    <div class="searchbox">
      <label for="q" class="sr">{{ __('site.dryhire.search_label') }}</label>
      <svg class="mag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input id="q" type="search" autocomplete="off" enterkeyhint="search" placeholder="{{ __('site.dryhire.search_placeholder', ['n' => $nf($data['stats']['products'])]) }}">
      <kbd id="kbd">{{ __('site.dryhire.press') }}</kbd>
      <button class="clear" id="clear" type="button" hidden>{{ __('site.dryhire.clear') }}</button>
    </div>
    <div class="suggest"><span style="opacity:.75">{{ __('site.dryhire.popular') }}</span>
      @foreach (config('website.popular') as $p)<button type="button" data-q="{{ $p }}">{{ $p }}</button>@endforeach
    </div>
  </div>
</section>

<nav class="catbar" aria-label="{{ __('site.dryhire.categories') }}"><div class="catrow gut"><div class="row" id="cats">
  @foreach ($data['cats'] as $c)<a class="chip" href="?cat={{ $c['slug'] }}">{{ $c['name'] }}</a>@endforeach
</div>
  <button class="reqbtn" id="fab" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16l-1.5 12h-13z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg><span class="bl">{{ __('site.js.request_btn') }}</span><span class="n num" id="fabN">0</span></button>
</div></nav>

<div class="dh-main gut">
  <aside id="aside" aria-label="{{ __('site.dryhire.filters') }}">
    <div class="row2" id="asideHead" hidden><h3 class="eyebrow" style="margin:0">{{ __('site.dryhire.filters') }}</h3><button class="x" id="asideClose" type="button" aria-label="{{ __('site.dryhire.close_filters') }}">✕</button></div>
    <div id="fSubs"></div>
    <div id="fAttrs"></div>
    <div id="fTags"></div>
    <div><h3 class="eyebrow">{{ __('site.dryhire.brand') }}</h3><div id="fBrands"></div></div>
  </aside>

  <main>
    <div class="toolbar">
      <div class="count" id="count" aria-live="polite"></div>
      <button class="btn ghost filters-btn" id="filtersBtn" type="button">{{ __('site.dryhire.filters') }}</button>
      <label for="sort" class="sr">{{ __('site.dryhire.sort') }}</label>
      <select class="sel" id="sort">
        <option value="rel">{{ __('site.dryhire.sort_rel') }}</option><option value="pa">{{ __('site.dryhire.sort_pa') }}</option>
        <option value="pd">{{ __('site.dryhire.sort_pd') }}</option><option value="az">{{ __('site.dryhire.sort_az') }}</option>
      </select>
      <div class="views" role="group" aria-label="{{ __('site.dryhire.view') }}">
        <button id="vGrid" type="button" aria-pressed="true" aria-label="{{ __('site.dryhire.grid') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/></svg></button>
        <button id="vList" type="button" aria-pressed="false" aria-label="{{ __('site.dryhire.list') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      </div>
    </div>
    <div class="active-filters" id="active" hidden></div>
    <div id="results">
      <noscript><p>{{ __('site.dryhire.noscript') }}</p>
        <ul>@foreach ($data['items'] as $i)<li><a href="{{ route('site.dryhire.item', $i['sl']) }}">{{ $i['nm'] }}</a></li>@endforeach</ul></noscript>
    </div>
    <div id="sentinel" aria-hidden="true" style="height:1px"></div>
  </main>
</div>

<div class="scrim" id="scrim"></div>
<div class="drawer" id="pd" role="dialog" aria-modal="true" aria-labelledby="pdTitle"></div>
<div class="drawer" id="bk" role="dialog" aria-modal="true" aria-labelledby="bkTitle"></div>

<script type="application/json" id="dh-data">@json($data)</script>
<script type="application/json" id="dh-cfg">@json($cfg)</script>
@endsection
@push('scripts')
<script src="{{ asset('js/site-dryhire.js') }}?v={{ @filemtime(public_path('js/site-dryhire.js')) }}" defer></script>
@endpush
