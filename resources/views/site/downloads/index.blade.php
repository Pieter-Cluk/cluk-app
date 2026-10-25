@extends('site.layout', ['active' => 'downloads'])
@section('title', __('site.downloads.title'))
@section('description', __('site.downloads.intro'))
@section('body', 'sl')
@section('content')
<main class="dl gut">
  <header class="dl-head">
    <h1>{{ __('site.downloads.title') }}</h1>
    <p class="muted">{{ __('site.downloads.intro') }}</p>
  </header>
  @if ($docs->isEmpty())
    <p class="muted">{{ __('site.downloads.empty') }}</p>
  @else
    <ul class="dl-list">
      @foreach ($docs as $d)
        <li class="dl-item">
          <span class="dl-ext" aria-hidden="true">{{ $d->extension() }}</span>
          <div class="dl-txt">
            <h2>{{ $d->text('title') }}</h2>
            @if ($t = $d->text('description'))<p>{{ $t }}</p>@endif
            <small class="muted">{{ $d->extension() }} · {{ $d->sizeText() }}@if ($d->access === 'login') · {{ __('site.downloads.for_customers') }}@endif</small>
          </div>
          <a class="btn" href="{{ route('site.downloads.get', $d->slug()) }}" @if ($d->access === 'open' || ($d->access === 'email' && $who)) download @endif>{{ __('site.downloads.get') }}</a>
        </li>
      @endforeach
    </ul>
  @endif
</main>
@endsection
