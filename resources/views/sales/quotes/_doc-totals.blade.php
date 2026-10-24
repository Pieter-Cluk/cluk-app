{{-- Totals on customer documents (quote, order confirmation, proforma): $doc is a quote or an order; transport shows when we deliver. --}}
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
<div class="doc-totals">
    @if ($doc->discount_cents || $doc->logistics === 'delivery')
        <div><span>{{ __('quotes.subtotal_plain') }}</span><span>{{ $eur($doc->subtotal_cents) }}</span></div>
    @endif
    @if ($doc->discount_cents)
        <div><span>{{ __('quotes.project_discount') }} {{ $num($doc->project_discount_bp / 100) }}%</span><span>− {{ $eur($doc->discount_cents) }}</span></div>
    @endif
    @if ($doc->logistics === 'delivery')
        <div><span>{{ __('quotes.transport.transport_line') }}</span><span>{{ $doc->transportToPrice() ? __('quotes.transport.price_follows') : $eur($doc->transport_cents) }}</span></div>
    @endif
    <div class="strong"><span>{{ __('quotes.total_excl') }}</span><span>{{ $eur($doc->net_cents) }}</span></div>
    <div class="muted"><span>{{ __('quotes.vat', ['pct' => $doc->vatPercent()]) }}</span><span>{{ $eur($doc->vat_cents) }}</span></div>
    <div class="grand"><span>{{ $grand ?? __('quotes.total') }}</span><span>{{ $eur($doc->gross_cents) }}</span></div>
</div>
