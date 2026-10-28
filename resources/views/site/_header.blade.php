@php($app = rtrim(config('app.url'), '/'))
@php($cust = \App\Services\Web\CustomerAuth::current())
<header class="site-header"><div class="gut nav" id="nav">
  <a class="logo" href="{{ route('site.home') }}" aria-label="{{ __('site.nav.home') }}"><img src="{{ asset('images/cluk-logo.png') }}" alt="cluk" width="78" height="34"></a>
  <ul id="menu">
    <li><a href="{{ route('site.dryhire') }}" @if ($active === 'dryhire') aria-current="page" @endif>{{ __('site.nav.dryhire') }}</a></li>
    <li><a href="{{ Route::has('site.sales') ? route('site.sales') : route('site.home').'#sales' }}" @if ($active === 'sales') aria-current="page" @endif>{{ __('site.nav.sales') }}</a></li>
    <li><a href="{{ $app }}/book?lang={{ app()->getLocale() }}">{{ __('site.nav.factory') }}</a></li>
    <li><a href="{{ $app }}/afrit6">{{ __('site.nav.afrit6') }}</a></li>
  </ul>
  <div class="right">
    <div class="langw"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/></svg>
      <label for="lang" class="sr">{{ __('site.nav.language') }}</label>
      <select class="lang" id="lang">
        @foreach (config('cluk.locales') as $code => $name)
          <option value="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" @selected(app()->getLocale() === $code) lang="{{ $code }}" title="{{ $name }}">{{ strtoupper($code) }}</option>
        @endforeach
      </select></div>
    <button class="menu-btn" id="menuBtn" aria-expanded="false" aria-controls="menu">{{ __('site.nav.menu') }}</button>
    <details class="login" id="login"><summary>{{ $cust ? __('site.account.my') : __('site.nav.login') }}<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 4.5 6 7.5 9 4.5"/></svg></summary>
      <div class="login-menu">
        <a href="{{ $app }}/afrit6"><b>{{ __('site.nav.afrit6') }}</b><span>{{ __('site.nav.afrit6_sub') }}</span></a>
        @if ($cust)
          <a href="{{ route('site.account') }}"><b>{{ __('site.account.my') }}</b><span>{{ __('site.account.hello', ['name' => $cust->first_name]) }}</span></a>
        @else
          <a href="{{ route('site.account.login') }}"><b>{{ __('site.account.menu') }}</b><span>{{ __('site.account.menu_sub') }}</span></a>
        @endif
        <a href="{{ $app }}/login"><b>{{ __('site.nav.portal') }}</b><span>{{ __('site.nav.portal_sub') }}</span></a>
        @if ($cust)<form method="post" action="{{ route('site.account.logout') }}">@csrf<button type="submit" class="login-out">{{ __('site.account.logout') }}</button></form>@endif
      </div></details>
  </div>
</div></header>
