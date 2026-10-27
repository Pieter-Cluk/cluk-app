@extends('site.layout', ['active' => 'account'])
@section('title', __('site.account.register_title'))
@section('description', __('site.account.register_intro'))
@section('body', 'sl')
@section('content')
<div class="acc-stage"><main class="acc gut">
  <section class="acc-card wide">
    <h1>{{ __('site.account.register_title') }}</h1>
    <p class="muted">{{ __('site.account.register_intro') }}</p>
    @include('site.account._form')
    <form method="post" action="{{ route('site.account.register.post') }}" class="acc-form">
      @csrf
      <div class="two"><div class="field"><label for="first_name">{{ __('site.account.first_name') }}</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required></div>
        <div class="field"><label for="last_name">{{ __('site.account.last_name') }}</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required></div></div>
      <div class="two"><div class="field"><label for="company_name">{{ __('site.account.company') }}</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" autocomplete="organization"></div>
        <div class="field"><label for="vat_number">{{ __('site.account.vat') }}</label><input id="vat_number" name="vat_number" value="{{ old('vat_number') }}" placeholder="BE0123.456.789"></div></div>
      <div class="two"><div class="field"><label for="email">{{ __('site.account.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', is_string(request('email')) ? request('email') : '') }}" autocomplete="email" required></div>
        <div class="field"><label for="phone">{{ __('site.account.phone') }}</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel"></div></div>
      <div class="two"><div class="field"><label for="password">{{ __('site.account.password_new') }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required><span class="note">{{ __('site.account.password_hint') }}</span></div>
        <div class="field"><label for="password_confirmation">{{ __('site.account.password_again') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></div></div>
      <input class="hp" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
      <label class="opt"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))>{{ __('site.account.terms') }}</label>
      <button class="btn" type="submit">{{ __('site.account.register_btn') }}</button>
    </form>
    <p class="acc-links">{{ __('site.account.have_account') }} <a href="{{ route('site.account.login') }}">{{ __('site.account.login_btn') }}</a></p>
  </section>
</main></div>
@endsection
