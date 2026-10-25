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

  @unless ($a->canOrder())<p class="acc-note">{{ __('site.account.look_only') }}</p>@endunless

  <section class="acc-block" id="quotes">
    <h2>{{ __('site.account.quotes') }}</h2>
    @if ($docs['quotes'])
      <div class="tbl"><table><thead><tr><th>{{ __('site.account.col_ref') }}</th><th>{{ __('site.account.col_what') }}</th><th>{{ __('site.account.col_date') }}</th><th class="r">{{ __('site.account.col_amount') }}</th><th>{{ __('site.account.col_status') }}</th><th></th></tr></thead><tbody>
        @foreach ($docs['quotes'] as $q)<tr><td class="nowrap">{{ $q['ref'] }}</td><td>{{ $q['title'] }}</td><td class="nowrap">{{ $date($q['date']) }}</td><td class="r num">{{ $eur($q['net_cents']) }}</td><td>{{ __('site.account.doc_status.'.$q['status']) }}</td>
          <td class="r"><a class="btn {{ $q['open'] && $a->canOrder() ? '' : 'ghost' }} sm" href="{{ $q['url'] }}" target="_blank" rel="noopener">{{ $q['open'] && $a->canOrder() ? __('site.account.open_accept') : __('site.account.open') }}</a></td></tr>@endforeach
      </tbody></table></div>
    @else<p class="muted">{{ __('site.account.none') }}</p>@endif
  </section>

  <section class="acc-block" id="orders">
    <h2>{{ __('site.account.orders') }}</h2>
    @if ($docs['orders'] || $history['orders'])
      <div class="tbl"><table><thead><tr><th>{{ __('site.account.col_ref') }}</th><th>{{ __('site.account.col_what') }}</th><th>{{ __('site.account.col_date') }}</th><th>{{ __('site.account.col_po') }}</th><th class="r">{{ __('site.account.col_amount') }}</th><th>{{ __('site.account.col_status') }}</th></tr></thead><tbody>
        @foreach ($docs['orders'] as $o)<tr><td class="nowrap">@if ($o['url'])<a href="{{ $o['url'] }}" target="_blank" rel="noopener">{{ $o['ref'] }}</a>@else{{ $o['ref'] }}@endif</td><td>{{ $o['title'] }}</td><td class="nowrap">{{ $date($o['from']) }} – {{ $date($o['until']) }}</td><td>{{ $o['po'] ?: '–' }}</td><td class="r num">{{ $eur($o['net_cents']) }}</td><td>{{ __('site.account.doc_status.'.$o['status']) }}</td></tr>@endforeach
        @foreach ($history['orders'] as $o)<tr><td class="nowrap">{{ $o['number'] }}</td><td>{{ __('site.account.product_order') }}</td><td class="nowrap">{{ $date($o['date']) }}</td><td>{{ $o['po_number'] ?: '–' }}</td><td class="r num">{{ $eur($o['net_cents']) }}</td><td>{{ __('site.account.status.'.$o['status']) }}</td></tr>@endforeach
      </tbody></table></div>
    @else<p class="muted">{{ __('site.account.none') }}</p>@endif
  </section>

  <section class="acc-block" id="invoices">
    <h2>{{ __('site.account.invoices') }}</h2>
    @if ($docs['invoices'])
      <div class="tbl"><table><thead><tr><th>{{ __('site.account.col_invoice') }}</th><th>{{ __('site.account.col_date') }}</th><th>{{ __('site.account.col_due') }}</th><th class="r">{{ __('site.account.col_amount_vat') }}</th><th>{{ __('site.account.col_status') }}</th><th></th></tr></thead><tbody>
        @foreach ($docs['invoices'] as $i)<tr><td class="nowrap">{{ $i['ref'] }}</td><td class="nowrap">{{ $date($i['date']) }}</td><td class="nowrap">{{ $date($i['due']) }}</td><td class="r num">{{ $i['gross'] }}</td>
          <td><span class="acc-pill acc-{{ $i['status'] }}">{{ __('site.account.inv_status.'.$i['status']) }}</span></td>
          <td class="r nowrap"><a class="btn ghost sm" href="{{ route('site.account.invoice', $i['id']) }}" target="_blank" rel="noopener">{{ __('site.account.open') }}</a>@if ($i['xml']) <a class="btn ghost sm" href="{{ route('site.account.invoice.xml', $i['id']) }}">XML</a>@endif</td></tr>@endforeach
      </tbody></table></div>
    @else<p class="muted">{{ $a->company_id ? __('site.account.none') : __('site.account.no_company') }}</p>@endif
  </section>

  <section class="acc-block">
    <h2>{{ __('site.account.requests') }}</h2>
    @if ($history['requests'])
      <div class="tbl"><table><thead><tr><th>{{ __('site.account.col_ref') }}</th><th>{{ __('site.account.col_date') }}</th><th>{{ __('site.account.col_use') }}</th><th class="r">{{ __('site.account.col_lines') }}</th><th class="r">{{ __('site.account.col_amount') }}</th><th>{{ __('site.account.col_status') }}</th></tr></thead><tbody>
        @foreach ($history['requests'] as $r)<tr><td>{{ $r['ref'] }}</td><td>{{ $date($r['date']) }}</td><td>{{ $date($r['first_day']) }} – {{ $date($r['last_day']) }}</td><td class="r num">{{ $r['lines'] }}</td><td class="r num">{{ $r['estimate_cents'] ? $eur($r['estimate_cents']) : '–' }}</td><td>{{ __('site.account.status.'.$r['status']) }}</td></tr>@endforeach
      </tbody></table></div>
    @else<p class="muted">{{ __('site.account.none') }}</p>@endif
  </section>

  @if ($a->isMain())
  <section class="acc-block" id="colleagues">
    <h2>{{ __('site.account.colleagues') }}</h2>
    <p class="muted">{{ __('site.account.colleagues_intro', ['company' => $a->company->name]) }}</p>
    @if ($colleagues->isNotEmpty())
      <div class="tbl"><table><thead><tr><th>{{ __('site.account.col_name') }}</th><th>{{ __('site.account.col_email') }}</th><th>{{ __('site.account.col_role') }}</th><th></th></tr></thead><tbody>
        @foreach ($colleagues as $c)
          <tr @class(['acc-off' => $c->status !== 'active'])><td>{{ $c->name() }}@if ($c->status !== 'active') <small class="muted">· {{ __('site.account.no_access') }}</small>@endif</td><td>{{ $c->email }}</td>
            <td><form method="post" action="{{ route('site.account.colleagues.update', $c->id) }}">@csrf<select name="role" onchange="this.form.submit()" aria-label="{{ __('site.account.col_role') }}">@foreach (\App\Models\Web\CustomerAccount::ROLES as $r)<option value="{{ $r }}" @selected($c->role === $r)>{{ __('site.account.roles.'.$r) }}</option>@endforeach</select></form></td>
            <td class="r"><form method="post" action="{{ route('site.account.colleagues.update', $c->id) }}">@csrf<input type="hidden" name="access" value="{{ $c->status === 'active' ? 'off' : 'on' }}"><button class="btn ghost sm" type="submit">{{ $c->status === 'active' ? __('site.account.remove_access') : __('site.account.give_access') }}</button></form></td></tr>
        @endforeach
      </tbody></table></div>
    @endif
    <form method="post" action="{{ route('site.account.colleagues.invite') }}" class="acc-invite">@csrf
      <h3>{{ __('site.account.invite') }}</h3>
      <div class="two"><div class="field"><label for="inv_first">{{ __('site.account.first_name') }}</label><input id="inv_first" name="first_name" required maxlength="80"></div>
        <div class="field"><label for="inv_last">{{ __('site.account.last_name') }}</label><input id="inv_last" name="last_name" maxlength="80"></div></div>
      <div class="two"><div class="field"><label for="inv_email">{{ __('site.account.email') }}</label><input id="inv_email" name="email" type="email" required maxlength="190"></div>
        <div class="field"><label for="inv_role">{{ __('site.account.col_role') }}</label><select id="inv_role" name="role">@foreach (['order', 'view', 'main'] as $r)<option value="{{ $r }}">{{ __('site.account.roles.'.$r) }}</option>@endforeach</select></div></div>
      <p class="muted small">{{ __('site.account.roles_hint') }}</p>
      <button class="btn" type="submit">{{ __('site.account.invite_go') }}</button>
    </form>
  </section>
  @elseif ($a->company_id)
  <p class="acc-note">{{ __('site.account.your_role', ['role' => __('site.account.roles.'.$a->role)]) }}</p>
  @endif

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
