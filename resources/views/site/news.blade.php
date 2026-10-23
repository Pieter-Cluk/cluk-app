@extends('site.layout', ['active' => 'news'])
@section('title', __('site.news.title'))
@section('description', __('site.news.meta'))
@section('content')
@php
    $labels = ['dryhire' => __('site.nav.dryhire'), 'sales' => __('site.nav.sales'), 'factory' => __('site.nav.factory'), 'afrit6' => __('site.nav.afrit6')];
@endphp
<main class="newsp">
  <header class="newsp-head gut"><h1>{{ __('site.news.title') }}</h1><p class="lab">{{ __('site.news.intro') }}</p></header>
  <nav class="nfilter gut" aria-label="{{ __('site.news.filter') }}">
    <a href="{{ route('site.news') }}" @if (! $part) aria-current="page" @endif>{{ __('site.news.all') }} <span>{{ $counts['all'] }}</span></a>
    @foreach ($labels as $k => $l)<a href="{{ route('site.news', ['part' => $k]) }}" @if ($part === $k) aria-current="page" @endif>{{ $l }} <span>{{ $counts[$k] }}</span></a>@endforeach
  </nav>
  <section class="ngrid gut">
    @forelse ($posts as $p)@include('site._post', ['p' => $p])@empty<p class="nempty">{{ __('site.news.empty') }}</p>@endforelse
  </section>
  @if ($posts->lastPage() > 1)
    <nav class="npager gut">@for ($i = 1; $i <= $posts->lastPage(); $i++)<a href="{{ request()->fullUrlWithQuery(['page' => $i]) }}" @if ($i === $posts->currentPage()) aria-current="page" @endif>{{ $i }}</a>@endfor</nav>
  @endif
</main>
@endsection
