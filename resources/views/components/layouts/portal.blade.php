@props(['title' => null, 'tab' => 'overview'])
@php($member = auth('member')->user())
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ? $title.' · ' : '' }}Afrit 6</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}?v={{ @filemtime(public_path('css/cluk.css')) }}">
@include('partials.favicon')
</head>
<body class="portal-body">
<div class="portal">
    <header class="p-top">
        <a class="a6-brand" href="{{ route('afrit6.portal') }}" aria-label="Afrit 6">
            <span class="a6-sign" aria-hidden="true"><b>6</b><i></i><svg width="22" height="18" viewBox="0 0 28 24" fill="none" stroke="currentColor" stroke-width="4.5"><path d="M1 12h22M13 3l9 9-9 9"/></svg></span>
            <span class="a6-word">AFRIT 6</span>
        </a>
        <div class="p-right">
            @include('partials.site-switch', ['current' => 'afrit6'])
            <details class="um langs">
                <summary class="iconbtn" aria-label="{{ __('login.language') }}">{{ strtoupper(app()->getLocale()) }}</summary>
                <div class="um-pop um-small">
                    @foreach (config('cluk.locales') as $code => $name)
                        <form method="post" action="{{ route('locale.switch', $code) }}">@csrf<button type="submit" class="um-item">{{ $name }}</button></form>
                    @endforeach
                </div>
            </details>
            <span class="p-who"><span class="av">{{ $member->initials }}</span><span><b>{{ $member->name }}</b><small>{{ $member->companyName() }}</small></span></span>
            <form method="post" action="{{ route('afrit6.logout') }}">@csrf<button class="btn" type="submit">{{ __('afrit6.logout') }}</button></form>
        </div>
    </header>
    @if ($member->isActive())
        <nav class="p-tabs" aria-label="Afrit 6">
            @foreach (['overview' => 'afrit6.portal', 'desks' => 'afrit6.portal.desks', 'room' => 'afrit6.portal.room', 'services' => 'afrit6.portal.services', 'bill' => 'afrit6.portal.bill'] as $key => $route)
                <a href="{{ route($route) }}" @class(['tab', 'tab-on' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>{{ __('workspace.nav.'.$key) }}</a>
            @endforeach
        </nav>
    @endif
    <main class="p-main">
        @include('partials.flash')
        {{ $slot }}
    </main>
    <footer class="p-foot"><img src="{{ asset('images/cluk-logo.png') }}" alt="cluk" height="20"><span>CONNECTING THE DOTS</span></footer>
</div>
</body>
</html>
