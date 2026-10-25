@extends('site.layout', ['active' => 'account'])
@section('title', __('site.account.inv_title', ['number' => $pf->invoice_number]))
@section('description', __('site.account.inv_title', ['number' => $pf->invoice_number]))
@section('body', 'sl')
@section('content')
@php
    $eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c);
    $date = fn ($d) => $d ? $d->locale(app()->getLocale())->translatedFormat('j M Y') : '–';
    $status = $pf->status === 'paid' ? 'paid' : ($pf->isOverdue() ? 'late' : 'open');
@endphp
<main class="acc-page gut inv">
  <p class="inv-bar"><a href="{{ route('site.account') }}#invoices">{{ __('site.account.inv_back') }}</a>
    <span><button class="btn ghost sm" type="button" onclick="window.print()">{{ __('site.account.inv_print') }}</button>@if ($pf->ubl_path) <a class="btn ghost sm" href="{{ route('site.account.invoice.xml', $pf->id) }}">{{ __('site.account.inv_xml') }}</a>@endif</span></p>
  <article class="inv-doc">
    <header class="inv-head">
      <div><h1>{{ __('site.account.inv_title', ['number' => $pf->invoice_number]) }}</h1><span class="acc-pill acc-{{ $status }}">{{ __('site.account.inv_status.'.$status) }}</span></div>
      <div class="inv-from"><b>{{ trim($me->legal_name.' '.$me->legal_form) }}</b><br>{{ $me->street }}<br>{{ $me->postcode }} {{ $me->city }}<br>{{ \App\Support\VatNumber::format($me->vat_number) }}</div>
    </header>
    <div class="inv-meta">
      <div><small>{{ __('site.account.inv_to') }}</small><b>{{ $pf->company?->name }}</b>@if ($pf->company?->street)<br>{{ $pf->company->street }}<br>{{ $pf->company->postcode }} {{ $pf->company->city }}@endif @if ($pf->company?->vat_number)<br>{{ \App\Support\VatNumber::format($pf->company->vat_number) }}@endif</div>
      <div><small>{{ __('site.account.inv_date') }}</small><b>{{ $date($pf->invoice_date) }}</b></div>
      <div><small>{{ __('site.account.inv_due') }}</small><b>{{ $date($pf->due_date) }}</b></div>
      @if ($pf->po_number)<div><small>{{ __('site.account.col_po') }}</small><b>{{ $pf->po_number }}</b></div>@endif
    </div>
    <p>{{ $pf->description }}</p>
    <div class="tbl"><table><thead><tr><th>{{ __('site.account.inv_lines') }}</th><th class="r">{{ __('site.account.inv_qty') }}</th><th class="r">{{ __('site.account.inv_unit') }}</th><th class="r">{{ __('site.account.inv_amount') }}</th></tr></thead><tbody>
      @foreach ($pf->lines as $l)@if ((int) $l['amount_cents'] !== 0)<tr><td>{{ $l['d'] }}</td><td class="r num">{{ $l['q'] }}</td><td class="r num">{{ $eur($l['unit_cents']) }}</td><td class="r num">{{ $eur($l['amount_cents']) }}</td></tr>@endif @endforeach
    </tbody></table></div>
    <div class="inv-tot num">
      <div><span>{{ __('site.account.inv_net') }}</span><span>{{ $eur($pf->net_cents) }}</span></div>
      <div><span>{{ __('site.account.inv_vat', ['pct' => rtrim(rtrim(number_format($pf->vat_rate_bp / 100, 2, ',', ''), '0'), ',').'%']) }}</span><span>{{ $eur($pf->vat_cents) }}</span></div>
      <div class="g"><span>{{ __('site.account.inv_gross') }}</span><span>{{ $eur($pf->gross_cents) }}</span></div>
    </div>
    <p class="inv-pay">{{ $status === 'paid' ? __('site.account.inv_paid') : __('site.account.inv_pay', ['amount' => $eur($pf->gross_cents), 'iban' => $bank?->formattedIban() ?? '—', 'ref' => $pf->invoice_number]) }}</p>
  </article>
</main>
@endsection
