@extends('site.layout', ['active' => 'account'])
@section('title', __('site.account.login_title'))
@section('description', __('site.account.login_intro'))
@section('body', 'sl')
@section('content')
<main class="acc gut">
  <section class="acc-card">
    <h1>{{ __('site.account.login_title') }}</h1>
    <p class="muted">{{ __('site.account.login_intro') }}</p>
    @include('site.account._form')
    <form method="post" action="{{ route('site.account.login.post') }}" class="acc-form">
      @csrf
      <div class="field"><label for="email">{{ __('site.account.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
      <div class="field"><label for="password">{{ __('site.account.password') }}</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
      <label class="opt"><input type="checkbox" name="remember" value="1">{{ __('site.account.remember') }}</label>
      <button class="btn" type="submit">{{ __('site.account.login_btn') }}</button>
    </form>
    <p class="acc-links"><a href="{{ route('site.account.forgot') }}">{{ __('site.account.forgot') }}</a></p>
  </section>
  <section class="acc-side">
    <h2>{{ __('site.account.no_account') }}</h2>
    <p>{{ __('site.account.register_intro') }}</p>
    <a class="btn ghost" href="{{ route('site.account.register') }}">{{ __('site.account.register_link') }}</a>
  </section>
</main>
@endsection
