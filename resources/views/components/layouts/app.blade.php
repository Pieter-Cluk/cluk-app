@props(['title' => null])
@php($nav = \App\Support\Navigation::for(auth()->user()))
@php($user = auth()->user())
<!doctype html>
<html lang="{{ app()->getLocale() }}" data-pick-hint="{{ __('cluk.pick_hint') }}" @if (in_array($user?->theme, ['light', 'dark'], true)) data-theme="{{ $user->theme }}" @endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'CLUK' }} · CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}?v={{ @filemtime(public_path('css/cluk.css')) }}">
<script>
    // Day / night mode: the choice in My preferences, else the device setting.
    try { localStorage.removeItem('cluk-theme'); } catch (e) {}
</script>
@include('partials.favicon')
<script src="{{ asset('js/pick.js') }}?v={{ @filemtime(public_path('js/pick.js')) }}" defer></script>
</head>
<body @class(['density-compact' => $user?->density === 'compact'])>
<div class="app">
    <input type="checkbox" id="nav-open" class="nav-toggle" aria-hidden="true">
    <aside class="side">
        <a class="logo" href="{{ route('dashboard') }}" aria-label="{{ __('nav.logo_home') }}">
            <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="36">
            <span>CONNECTING THE DOTS</span>
        </a>
        <nav aria-label="{{ __('nav.menu') }}">
            @foreach ($nav['top'] as $item)
                @include('layouts.nav-item', ['item' => $item])
            @endforeach
            @foreach ($nav['groups'] as $group => $items)
                <div class="group">{{ __('nav.groups.'.$group) }}</div>
                @foreach ($items as $item)
                    @include('layouts.nav-item', ['item' => $item])
                @endforeach
            @endforeach
        </nav>
        <div class="side-foot">{{ __('nav.footer') }}<br><b>{{ __('nav.slogan') }}</b></div>
    </aside>
    <label for="nav-open" class="nav-scrim" aria-hidden="true"></label>

    <main class="main">
        <header class="top">
            <label for="nav-open" class="iconbtn menu-btn" aria-label="{{ __('nav.menu') }}"><x-icon name="menu"/></label>
            <label class="search">
                <x-icon name="search"/>
                <input type="search" placeholder="{{ __('nav.search') }}" aria-label="{{ __('nav.search') }}">
            </label>
            <div class="top-right">
                <button type="button" class="iconbtn" id="theme-btn" data-day="{{ __('nav.day_mode') }}" data-night="{{ __('nav.night_mode') }}" aria-label="{{ __('nav.night_mode') }}" title="{{ __('nav.night_mode') }}">
                    <x-icon name="moon" class="i-moon"/><x-icon name="sun" class="i-sun"/>
                </button>
                <details class="um langs">
                    <summary class="iconbtn" aria-label="{{ __('nav.language') }}">{{ strtoupper(app()->getLocale()) }}</summary>
                    <div class="um-pop um-small">
                        @foreach (config('cluk.locales') as $code => $name)
                            <form method="post" action="{{ route('locale.switch', $code) }}">@csrf<button type="submit" class="um-item">{{ $name }}</button></form>
                        @endforeach
                    </div>
                </details>
                <details class="um">
                    <summary aria-label="{{ __('nav.account_menu') }}"><span class="um-name">{{ $user->name }}</span><x-avatar :who="$user"/></summary>
                    <div class="um-pop">
                        <div class="um-head"><x-avatar :who="$user" size="lg"/><div><b>{{ $user->name }}</b><small>{{ $user->email }}</small><small>{{ __('nav.role') }}: {{ $user->role?->name ?? '—' }}</small>@if ($user->awayNow())<small class="um-away">{{ __('me.away.badge', ['date' => $user->away_until->format('d/m')]) }}</small>@endif</div></div>
                        <div class="um-sep"></div>
                        @foreach ($nav['account'] as $item)
                            <a class="um-item" href="{{ url($item['path']) }}"><x-icon :name="$item['icon']"/>{{ __('nav.items.'.$item['key']) }}</a>
                        @endforeach
                        <div class="um-sep"></div>
                        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="um-item"><x-icon name="logout"/>{{ __('nav.logout') }}</button></form>
                    </div>
                </details>
            </div>
        </header>
        <div class="content">
            {{ $slot }}
        </div>
    </main>
</div>
<script>
    (function () {
        const btn = document.getElementById('theme-btn');
        const root = document.documentElement;
        const isDark = () => root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        const label = () => { const t = isDark() ? btn.dataset.day : btn.dataset.night; btn.setAttribute('aria-label', t); btn.title = t; btn.classList.toggle('is-dark', isDark()); };
        btn.addEventListener('click', () => {
            root.dataset.theme = isDark() ? 'light' : 'dark';
            label();
            fetch(@js(route('me.theme')), {method: 'POST', headers: {'X-CSRF-TOKEN': @js(csrf_token()), 'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify({theme: root.dataset.theme})}).catch(() => {});
        });
        label();
    })();
</script>
@if ($user)
<script>
    // Keyboard shortcuts (listed in Help): "?" opens Help, "g" then a letter jumps to a module.
    (function () {
        const map = @js(collect(\App\Http\Controllers\Help\HelpController::shortcuts($user))->mapWithKeys(fn ($k) => [$k['keys'] => $k['url']]));
        let g = 0;
        document.addEventListener('keydown', function (e) {
            const t = e.target;
            if (e.ctrlKey || e.metaKey || e.altKey || (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)))) return;
            if (e.key === '?' && map['?']) { location.href = map['?']; return; }
            if (e.key === 'g') { g = Date.now(); return; }
            if (g && Date.now() - g < 1200 && map['g ' + e.key]) { location.href = map['g ' + e.key]; }
            g = 0;
        });
    })();
</script>
@endif
</body>
</html>
