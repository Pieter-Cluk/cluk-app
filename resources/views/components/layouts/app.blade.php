@props(['title' => null])
@php($nav = \App\Support\Navigation::for(auth()->user()))
@php($user = auth()->user())
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'CLUK' }} · CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
<script>
    // Day / night mode: the person's choice, else the device setting.
    try { const t = localStorage.getItem('cluk-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
</script>
</head>
<body>
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
                    <summary aria-label="{{ __('nav.account_menu') }}"><span class="um-name">{{ $user->name }}</span><span class="av">{{ $user->initials }}</span></summary>
                    <div class="um-pop">
                        <div class="um-head"><span class="av av-lg">{{ $user->initials }}</span><div><b>{{ $user->name }}</b><small>{{ $user->email }}</small><small>{{ __('nav.role') }}: {{ $user->role?->name ?? '—' }}</small></div></div>
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
        btn.addEventListener('click', () => { root.dataset.theme = isDark() ? 'light' : 'dark'; try { localStorage.setItem('cluk-theme', root.dataset.theme); } catch (e) {} label(); });
        label();
    })();
</script>
</body>
</html>
