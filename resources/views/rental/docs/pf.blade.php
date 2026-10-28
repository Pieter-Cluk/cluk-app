@extends('rental.docs.layout')
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
@php($bank = \App\Models\BankAccount::where('on_documents', true)->orderByDesc('is_main')->first())
@section('body')
    <p class="doc-intro">{{ __('docs.pf.intro', ['order' => $order->number]) }}</p>
    <table class="doc-table">
        <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th><th class="num">{{ __('quotes.factor') }}</th><th class="num">{{ __('quotes.cols.rate') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th></tr></thead>
        <tbody>
            @foreach ($order->lines as $line)
                @if ($line->kind === 'text')
                    <tr class="doc-text"><td></td><td colspan="4"><i>{{ $line->name }}</i></td></tr>
                @else
                    <tr><td class="num">{{ $line->qtyLabel() }}</td><td>{{ $line->name }}@if ($line->discount_bp) <small>({{ __('quotes.line_discount', ['pct' => $num($line->discount_bp / 100)]) }})</small>@endif</td><td class="num">{{ number_format((float) $line->factor, 2, ',', '.') }}</td><td class="num nowrap">{{ $eur($line->unit_price_cents) }}</td><td class="num nowrap">{{ $eur($line->amount_cents) }}</td></tr>
                @endif
            @endforeach
        </tbody>
    </table>
    @include('sales.quotes._doc-totals', ['doc' => $order, 'grand' => __('docs.pf.to_pay')])
@endsection
@section('notes')
    <b>{{ __('docs.notes_title.pf') }}</b>
    <p><b>{{ __('docs.pf.not_invoice') }}</b> {{ __('docs.pf.when') }}</p>
    <dl class="doc-meta pay">
        <dt>{{ __('docs.pf.beneficiary') }}</dt><dd>{{ trim($me->legal_name.' '.$me->legal_form) }}</dd>
        <dt>IBAN</dt><dd>{{ $bank?->formattedIban() ?? __('docs.pf.no_bank') }}</dd>
        @if ($bank?->bic)<dt>BIC</dt><dd>{{ $bank->bic }}</dd>@endif
        <dt>{{ __('docs.pf.reference') }}</dt><dd>{{ $code }}</dd>
    </dl>
    @if ($bank && ($qrText = \App\Support\PaymentQr::payload(trim($me->legal_name.' '.$me->legal_form), (string) $bank->iban, $bank->bic, (int) $order->gross_cents, $code)))
        <p class="pay-qr"><img src="{{ \App\Support\PaymentQr::png($qrText) }}" alt="" width="110" height="110"><br><small>{{ __('invoice.scan') }}</small></p>
    @endif
    <p>{{ __('quotes.doc_terms_link', ['url' => preg_replace('#^https?://#', '', route('terms', $order->language))]) }}</p>
@endsection
