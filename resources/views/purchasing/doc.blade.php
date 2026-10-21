{{-- PQ (price request) or PO (purchase order) as a document for the supplier. --}}
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($isPo = $kind === 'po')
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $doc->number }}</title><link rel="stylesheet" href="{{ asset('css/cluk.css') }}"></head>
<body class="doc-body">
<div class="doc-bar no-print"><button type="button" class="primary" onclick="window.print()">{{ __('quotes.print_save') }}</button></div>
<article class="doc">
    <header class="doc-head">
        <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="34">
        <div class="doc-me"><b>{{ trim($me->legal_name.' '.$me->legal_form) }}</b><br>{{ $me->street }}, {{ $me->postcode }} {{ $me->city }}<br>{{ \App\Support\VatNumber::format($me->vat_number) }}@if ($me->email) · {{ $me->email }}@endif</div>
    </header>
    <div class="doc-title">
        <div><div class="doc-kind">{{ mb_strtoupper(__('documents.types.'.($isPo ? 'PO' : 'PQ'))) }}</div><h1>{{ $isPo ? $doc->supplier->name : __('purchasing.doc.pq_heading') }}</h1></div>
        <div class="doc-code"><b>{{ $doc->number }}</b><br>{{ now()->format('d/m/Y') }}</div>
    </div>
    @if ($isPo)
        <div class="doc-parties"><div><div class="doc-label">{{ mb_strtoupper(__('purchasing.doc.supplier')) }}</div><b>{{ $doc->supplier->name }}</b><br>@if ($doc->supplier->street){{ $doc->supplier->street }}<br>{{ $doc->supplier->postcode }} {{ $doc->supplier->city }}<br>@endif @if ($doc->supplier->vat_number){{ $doc->supplier->formattedVat() }}@endif</div>
            <dl class="doc-meta"><dt>{{ __('purchasing.doc.deliver_to') }}</dt><dd>{{ $me->warehouse_address ?: $me->street.', '.$me->postcode.' '.$me->city }}</dd>@if ($doc->their_ref)<dt>{{ __('purchasing.their_ref_label') }}</dt><dd>{{ $doc->their_ref }}</dd>@endif</dl></div>
    @else
        <p class="doc-intro">{{ __('purchasing.doc.pq_intro') }}</p>
    @endif
    <table class="doc-table">
        <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th>@if ($isPo)<th>{{ __('purchasing.delivery_us') }}</th><th class="num">{{ __('purchasing.net') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th>@else<th>{{ __('purchasing.net') }}</th><th>{{ __('purchasing.delivery_us') }}</th>@endif</tr></thead>
        <tbody>
            @foreach ($doc->lines as $l)
                <tr><td class="num">{{ $l->qty }}</td><td>{{ $l->name }}@if ($l->item)<br><small>{{ $l->item->number }}</small>@endif</td>
                    @if ($isPo)<td>{{ $l->delivery_date?->format('d/m/Y') ?? '—' }}</td><td class="num nowrap">{{ $eur($l->unit_cents) }}</td><td class="num nowrap">{{ $eur($l->amount_cents) }}</td>@else<td>__________</td><td>__________</td>@endif</tr>
            @endforeach
        </tbody>
    </table>
    @if ($isPo)<div class="doc-totals"><div class="grand"><span>{{ __('purchasing.total_excl') }}</span><span>{{ $eur($doc->total_cents) }}</span></div></div>@endif
    <section class="doc-notes"><p>{{ $isPo ? __('purchasing.doc.po_note') : __('purchasing.doc.pq_note') }}</p></section>
    @if ($isPo)<div class="doc-signs"><div class="sign"><span>{{ __('purchasing.doc.sign_cluk') }}</span></div><div class="sign"><span>{{ __('purchasing.doc.sign_supplier') }}</span></div></div>@endif
    <footer class="doc-foot">{{ trim($me->legal_name.' '.$me->legal_form) }} · {{ $me->street }}, {{ $me->postcode }} {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}</footer>
</article>
</body>
</html>
