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
<link rel="icon" href="{{ asset('images/site/favicon.ico') }}?v=2" sizes="48x48">
<link rel="icon" href="{{ asset('images/site/icon-32.png') }}?v=2" type="image/png" sizes="32x32">
<link rel="icon" href="{{ asset('images/site/icon-192.png') }}?v=2" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="{{ asset('images/site/apple-touch-icon.png') }}?v=2">
@stack('head')
</head>
<body class="@yield('body')">
@include('site._header', ['active' => $active ?? null])
@yield('content')
@include('site._footer')
<div class="consent" id="consent" role="dialog" aria-labelledby="consentTitle" hidden>
  <div><b id="consentTitle">{{ __('site.consent.title') }}</b><p>{{ __('site.consent.text') }}</p></div>
  <div class="consent-btns"><button type="button" class="c-no" data-consent="necessary">{{ __('site.consent.necessary') }}</button><button type="button" class="c-yes" data-consent="all">{{ __('site.consent.all') }}</button></div>
</div>
<script type="application/json" id="site-cfg">{"track":"{{ route('site.track') }}","section":"@yield('section', $active ?? '')"}</script>
<script src="{{ asset('js/site.js') }}?v={{ @filemtime(public_path('js/site.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
