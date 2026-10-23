<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title') · CLUK</title>
<meta name="description" content="@yield('description')">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0a0a0a">
@foreach (array_keys(config('cluk.locales')) as $code)
<link rel="alternate" hreflang="{{ $code }}" href="{{ request()->url() }}?lang={{ $code }}">
@endforeach
@if (file_exists(public_path('fonts/site-fonts.css')))<link rel="stylesheet" href="{{ asset('fonts/site-fonts.css') }}">@endif
<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
<link rel="icon" href="{{ asset('favicon.ico') }}">
@stack('head')
</head>
<body class="@yield('body')">
@include('site._header', ['active' => $active ?? null])
@yield('content')
@include('site._footer')
<script src="{{ asset('js/site.js') }}?v={{ @filemtime(public_path('js/site.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
