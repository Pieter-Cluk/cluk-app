@extends('site.layout', ['active' => 'sales'])
@php
    $i = $d['item'];
    $eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c);
    $cat = $i->productCategory;
    $sub = $i->productSubcategory;
    $photo = $i->photo_path ? route('site.sales.photo', ['item' => $i->id, 'w' => 1200]).'?v='.substr(md5($i->photo_path), 0, 8) : null;
    $tagSlugs = array_column($d['tags'], 'slug');
    $ld = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $i->name(), 'sku' => $i->number, 'mpn' => $i->manufacturer_code, 'gtin13' => $i->ean,
        'brand' => $i->brand ? ['@type' => 'Brand', 'name' => $i->brand] : null, 'description' => $i->description, 'image' => $photo,
        'offers' => $i->list_price_cents > 0 ? ['@type' => 'Offer', 'priceCurrency' => 'EUR', 'price' => number_format($i->list_price_cents / 100, 2, '.', ''),
            'availability' => $i->stock_qty > 0 ? 'https://schema.org/InStock' : 'https://schema.org/BackOrder'] : null,
    ]);
@endphp
@section('title', $i->name().' · '.__('site.sales.title'))
@section('description', \Illuminate\Support\Str::limit((string) ($i->description ?: $i->name()), 155))
@section('body', 'sl')
@section('content')
@include('site.sales._bar')
<main class="swrap gut">
  <nav class="crumbs" aria-label="breadcrumb"><a href="{{ route('site.sales') }}">{{ __('site.sales.home') }}</a>
    @if ($cat)<span>›</span><a href="{{ route('site.sales.category', $cat->slug) }}">{{ $cat->label($locale) }}</a>@endif
    @if ($sub && $sub->slug !== 'general')<span>›</span><a href="{{ route('site.sales.category', [$cat->slug, $sub->slug]) }}">{{ $sub->label($locale) }}</a>@endif
  </nav>
  <div class="pgrid">
    <div class="pphoto">
      @if ($photo)<img src="{{ $photo }}" alt="{{ $i->name() }}">@else<span class="sph-none big"><span class="b">{{ $i->brand }}</span><span class="t">{{ $i->type }}</span></span>@endif
      <span class="badges">@if (in_array('promotion', $tagSlugs, true))<span class="tag promo-t">{{ __('site.sales.tag_promo') }}</span>@endif @if (in_array('new', $tagSlugs, true))<span class="tag new-t">{{ __('site.sales.tag_new') }}</span>@endif</span>
    </div>
    <div class="pinfo">
      @if ($i->brandRef)<a class="pbrand" href="{{ route('site.sales.brand', $i->brandRef->slug) }}">{{ $i->brand }}</a>@endif
      <h1>{{ $i->name() }}</h1>
      <dl class="pcodes"><dt>{{ __('site.sales.order_code') }}</dt><dd>{{ $i->number }}</dd>
        @if ($i->manufacturer_code)<dt>{{ __('site.sales.mfr_code') }}</dt><dd>{{ $i->manufacturer_code }}</dd>@endif
        @if ($i->ean)<dt>{{ __('site.sales.ean') }}</dt><dd>{{ $i->ean }}</dd>@endif
        @if ($d['size'])<dt>{{ __('site.sales.size') }}</dt><dd class="num">{{ $d['size'] }}</dd>@endif
        @if ($d['weight'])<dt>{{ __('site.sales.weight') }}</dt><dd class="num">{{ $d['weight'] }}</dd>@endif</dl>
      @if ($i->description)<p class="pdesc">{{ $i->description }}</p>@endif
      @if ($d['options'])<div class="chips-s">@foreach ($d['options'] as [$k, $v])<span>{{ $k }}: {{ $v }}</span>@endforeach</div>@endif
      <div class="pbuy">
        @if ($i->list_price_cents > 0)
          <div class="pprice num">{{ $eur($d['price']) }} <small>{{ __('site.sales.excl_vat') }}</small></div>
          @if ($d['price'] < $i->list_price_cents)<div class="plist num">{{ __('site.sales.your_price') }} · {{ __('site.sales.list_price') }} <s>{{ $eur($i->list_price_cents) }}</s></div>@endif
        @else
          <div class="pprice onreq">{{ __('site.sales.on_request') }}</div>
        @endif
        <div class="avail {{ $i->stock_qty > 0 ? 'ok' : '' }} num">{{ $i->stock_qty > 0 ? __('site.sales.in_stock', ['n' => $i->stock_qty]) : __('site.sales.lead') }}</div>
        <div class="row2">
          <div class="qty"><button type="button" data-step="-1" aria-label="{{ __('site.sjs.one_less') }}">−</button><input id="pQty" type="number" min="1" value="1" aria-label="{{ __('site.sales.qty') }}"><button type="button" data-step="1" aria-label="{{ __('site.sjs.one_more') }}">+</button></div>
          <button class="btn" type="button" id="pAdd" data-add="{{ $i->id }}" data-name="{{ $i->name() }}" data-price="{{ $i->list_price_cents > 0 ? $d['price'] : 0 }}" data-code="{{ $i->number }}">{{ $i->list_price_cents > 0 ? __('site.sales.add') : __('site.sales.ask_price') }}</button>
        </div>
      </div>
      @if ($d['documents'])
        <div><h2 class="eyebrow">{{ __('site.sales.documents') }}</h2><div class="docs">@foreach ($d['documents'] as $doc)<a href="{{ route('site.sales.document', $doc['id']) }}" target="_blank" rel="noopener">⬇ {{ __('site.sales.docs.'.$doc['kind']) }}</a>@endforeach</div></div>
      @endif
    </div>
  </div>
  @if ($d['related']->isNotEmpty())
    <section class="sblock" id="related"><div class="shead"><h2>{{ __('site.sales.related', ['sub' => $sub && $sub->slug !== 'general' ? $sub->label($locale) : ($cat?->label($locale) ?? '')]) }}</h2></div>
      <div class="sgrid">@foreach ($d['related'] as $r)@include('site.sales._card', ['i' => $r])@endforeach</div></section>
  @endif
</main>
<script type="application/ld+json">@json($ld)</script>
@include('site.sales._basket')
@endsection
