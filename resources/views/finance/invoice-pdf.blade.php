{{-- The printable invoice (PDF via dompdf: simple tables, no flex or grid). --}}
@php
    $eur = fn ($c) => '€ '.number_format(((int) $c) / 100, 2, ',', '.');
    $d = fn ($x) => $x?->format('d/m/Y');
    $lines = collect($pf->lines)->filter(fn ($l) => (int) ($l['amount_cents'] ?? 0) !== 0 || filled($l['d'] ?? null));
@endphp
<!doctype html>
<html lang="{{ $loc }}">
<head>
<meta charset="utf-8">
<title>{{ __('invoice.title') }} {{ $pf->invoice_number }}</title>
<style>
    @page { margin: 18mm 16mm 22mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1f2420; }
    table { border-collapse: collapse; width: 100%; }
    .top td { vertical-align: top; }
    .logo { height: 34px; }
    .me { font-size: 8.5pt; color: #4a524c; line-height: 1.45; text-align: right; }
    h1 { font-size: 22pt; margin: 22px 0 2px; letter-spacing: .5px; }
    .num { text-align: right; white-space: nowrap; }
    .facts td { padding: 2px 0; font-size: 9pt; }
    .facts td.k { color: #676e69; width: 38%; }
    .to { font-size: 10pt; line-height: 1.45; }
    .label { font-size: 7.5pt; letter-spacing: 1px; color: #52ae32; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
    .lines { margin-top: 18px; }
    .lines th { font-size: 8pt; text-transform: uppercase; letter-spacing: .5px; color: #676e69; text-align: left; border-bottom: 1.5px solid #1f2420; padding: 6px 4px; }
    .lines th.num { text-align: right; }
    .lines td { padding: 6px 4px; border-bottom: 1px solid #e5e9e4; vertical-align: top; }
    .totals { margin-top: 10px; width: 46%; margin-left: 54%; }
    .totals td { padding: 4px 4px; }
    .totals .grand td { border-top: 1.5px solid #1f2420; font-weight: bold; font-size: 11pt; padding-top: 7px; }
    .pay { margin-top: 22px; background: #f2f7ef; border-left: 3px solid #52ae32; padding: 10px 12px; font-size: 9pt; line-height: 1.5; }
    .pay .qr { float: right; width: 110px; text-align: center; margin: -2px 0 0 12px; }
    .pay .qr img { display: block; margin: 0 auto 2px; background: #fff; }
    .pay .qr small { font-size: 7pt; color: #555; line-height: 1.2; }
    .note { margin-top: 10px; font-size: 8.5pt; color: #4a524c; }
    .foot { position: fixed; bottom: -14mm; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #676e69; }
</style>
</head>
<body>
<div class="foot">{{ collect([trim(($me->legal_name ?: 'CLUK').' '.($me->legal_form ?: '')), trim(($me->street ?: '').', '.($me->postcode ?: '').' '.($me->city ?: ''), ' ,'), $me->vat_number ? $vatFmt($me->vat_number) : null, $me->rpr ? 'RPR '.$me->rpr : null, $me->website])->filter()->implode(' · ') }}@if ($me->footer_line)<br>{{ $me->footer_line }}@endif</div>

<table class="top"><tr>
    <td>@if ($logo)<img class="logo" src="{{ $logo }}" alt="CLUK">@else<b>CLUK</b>@endif</td>
    <td class="me">
        <b>{{ trim(($me->legal_name ?: 'CLUK').' '.($me->legal_form ?: '')) }}</b><br>
        {{ $me->street }}<br>{{ trim($me->postcode.' '.$me->city) }}@if ($me->country && $me->country !== 'BE'), {{ $me->country }}@endif<br>
        @if ($me->vat_number){{ __('invoice.vat') }} {{ $vatFmt($me->vat_number) }}<br>@endif
        {{ collect([$me->phone, $me->invoice_email ?: $me->email])->filter()->implode(' · ') }}
    </td>
</tr></table>

<h1>{{ mb_strtoupper(__('invoice.title')) }}</h1>

<table class="top" style="margin-top: 10px"><tr>
    <td style="width: 52%">
        <div class="label">{{ __('invoice.to') }}</div>
        <div class="to"><b>{{ $buyer['name'] }}</b><br>
            @if ($buyer['street']){{ $buyer['street'] }}<br>@endif
            @if ($buyer['postcode'] || $buyer['city']){{ trim($buyer['postcode'].' '.$buyer['city']) }}<br>@endif
            @if ($buyer['country'] && $buyer['country'] !== 'BE'){{ $buyer['country'] }}<br>@endif
            @if ($buyer['vat']){{ __('invoice.vat') }} {{ $vatFmt($buyer['vat']) }}@endif
        </div>
    </td>
    <td style="width: 48%">
        <table class="facts">
            <tr><td class="k">{{ __('invoice.number') }}</td><td><b>{{ $pf->invoice_number }}</b></td></tr>
            <tr><td class="k">{{ __('invoice.date') }}</td><td>{{ $d($pf->invoice_date) }}</td></tr>
            @if ($pf->due_date)<tr><td class="k">{{ __('invoice.due') }}</td><td>{{ $d($pf->due_date) }}</td></tr>@endif
            @if ($pf->service_date)<tr><td class="k">{{ __('invoice.service_date') }}</td><td>{{ $d($pf->service_date) }}</td></tr>@endif
            @if ($pf->po_number)<tr><td class="k">{{ __('invoice.your_ref') }}</td><td>{{ $pf->po_number }}</td></tr>@endif
            @if ($pf->source_code)<tr><td class="k">{{ __('invoice.our_ref') }}</td><td>{{ $pf->source_code }}</td></tr>@endif
        </table>
    </td>
</tr></table>

@if ($pf->description)<p style="margin: 18px 0 0"><b>{{ $pf->description }}</b></p>@endif

<table class="lines">
    <thead><tr><th>{{ __('invoice.desc') }}</th><th class="num">{{ __('invoice.qty') }}</th><th class="num">{{ __('invoice.unit') }}</th><th class="num">{{ __('invoice.amount') }}</th></tr></thead>
    <tbody>
        @foreach ($lines as $l)
            <tr><td>{{ $l['d'] ?? '' }}</td><td class="num">{{ $l['q'] ?? '' }}</td><td class="num">@if (isset($l['unit_cents'])){{ $eur($l['unit_cents']) }}@endif</td><td class="num">{{ $eur($l['amount_cents'] ?? 0) }}</td></tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>{{ __('invoice.subtotal') }}</td><td class="num">{{ $eur($pf->net_cents) }}</td></tr>
    <tr><td>{{ __('invoice.vat_line', ['rate' => rtrim(rtrim(number_format($pf->vat_rate_bp / 100, 2, ',', ''), '0'), ',')]) }}</td><td class="num">{{ $eur($pf->vat_cents) }}</td></tr>
    <tr class="grand"><td>{{ __('invoice.total') }}</td><td class="num">{{ $eur($pf->gross_cents) }}</td></tr>
</table>

@if ($exempt)<p class="note">{{ $exempt }}</p>@endif

<div class="pay">
    @if ($bank)
        @if ($qr ?? null)<div class="qr"><img src="{{ $qr }}" alt="" width="104" height="104"><small>{{ __('invoice.scan') }}</small></div>@endif
        {{ __('invoice.pay_by', ['date' => $d($pf->due_date) ?? '—', 'amount' => $eur($pf->gross_cents)]) }}<br>
        <b>IBAN {{ trim(chunk_split(preg_replace('/\s+/', '', (string) $bank->iban), 4, ' ')) }}</b>@if ($bank->bic) · BIC {{ $bank->bic }}@endif<br>
        {{ __('invoice.reference') }}: <b>{{ $ogm ?? $pf->invoice_number }}</b>
        <div style="clear: both"></div>
    @else
        {{ __('invoice.pay_contact') }}
    @endif
</div>
@if ($pf->status === 'paid')<p class="note"><b>{{ __('invoice.paid', ['date' => $d($pf->paid_at)]) }}</b></p>@endif
</body>
</html>
