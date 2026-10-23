@extends('site.layout', ['active' => 'account'])
@section('title', __('site.account.reset_title'))
@section('description', __('site.account.reset_title'))
@section('body', 'sl')
@section('content')
<main class="acc gut">
  <section class="acc-card">
    <h1>{{ __('site.account.reset_title') }}</h1>
    @include('site.account._form')
    <form method="post" action="{{ route('site.account.reset.post') }}" class="acc-form">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <div class="field"><label for="email">{{ __('site.account.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required></div>
      <div class="field"><label for="password">{{ __('site.account.password_new') }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required><span class="note">{{ __('site.account.password_hint') }}</span></div>
      <div class="field"><label for="password_confirmation">{{ __('site.account.password_again') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></div>
      <button class="btn" type="submit">{{ __('site.account.reset_btn') }}</button>
    </form>
  </section>
</main>
@endsection
