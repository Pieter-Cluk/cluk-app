@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
@php($bank = \App\Models\BankAccount::where('on_documents', true)->orderByDesc('is_main')->first())
@php($pay = $order->company?->customer_payment_days ?? (int) $me->payment_term_days)
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $order->number }} · {{ $order->title }}</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
</head>
<body class="doc-body">
<div class="doc-bar no-print"><button type="button" class="primary" onclick="window.print()">{{ __('quotes.print_save') }}</button></div>
<article class="doc">
    <header class="doc-head">
        <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="34">
        <div class="doc-me"><b>{{ trim($me->legal_name.' '.$me->legal_form) }}</b><br>{{ $me->street }}, {{ $me->postcode }} {{ $me->city }}<br>{{ \App\Support\VatNumber::format($me->vat_number) }}@if ($me->email) · {{ $me->email }}@endif</div>
    </header>
    <div class="doc-title">
        <div>
            <div class="doc-kind">{{ mb_strtoupper(__('orders.doc_title')) }}@if ($order->status !== 'approved') · {{ mb_strtoupper(__('orders.doc_draft')) }}@endif</div>
            <h1>{{ $order->title }}</h1>
        </div>
        <div class="doc-code"><b>{{ $order->number }}</b><br>{{ ($order->approved_at ?? now())->format('d/m/Y') }}</div>
    </div>
    <div class="doc-parties">
        <div>
            <div class="doc-label">{{ mb_strtoupper(__('orders.confirmed_to')) }}</div>
            @if ($order->company)
                <b>{{ $order->company->name }}</b><br>
                @if ($order->contact){{ __('quotes.attn', ['name' => $order->contact->name]) }}<br>@endif
                @if ($order->company->street){{ $order->company->street }}<br>{{ $order->company->postcode }} {{ $order->company->city }}<br>@endif
                @if ($order->company->vat_number){{ $order->company->formattedVat() }}@endif
            @else
                <b>{{ $order->contact?->name ?? $order->confirmed_name ?? '—' }}</b>
            @endif
        </div>
        <dl class="doc-meta">
            <dt>{{ __('quotes.doc_use') }}</dt><dd>{{ $order->use_from->format('d/m/Y') }} – {{ $order->use_until->format('d/m/Y') }}</dd>
            <dt>{{ __('quotes.doc_pickup') }}</dt><dd>{{ $order->out_from->format('d/m/Y H:i') }}</dd>
            <dt>{{ __('quotes.doc_return') }}</dt><dd>{{ $order->out_until->format('d/m/Y H:i') }}</dd>
            @if ($order->location)<dt>{{ __('quotes.location') }}</dt><dd>{{ $order->location }}</dd>@endif
            @if ($order->po_number)<dt>{{ __('quotes.po') }}</dt><dd>{{ $order->po_number }}</dd>@endif
            @if ($order->quote)<dt>{{ __('orders.based_on') }}</dt><dd>{{ $order->quote->code() }}</dd>@endif
        </dl>
    </div>
    <p class="doc-intro">{{ __('orders.doc_intro') }}</p>
    <table class="doc-table">
        <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th><th class="num">{{ __('quotes.factor') }}</th><th class="num">{{ __('quotes.cols.rate') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th></tr></thead>
        <tbody>
            @foreach ($order->lines as $line)
                @if ($line->kind === 'text')
                    <tr class="doc-text"><td></td><td colspan="4"><i>{{ $line->name }}</i></td></tr>
                @else
                    <tr>
                        <td class="num">{{ $line->qtyLabel() }}</td>
                        <td>{{ $line->name }}@if ($line->discount_bp) <small>({{ __('quotes.line_discount', ['pct' => $num($line->discount_bp / 100)]) }})</small>@endif</td>
                        <td class="num">{{ number_format((float) $line->factor, 2, ',', '.') }}</td>
                        <td class="num nowrap">{{ $eur($line->unit_price_cents) }}</td>
                        <td class="num nowrap">{{ $eur($line->amount_cents) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    <div class="doc-totals">
        @if ($order->discount_cents)
            <div><span>{{ __('quotes.subtotal_plain') }}</span><span>{{ $eur($order->subtotal_cents) }}</span></div>
            <div><span>{{ __('quotes.project_discount') }} {{ $num($order->project_discount_bp / 100) }}%</span><span>− {{ $eur($order->discount_cents) }}</span></div>
        @endif
        <div class="strong"><span>{{ __('quotes.total_excl') }}</span><span>{{ $eur($order->net_cents) }}</span></div>
        <div class="muted"><span>{{ __('quotes.vat', ['pct' => $order->vatPercent()]) }}</span><span>{{ $eur($order->vat_cents) }}</span></div>
        <div class="grand"><span>{{ __('quotes.total') }}</span><span>{{ $eur($order->gross_cents) }}</span></div>
    </div>
    <section class="doc-notes">
        <b>{{ __('orders.doc_arrangements') }}</b>
        <p>{{ __('orders.doc_pickup_text', ['address' => $me->warehouse_address ?: $me->street.', '.$me->postcode.' '.$me->city]) }}</p>
        <p>{{ $pay > 0 ? __('quotes.doc_pay_days', ['days' => $pay]) : __('quotes.doc_pay_now') }}</p>
        <p>{{ __('quotes.doc_terms_link', ['url' => preg_replace('#^https?://#', '', route('terms', $order->language))]) }}</p>
    </section>
    @include('partials.doc-extras', ['type' => 'R-OC'])
    <footer class="doc-foot">
        {{ trim($me->legal_name.' '.$me->legal_form) }} · {{ $me->street }}, {{ $me->postcode }} {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}@if ($bank) · IBAN {{ $bank->formattedIban() }}@endif
        @if ($order->handedBy)<br>{{ __('quotes.prepared_by', ['name' => $order->handedBy->name, 'email' => $order->handedBy->email]) }}@endif
    </footer>
</article>
</body>
</html>
