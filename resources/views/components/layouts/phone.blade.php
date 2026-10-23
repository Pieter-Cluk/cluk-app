@props(['title' => null, 'back' => null])
@php($user = auth()->user())
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#52ae32">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<link rel="manifest" href="{{ asset('warehouse.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('images/app-icon-192.png') }}">
<title>{{ $title ?? __('warehouse.app') }} · CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
<link rel="stylesheet" href="{{ asset('css/phone.css') }}">
<script>
    try { const t = localStorage.getItem('cluk-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
</script>
@include('partials.favicon')
</head>
<body class="ph-body">
<header class="ph-top">
    @if ($back)
        <a class="ph-back" href="{{ $back }}" aria-label="{{ __('warehouse.back') }}">‹</a>
    @else
        <a class="ph-logo" href="{{ route('w.home') }}"><img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="24"></a>
    @endif
    <b class="ph-title">{{ $title ?? __('warehouse.app') }}</b>
    <a class="ph-me" href="{{ route('dashboard') }}" title="{{ __('warehouse.to_office') }}"><x-avatar :who="$user" size="sm" /></a>
</header>
<main class="ph-main">
    @include('partials.flash')
    {{ $slot }}
</main>
<nav class="ph-tabs" aria-label="{{ __('warehouse.app') }}">
    <a href="{{ route('w.home') }}" @class(['on' => request()->routeIs('w.home')])>{{ __('warehouse.tabs.tasks') }}</a>
    <a href="{{ route('w.scan') }}" @class(['on' => request()->routeIs('w.scan')])>▣ {{ __('warehouse.tabs.scan') }}</a>
    <a href="{{ route('warehouse.index') }}">{{ __('warehouse.tabs.office') }}</a>
</nav>
<script src="{{ asset('js/scan.js') }}?v=1" defer></script>
</body>
</html>
