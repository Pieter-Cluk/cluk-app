{{-- The invoice data, to make the invoice in OkiOki (until the connection is live). --}}
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $pf->number }}</title><link rel="stylesheet" href="{{ asset('css/cluk.css') }}?v={{ @filemtime(public_path('css/cluk.css')) }}"></head>
<body class="doc-body">
<div class="doc-bar no-print"><button type="button" class="primary" onclick="window.print()">{{ __('quotes.print_save') }}</button></div>
<article class="doc">
    <div class="doc-title"><div><div class="doc-kind">{{ mb_strtoupper(__('finance.print_kind')) }}</div><h1>{{ $pf->customerName() }}</h1></div><div class="doc-code"><b>{{ $pf->number }}</b><br>{{ $pf->source_code }}</div></div>
    <div class="doc-parties">
        <div><div class="doc-label">{{ mb_strtoupper(__('finance.facts.customer')) }}</div><b>{{ $pf->customerName() }}</b><br>{{ $pf->company?->address() }}<br>{{ $pf->company?->vat_number ? \App\Support\VatNumber::format($pf->company->vat_number) : '' }}</div>
        <dl class="doc-meta"><dt>{{ __('finance.facts.vat') }}</dt><dd>{{ $pf->vat_code }}</dd><dt>{{ __('finance.facts.po') }}</dt><dd>{{ $pf->po_number ?: '—' }}</dd><dt>{{ __('finance.facts.term') }}</dt><dd>{{ trans_choice('companies.days', $pf->payment_days, ['days' => $pf->payment_days]) }}</dd><dt>{{ __('finance.facts.service') }}</dt><dd>{{ $pf->service_date?->format('d/m/Y') }}</dd></dl>
    </div>
    <table class="doc-table"><thead><tr><th>{{ __('finance.cols.desc') }}</th><th class="num">{{ __('finance.cols.qty') }}</th><th class="num">{{ __('finance.cols.unit') }}</th><th class="num">{{ __('finance.cols.amount') }}</th></tr></thead>
        <tbody>@foreach ($pf->lines as $l)<tr><td>{{ $l['d'] }}</td><td class="num">{{ $l['q'] }}</td><td class="num nowrap">{{ $eur($l['unit_cents']) }}</td><td class="num nowrap">{{ $eur($l['amount_cents']) }}</td></tr>@endforeach</tbody></table>
    <div class="doc-totals"><div><span>{{ __('finance.subtotal') }}</span><span>{{ $eur($pf->net_cents) }}</span></div><div class="muted"><span>{{ $pf->vat_code }}</span><span>{{ $eur($pf->vat_cents) }}</span></div><div class="grand"><span>{{ __('quotes.total') }}</span><span>{{ $eur($pf->gross_cents) }}</span></div></div>
</article>
</body>
</html>
