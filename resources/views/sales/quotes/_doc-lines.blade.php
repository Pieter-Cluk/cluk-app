{{-- The quote lines and totals, as the customer sees them (print and customer page). --}}
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
@php($def = $quote->defaultFactor())
<table class="doc-table">
    <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th><th class="num">{{ __('quotes.factor') }}</th><th class="num">{{ __('quotes.cols.rate') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th></tr></thead>
    <tbody>
        @foreach ($quote->lines as $line)
            @if ($line->kind === 'text')
                <tr class="doc-text"><td></td><td colspan="4"><i>{{ $line->name }}</i></td></tr>
            @else
                <tr>
                    <td class="num">{{ $line->qtyLabel() }}</td>
                    <td>{{ $line->name }}@if ($line->discount_bp) <small>({{ __('quotes.line_discount', ['pct' => $num($line->discount_bp / 100)]) }})</small>@endif</td>
                    <td class="num">{{ number_format($line->effectiveFactor($def), 2, ',', '.') }}</td>
                    <td class="num nowrap">{{ $eur($line->unit_price_cents) }}</td>
                    <td class="num nowrap">{{ $eur($line->amount_cents) }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
<div class="doc-totals">
    @if ($quote->discount_cents)
        <div><span>{{ __('quotes.subtotal_plain') }}</span><span>{{ $eur($quote->subtotal_cents) }}</span></div>
        <div><span>{{ __('quotes.project_discount') }} {{ $num($quote->project_discount_bp / 100) }}%</span><span>− {{ $eur($quote->discount_cents) }}</span></div>
    @endif
    <div class="strong"><span>{{ __('quotes.total_excl') }}</span><span>{{ $eur($quote->net_cents) }}</span></div>
    <div class="muted"><span>{{ __('quotes.vat', ['pct' => $quote->vatPercent()]) }}</span><span>{{ $eur($quote->vat_cents) }}</span></div>
    <div class="grand"><span>{{ __('quotes.total') }}</span><span>{{ $eur($quote->gross_cents) }}</span></div>
</div>
