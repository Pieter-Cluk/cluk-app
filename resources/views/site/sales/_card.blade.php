{{-- One product in a list. $i has price_cents set (customer price or list price). --}}
@php
    $eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c);
    $url = route('site.sales.product', \App\Services\Website\SalesCatalogue::slug($i));
    $tagSlugs = $i->relationLoaded('tags') ? $i->tags->pluck('slug')->all() : [];
@endphp
<article class="scard" data-id="{{ $i->id }}">
  <a class="sphoto" href="{{ $url }}" tabindex="-1" aria-hidden="true">
    @if ($i->photo_path)<img src="{{ route('site.sales.photo', ['item' => $i->id, 'w' => 480]) }}?v={{ substr(md5($i->photo_path), 0, 8) }}" alt="" loading="lazy" decoding="async">
    @else<span class="sph-none"><span class="b">{{ $i->brand }}</span><span class="t">{{ $i->type }}</span></span>@endif
    <span class="badges">@if (in_array('promotion', $tagSlugs, true))<span class="tag promo-t">{{ __('site.sales.tag_promo') }}</span>@endif @if (in_array('new', $tagSlugs, true))<span class="tag new-t">{{ __('site.sales.tag_new') }}</span>@endif</span>
  </a>
  <div class="sbody">
    <h3><a href="{{ $url }}">{{ $i->name() }}</a></h3>
    <div class="codes"><span>{{ __('site.sales.order_code') }}: <b>{{ $i->number }}</b></span>@if ($i->manufacturer_code)<span>{{ $i->manufacturer_code }}</span>@endif</div>
    <div class="smeta">
      <div>
        @if ($i->list_price_cents > 0)
          <div class="sprice num">{{ $eur($i->price_cents) }}@if ($i->price_cents < $i->list_price_cents) <s>{{ $eur($i->list_price_cents) }}</s>@endif</div>
          <div class="svat">{{ $i->price_cents < $i->list_price_cents ? __('site.sales.your_price').' · ' : '' }}{{ __('site.sales.excl_vat') }}</div>
        @else
          <div class="sprice onreq">{{ __('site.sales.on_request') }}</div>
        @endif
        <div class="avail {{ $i->stock_qty > 0 ? 'ok' : '' }} num">{{ $i->stock_qty > 0 ? __('site.sales.in_stock', ['n' => $i->stock_qty]) : __('site.sales.lead') }}</div>
      </div>
      <button class="sadd" type="button" data-add="{{ $i->id }}" data-name="{{ $i->name() }}" data-price="{{ $i->list_price_cents > 0 ? $i->price_cents : 0 }}" data-code="{{ $i->number }}" aria-label="{{ __('site.sales.add') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
    </div>
  </div>
</article>
