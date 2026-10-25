@extends('site.layout', ['active' => 'dryhire'])
@section('title', $d['name'].' · '.__('site.dryhire.title'))
@section('description', \Illuminate\Support\Str::limit(strip_tags((string) $d['description']) ?: $d['name'].' · '.$d['subcategory'], 155))
@section('body', 'dh')
@section('content')
@php
    $eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c);
    $ld = array_filter([
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $d['name'], 'sku' => $d['number'],
    'brand' => $d['brand'] ? ['@type' => 'Brand', 'name' => $d['brand']] : null, 'description' => $d['description'], 'image' => $d['photos'][0] ?? null, 'category' => $d['category'].' / '.$d['subcategory'],
]);
@endphp
<div class="item-page gut">
  <div class="gallery">
    @if ($d['photos'])
      <div class="photo"><img id="mainPhoto" src="{{ $d['photos'][0] }}" alt="{{ $d['name'] }}"></div>
      @if (count($d['photos']) > 1)
        <div class="thumbs">@foreach ($d['photos'] as $k => $p)<button type="button" data-src="{{ $p }}" aria-current="{{ $k === 0 ? 'true' : 'false' }}"><img src="{{ $p }}" alt="" loading="lazy"></button>@endforeach</div>
      @endif
    @else
      <div class="plate big"><div class="top"><span class="code">{{ $item->productCategory?->code }}</span><span class="brand">{{ $d['brand'] ?: $d['subcategory'] }}</span></div><div class="type">{{ $d['type'] ?: $d['name'] }}</div></div>
    @endif
  </div>
  <div style="display:grid;gap:18px;align-content:start">
    <nav class="crumbs" aria-label="breadcrumb"><a href="{{ route('site.dryhire') }}">{{ __('site.dryhire.title') }}</a><span>›</span><a href="{{ route('site.dryhire') }}?cat={{ $item->productCategory?->slug }}">{{ $d['category'] }}</a><span>›</span><span>{{ $d['subcategory'] }}</span></nav>
    <div><h1>{{ $d['name'] }}</h1><span class="note" style="font-family:ui-monospace,Menlo,monospace">{{ $d['number'] }}</span></div>
    @if ($d['description'])<p class="desc">{{ $d['description'] }}</p>@endif
    @if ($d['options'] || $d['tags'])<div class="chips-s">@foreach ($d['options'] as [$k, $v])<span>{{ $k }}: {{ $v }}</span>@endforeach @foreach ($d['tags'] as $t)<span>{{ $t }}</span>@endforeach</div>@endif
    <dl class="specs num">
      @if ($d['brand'])<dt>{{ __('site.js.l_brand') }}</dt><dd>{{ $d['brand'] }}</dd>@endif
      @if ($d['qty'] > 0)<dt>{{ __('site.js.l_pieces') }}</dt><dd><b style="color:var(--green)">{{ $d['qty'] }}</b></dd>@endif
      @if ($d['weight'])<dt>{{ __('site.js.l_weight') }}</dt><dd>{{ $d['weight'] }}</dd>@endif
      @if ($d['size'])<dt>{{ __('site.js.l_size') }}</dt><dd>{{ $d['size'] }}</dd>@endif
      <dt>{{ __('site.js.l_price') }}</dt><dd>@if ($d['price'] > 0 && ($disc ?? 0))<b>{{ $eur((int) round($d['price'] * (1 - $disc / 10000))) }}</b> {{ __('site.js.per_day') }} · {{ __('site.dryhire.your_price') }} ({{ __('site.dryhire.list_price') }} {{ $eur($d['price']) }})@elseif ($d['price'] > 0)<b>{{ $eur($d['price']) }}</b> {{ __('site.js.per_day') }} · {{ __('site.js.price_note') }}@else{{ __('site.js.on_request') }}@endif</dd>
    </dl>
    @if ($d['contents'])<div><h3 class="eyebrow" style="color:var(--muted);margin:0 0 8px">{{ __('site.js.contents') }}</h3><ul style="margin:0;padding-left:18px">@foreach ($d['contents'] as [$q, $n])<li>{{ $q }} × {{ $n }}</li>@endforeach</ul></div>@endif
    <div><a class="btn" href="{{ route('site.dryhire') }}?add={{ $d['id'] }}">{{ __('site.item.request') }}</a></div>
    @if ($d['related'])
      <div><h3 class="eyebrow" style="color:var(--muted);margin:0 0 8px">{{ __('site.item.similar', ['sub' => $d['subcategory']]) }}</h3>
        <div class="related">@foreach ($d['related'] as $r)<a href="{{ route('site.dryhire.item', $r['id'].'-'.\Illuminate\Support\Str::slug($r['name'])) }}"><span>{{ $r['name'] }}</span><span class="num">{{ $r['p'] ? $eur($r['p']) : __('site.js.on_request') }}</span></a>@endforeach</div></div>
    @endif
    <p><a href="{{ route('site.dryhire') }}" class="note">← {{ __('site.dryhire.back') }}</a></p>
  </div>
</div>
<script type="application/ld+json">@json($ld)</script>
@endsection
@push('scripts')
<script>document.querySelectorAll('.thumbs button').forEach(b=>b.addEventListener('click',()=>{document.getElementById('mainPhoto').src=b.dataset.src;document.querySelectorAll('.thumbs button').forEach(x=>x.setAttribute('aria-current',x===b))}))</script>
@endpush
