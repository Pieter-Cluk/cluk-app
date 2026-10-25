<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="30">
<meta name="robots" content="noindex">
<title>CLUK · @yield('title')</title>
<link rel="stylesheet" href="{{ asset('css/wall.css') }}?v={{ @filemtime(public_path('css/wall.css')) }}">
@include('partials.favicon')
</head>
<body class="wall">
<div class="top">
    <div class="top-l"><img src="{{ asset('images/cluk-logo.png') }}" alt="cluk" height="40"><h1>@yield('title')</h1>@yield('nav')</div>
    <div class="clock"><b id="clock">{{ now()->format('H:i') }}</b><span>{{ now()->locale(app()->getLocale())->translatedFormat('D j M') }} · {{ __('wall.refresh') }}</span></div>
</div>
@yield('body')
<script>setInterval(() => { const d = new Date(); document.getElementById('clock').textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }, 5000);</script>
</body>
</html>
