@extends('site.layout', ['active' => 'account'])
@section('title', __('site.account.account_title'))
@section('description', __('site.account.account_title'))
@section('body', 'sl')
@section('content')
@php
    $eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c);
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale(app()->getLocale())->translatedFormat('j M Y') : '–';
@endphp
<main class="acc-page gut">
  <header class="lhead">
    <h1>{{ __('site.account.hello', ['name' => $a->first_name]) }}</h1>
    <p class="muted">{{ $a->email }}@if ($a->company) · {{ __('site.account.company_linked', ['company' => $a->company->name]) }}@endif</p>
    <form method="post" action="{{ route('site.account.logout') }}">@csrf<button class="btn ghost" type="submit">{{ __('site.account.logout') }}</button></form>
  </header>
  @include('site.account._form')

  <section class="acc-block">
    <h2>{{ __('site.account.baskets') }}</h2>
    <div class="acc-baskets">
      <a class="acc-basket" href="{{ route('site.dryhire') }}?basket=1"><b>{{ __('site.account.dryhire_basket') }}</b><span class="num">{{ __('site.account.lines_n', ['n' => count($dry['lines'])]) }}@if ($dry['first_day']) · {{ $date($dry['first_day']) }} – {{ $date($dry['last_day']) }}@endif</span><span class="go">{{ __('site.account.open') }} →</span></a>
      <a class="acc-basket" href="{{ route('site.sales') }}?basket=1"><b>{{ __('site.account.sales_basket') }}</b><span class="num">{{ __('site.account.lines_n', ['n' => count($sales['lines'])]) }}@if ($sales['net_cents']) · {{ $eur($sales['net_cents']) }}@endif</span><span class="go">{{ __('site.account.open') }} →</span></a>
    </div>
  </section>

  <section class="acc-block">
    <h2>{{ __('site.account.orders') }}</h2>
    @if ($history['orders'])
      <div class="tbl"><table><thead><tr><th>{{ __('site.account.col_ref') }}</th><th>{{ __('site.account.col_date') }}</th><th>{{ __('site.account.col_po') }}</th><th class="r">{{ __('site.account.col_amount') }}</th><th>{{ __('site.account.col_status') }}</th></tr></thead><tbody>
        @foreach ($history['orders'] as $o)<tr><td>{{ $o['number'] }}</td><td>{{ $date($o['date']) }}</td><td>{{ $o['po_number'] ?: '–' }}</td><td class="r num">{{ $eur($o['net_cents']) }}</td><td>{{ __('site.account.status.'.$o['status']) }}</td></tr>@endforeach
      </tbody></table></div>
    @else<p class="muted">{{ __('site.account.none') }}</p>@endif
  </section>

  <section class="acc-block">
    <h2>{{ __('site.account.requests') }}</h2>
    @if ($history['requests'])
      <div class="tbl"><table><thead><tr><th>{{ __('site.account.col_ref') }}</th><th>{{ __('site.account.col_date') }}</th><th>{{ __('site.account.col_use') }}</th><th class="r">{{ __('site.account.col_lines') }}</th><th class="r">{{ __('site.account.col_amount') }}</th><th>{{ __('site.account.col_status') }}</th></tr></thead><tbody>
        @foreach ($history['requests'] as $r)<tr><td>{{ $r['ref'] }}</td><td>{{ $date($r['date']) }}</td><td>{{ $date($r['first_day']) }} – {{ $date($r['last_day']) }}</td><td class="r num">{{ $r['lines'] }}</td><td class="r num">{{ $r['estimate_cents'] ? $eur($r['estimate_cents']) : '–' }}</td><td>{{ __('site.account.status.'.$r['status']) }}</td></tr>@endforeach
      </tbody></table></div>
    @else<p class="muted">{{ __('site.account.none') }}</p>@endif
  </section>

  <div class="acc-two">
    <section class="acc-block">
      <h2>{{ __('site.account.profile') }}</h2>
      <form method="post" action="{{ route('site.account.profile') }}" class="acc-form">
        @csrf
        <div class="two"><div class="field"><label for="first_name">{{ __('site.account.first_name') }}</label><input id="first_name" name="first_name" value="{{ old('first_name', $a->first_name) }}" required></div>
          <div class="field"><label for="last_name">{{ __('site.account.last_name') }}</label><input id="last_name" name="last_name" value="{{ old('last_name', $a->last_name) }}" required></div></div>
        <div class="two"><div class="field"><label for="company_name">{{ __('site.account.company') }}</label><input id="company_name" name="company_name" value="{{ old('company_name', $a->company_name) }}"></div>
          <div class="field"><label for="vat_number">{{ __('site.account.vat') }}</label><input id="vat_number" name="vat_number" value="{{ old('vat_number', $a->vat_number) }}"></div></div>
        <div class="two"><div class="field"><label for="phone">{{ __('site.account.phone') }}</label><input id="phone" name="phone" type="tel" value="{{ old('phone', $a->phone) }}"></div>
          <div class="field"><label for="locale">{{ __('site.account.language') }}</label><select id="locale" name="locale">@foreach (config('cluk.locales') as $code => $name)<option value="{{ $code }}" @selected($a->locale === $code)>{{ $name }}</option>@endforeach</select></div></div>
        <button class="btn" type="submit">{{ __('site.account.save') }}</button>
      </form>
    </section>
    <section class="acc-block">
      <h2>{{ __('site.account.change_password') }}</h2>
      <form method="post" action="{{ route('site.account.password') }}" class="acc-form">
        @csrf
        <div class="field"><label for="current">{{ __('site.account.password_current') }}</label><input id="current" name="current" type="password" autocomplete="current-password" required></div>
        <div class="field"><label for="password">{{ __('site.account.password_new') }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required></div>
        <div class="field"><label for="password_confirmation">{{ __('site.account.password_again') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></div>
        <button class="btn ghost" type="submit">{{ __('site.account.change_password') }}</button>
      </form>
    </section>
  </div>
</main>
@endsection
