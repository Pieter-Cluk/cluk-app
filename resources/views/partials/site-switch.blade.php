{{-- Switch between the CLUK sites (website, Cluk Factory, Afrit 6). Used in dark page headers; works without JavaScript. --}}
@php($lang = app()->getLocale())
@php($home = Route::has('site.home') ? route('site.home') : url('/site'))
@php($sites = [
    'home' => [$home.'?lang='.$lang, 'cluk.live'],
    'dryhire' => [Route::has('site.dryhire') ? route('site.dryhire', ['lang' => $lang]) : $home, null],
    'sales' => [Route::has('site.sales') ? route('site.sales', ['lang' => $lang]) : $home.'?lang='.$lang.'#sales', null],
    'factory' => [route('factory.book', ['lang' => $lang]), null],
    'afrit6' => [route('afrit6.login'), null],
])
<details class="sw">
    <summary aria-label="{{ __('switch.label') }}"><span>{{ __('switch.label') }}</span><svg width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg></summary>
    <div class="sw-pop" role="menu">
        @foreach ($sites as $key => [$url])
            <a role="menuitem" href="{{ $url }}" @if (($current ?? '') === $key) aria-current="page" @endif><b>{{ __('switch.sites.'.$key) }}</b><small>{{ __('switch.subs.'.$key) }}</small></a>
        @endforeach
    </div>
</details>
@once
<style>
.sw{position:relative;font-family:inherit}
.sw summary{list-style:none;display:flex;align-items:center;gap:8px;cursor:pointer;min-height:36px;padding:0 12px;border:1px solid rgba(255,255,255,.3);border-radius:999px;color:#fff;font-size:12px;font-weight:700;letter-spacing:.5px;white-space:nowrap}
.sw summary::-webkit-details-marker{display:none}
.sw summary:hover,.sw[open] summary{border-color:#fff}
.sw summary:focus-visible{outline:2px solid #fff;outline-offset:2px}
.sw-pop{position:absolute;right:0;top:calc(100% + 8px);width:250px;max-width:calc(100vw - 32px);background:#fff;color:#111;border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.3);padding:6px;z-index:50;text-align:left}
.sw-pop a{display:flex;flex-direction:column;gap:1px;padding:9px 12px;border-radius:7px;color:#111;text-decoration:none;font-size:14px;letter-spacing:0}
.sw-pop a small{color:#666;font-size:12px;font-weight:400}
.sw-pop a:hover,.sw-pop a:focus-visible{background:#f1f3f1;outline:none}
.sw-pop a[aria-current="page"]{background:#eef6ee}
.sw-pop a[aria-current="page"] b::after{content:" ✓";color:#2e8b3a}
</style>
@endonce
