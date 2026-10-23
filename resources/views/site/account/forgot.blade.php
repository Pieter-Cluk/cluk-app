@extends('site.layout', ['active' => 'account'])
@section('title', __('site.account.forgot_title'))
@section('description', __('site.account.forgot_intro'))
@section('body', 'sl')
@section('content')
<main class="acc gut">
  <section class="acc-card">
    <h1>{{ __('site.account.forgot_title') }}</h1>
    <p class="muted">{{ __('site.account.forgot_intro') }}</p>
    @include('site.account._form')
    <form method="post" action="{{ route('site.account.forgot.post') }}" class="acc-form">
      @csrf
      <div class="field"><label for="email">{{ __('site.account.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
      <button class="btn" type="submit">{{ __('site.account.forgot_btn') }}</button>
    </form>
    <p class="acc-links"><a href="{{ route('site.account.login') }}">← {{ __('site.account.login_title') }}</a></p>
  </section>
</main>
@endsection
