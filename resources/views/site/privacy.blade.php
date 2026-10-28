@extends('site.layout', ['active' => 'privacy'])
@section('title', __('privacy.title'))
@section('description', __('privacy.meta'))
@section('content')
<main class="legalp gut">
  <style>
    .legalp{max-width:780px;margin:0 auto;padding:48px 0 72px}
    .legalp h1{margin:0 0 6px}
    .legalp .upd{opacity:.7;font-size:14px;margin:0 0 24px}
    .legalp h2{font-size:20px;margin:32px 0 8px}
    .legalp p,.legalp li{line-height:1.6}
    .legalp ul{padding-left:20px;display:grid;gap:8px}
  </style>
  <h1>{{ __('privacy.title') }}</h1>
  <p class="upd">{{ __('privacy.updated', ['date' => $date->locale(app()->getLocale())->translatedFormat('j F Y')]) }}</p>
  <p>{{ __('privacy.intro', $vars) }}</p>
  @foreach (__('privacy.sections', $vars) as $s)
    <section>
      <h2>{{ $s['h'] }}</h2>
      @foreach ($s['p'] ?? [] as $p)<p>{{ $p }}</p>@endforeach
      @if (! empty($s['li']))<ul>@foreach ($s['li'] as $li)<li>{{ $li }}</li>@endforeach</ul>@endif
    </section>
  @endforeach
</main>
@endsection
