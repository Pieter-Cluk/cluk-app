@extends('site.layout', ['active' => 'downloads'])
@section('title', $d->label(null, 'title'))
@section('body', 'sl')
@section('content')
<main class="acc gut">
  <section class="acc-card">
    <h1 class="dl-ask-h">{{ $d->label(null, 'title') }}</h1>
    <p class="muted">{{ __('site.downloads.ask_intro') }}</p>
    @include('site.account._form')
    <form method="post" action="{{ route('site.downloads.ask', $d->id) }}" class="acc-form">
      @csrf
      <div class="field"><label for="name">{{ __('site.downloads.name') }}</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120" autofocus></div>
      <div class="field"><label for="email">{{ __('site.downloads.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="190"></div>
      <div class="field"><label for="company">{{ __('site.downloads.company') }}</label><input id="company" name="company" value="{{ old('company') }}" autocomplete="organization" maxlength="190"></div>
      <div class="hp" aria-hidden="true"><label>Website <input name="website_url" tabindex="-1" autocomplete="off"></label></div>
      <p class="muted small">{{ __('site.downloads.privacy') }}</p>
      <button class="btn" type="submit">{{ __('site.downloads.download') }} · {{ $d->type() }} {{ $d->sizeLabel() }}</button>
      <p class="hint">{!! __('privacy.agree', ['link' => '<a href="'.e(route('site.privacy')).'" target="_blank" rel="noopener">'.e(__('privacy.link')).'</a>']) !!}</p>
    </form>
  </section>
  <section class="acc-side">
    <h2>{{ __('site.downloads.have_login') }}</h2>
    <p>{{ __('site.downloads.have_login_text') }}</p>
    <a class="btn ghost" href="{{ route('site.account.login') }}">{{ __('site.account.login_btn') }}</a>
  </section>
</main>
@endsection
