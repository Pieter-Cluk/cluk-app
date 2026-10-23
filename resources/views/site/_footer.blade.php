@php($co = \App\Models\CompanyDetail::query()->first())
@php($app = rtrim(config('app.url'), '/'))
<footer class="site-footer"><div class="gut">
  <div class="foot">
    <a class="logo" href="{{ route('site.home') }}" aria-label="{{ __('site.nav.home') }}"><img src="{{ asset('images/cluk-logo.png') }}" alt="cluk" width="134" height="58"></a>
    <div class="slogan">{{ __('site.footer.slogan') }}</div>
    <div class="fr">
      <nav class="links" aria-label="{{ __('site.footer.footer') }}">
        <a href="{{ route('site.dryhire') }}">{{ __('site.nav.dryhire') }}</a>
        <a href="{{ Route::has('site.sales') ? route('site.sales') : route('site.home').'#sales' }}">{{ __('site.nav.sales') }}</a>
        <a href="{{ $app }}/book">{{ __('site.nav.factory') }}</a>
        <a href="{{ $app }}/afrit6">{{ __('site.nav.afrit6') }}</a>
        <a href="mailto:{{ $co?->email ?: 'info@cluk.live' }}">{{ __('site.footer.contact') }}</a>
        <a href="#cookies" data-cookie-settings>{{ __('site.consent.settings') }}</a>
      </nav>
      <div class="social">
        <a href="{{ config('website.social.instagram') }}" aria-label="Instagram" rel="noopener" target="_blank">@include('site._icon', ['i' => 'ig'])</a>
        <a href="{{ config('website.social.facebook') }}" aria-label="Facebook" rel="noopener" target="_blank">@include('site._icon', ['i' => 'fb'])</a>
        <a href="{{ config('website.social.linkedin') }}" aria-label="LinkedIn" rel="noopener" target="_blank">@include('site._icon', ['i' => 'li'])</a>
      </div>
    </div>
  </div>
  <div class="legal"><span>© {{ now()->year }} {{ trim(($co?->legal_name ?: 'CLUK').' '.($co?->legal_form ?? 'BV')) }} · {{ $co?->email ?: 'info@cluk.live' }}@if ($co?->phone) · {{ $co->phone }}@endif @if ($co?->vat_number) · {{ $co->vat_number }}@endif</span><span>{{ $co ? trim($co->street.', '.$co->postcode.' '.$co->city, ' ,') : 'Zulte' }} · {{ __('site.footer.country') }}</span></div>
</div></footer>
