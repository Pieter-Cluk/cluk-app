{{-- Sales bar under the site header: all categories (mega menu), search with suggestions, new, brands, basket. --}}
<div class="sbar" id="sbar"><div class="sbar-in gut">
  <button class="mega-btn" id="megaBtn" type="button" aria-expanded="false" aria-controls="mega"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>{{ __('site.sales.all_categories') }}</button>
  <form class="ssearch" id="ssearch" action="{{ route('site.sales.search') }}" method="get" role="search" autocomplete="off">
    <label for="sq" class="sr">{{ __('site.sales.search_label') }}</label>
    <input id="sq" name="q" type="search" value="{{ $q ?? '' }}" placeholder="{{ __('site.sales.search_placeholder') }}" enterkeyhint="search" aria-autocomplete="list" aria-controls="suggest">
    <button type="submit" aria-label="{{ __('site.sales.search_btn') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
    <div class="suggest-box" id="suggest" role="listbox" hidden></div>
  </form>
  <nav class="slinks" aria-label="{{ __('site.sales.title') }}">
    <a href="{{ route('site.sales.new') }}" @if (($mode ?? '') === 'new') aria-current="page" @endif>{{ __('site.sales.new_items') }}</a>
    <a href="{{ route('site.sales.brands') }}" @if (in_array($mode ?? '', ['brands', 'brand'], true)) aria-current="page" @endif>{{ __('site.sales.brands') }}</a>
  </nav>
  <button class="basket-btn" id="basketBtn" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.3a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/></svg><span class="bl">{{ __('site.sjs.basket_btn') }}</span><span class="n" id="basketN">0</span></button>
</div>
<div class="mega" id="mega" hidden><div class="mega-in gut">
  @forelse ($tree as $c)
    <div class="mega-col">
      <a class="mega-cat" href="{{ route('site.sales.category', $c->slug) }}">{{ $c->label($locale) }} <span>{{ $c->n }}</span></a>
      @if ($c->subcategories->count() > 1 || ($c->subcategories->first()?->slug ?? 'general') !== 'general')
        @foreach ($c->subcategories as $s)<a href="{{ route('site.sales.category', [$c->slug, $s->slug]) }}">{{ $s->label($locale) }}</a>@endforeach
      @endif
    </div>
  @empty
    <p class="muted">{{ __('site.sales.empty') }}</p>
  @endforelse
  <div class="mega-col"><a class="mega-cat" href="{{ route('site.sales.brands') }}">{{ __('site.sales.brands') }}</a><a href="{{ route('site.sales.new') }}">{{ __('site.sales.new_items') }}</a></div>
</div></div></div>
