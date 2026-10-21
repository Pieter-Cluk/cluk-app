@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($open = in_array($po->status, ['sent', 'confirmed'], true) && $canWork)
<x-layouts.app :title="$po->number">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('purchasing.index', ['tab' => 'po']) }}">{{ mb_strtoupper(__('nav.items.purchasing')) }}</a> · {{ $po->number }}</div>
            <h1>{{ __('purchasing.po_title', ['number' => $po->number, 'supplier' => $po->supplier->name]) }}</h1>
            <p class="muted">@if ($po->request)<a href="{{ route('purchasing.pq', $po->request) }}">{{ $po->request->number }}</a>@endif @if ($po->request?->salesQuote) · <a href="{{ route('psales.show', $po->request->salesQuote) }}">{{ $po->request->salesQuote->number }} · {{ $po->request->salesQuote->customerName() }}</a>@endif @if ($po->their_ref) · {{ __('purchasing.their_ref', ['ref' => $po->their_ref]) }}@endif</p>
        </div>
        <span @class(['pill', 'pill-ok' => in_array($po->status, ['confirmed', 'received'], true), 'pill-warn' => $po->status === 'sent', 'pill-danger' => $po->isLate()])>{{ $po->isLate() ? __('purchasing.late') : __('purchasing.po_status.'.$po->status) }}</span>
    </div>
    @include('partials.flash')
    <form method="post" action="{{ route('purchasing.confirm', $po) }}" class="card card-flush">@csrf
        <table class="table">
            <thead><tr><th class="num">{{ __('purchasing.cols.qty') }}</th><th>{{ __('purchasing.cols.items') }}</th><th>{{ __('purchasing.purpose') }}</th><th class="num">{{ __('purchasing.net') }}</th><th class="num">{{ __('purchasing.cols.amount') }}</th><th>{{ __('purchasing.delivery_us') }}</th><th class="num">{{ __('purchasing.received_qty') }}</th></tr></thead>
            <tbody>
                @foreach ($po->lines as $l)
                    <tr><td class="num">{{ $l->qty }}</td><td><b>{{ $l->name }}</b><br><small class="muted">{{ $l->item?->number }}@if ($l->quoteLine) · {{ $l->quoteLine->quote?->number }}@endif</small></td><td>{{ __('purchasing.purposes.'.$l->purpose) }}</td>
                        <td class="num nowrap">{{ $eur($l->unit_cents) }}</td><td class="num nowrap">{{ $eur($l->amount_cents) }}</td>
                        <td>@if ($open)<input type="date" name="dates[{{ $l->id }}]" value="{{ $l->delivery_date?->toDateString() }}" aria-label="{{ __('purchasing.delivery_us') }}">@else{{ $l->delivery_date?->format('d/m/Y') ?? '—' }}@endif</td>
                        <td class="num">{{ $l->received_qty }} / {{ $l->qty }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="pad card-foot">
            <b>{{ __('purchasing.total_excl') }} {{ $eur($po->total_cents) }}</b>
            @if ($open)<span class="inline-form"><input name="their_ref" maxlength="80" value="{{ $po->their_ref }}" placeholder="{{ __('purchasing.their_ref_label') }}" aria-label="{{ __('purchasing.their_ref_label') }}"><button class="primary" type="submit">{{ $po->status === 'confirmed' ? __('purchasing.update_dates') : __('purchasing.mark_confirmed') }}</button></span>@endif
        </div>
    </form>
    <div class="split-2 mt">
        @if ($open)
            <form method="post" action="{{ route('purchasing.receive', $po) }}" class="card stack-sm">@csrf
                <h2>{{ __('purchasing.receive') }}</h2>
                @foreach ($po->lines as $l)
                    @if ($l->received_qty < $l->qty)<label class="field"><span>{{ $l->name }} ({{ __('purchasing.open_qty', ['n' => $l->qty - $l->received_qty]) }})</span><input type="number" min="0" max="{{ $l->qty - $l->received_qty }}" name="qty[{{ $l->id }}]" value="{{ $l->qty - $l->received_qty }}" class="w-sm"></label>@endif
                @endforeach
                <div><button class="btn" type="submit">{{ __('purchasing.mark_received') }}</button></div>
                <small class="muted">{{ __('purchasing.receive_hint') }}</small>
            </form>
        @endif
        <form method="post" action="{{ route('purchasing.invoice', $po) }}" class="card stack-sm">@csrf
            <h2>{{ __('purchasing.invoice') }}</h2>
            @if ($po->invoice_cents !== null)<p>{{ __('purchasing.invoice_is', ['amount' => $eur($po->invoice_cents), 'po' => $eur($po->total_cents)]) }} @if ($po->invoiceDiffers())<span class="pill pill-danger">{{ __('purchasing.po_states.invoice') }}</span>@else<span class="pill pill-ok">{{ __('purchasing.invoice_ok') }}</span>@endif</p>@endif
            @if ($canWork)
                <label class="field"><span>{{ __('purchasing.invoice_amount') }}</span><input type="number" step="0.01" min="0" name="amount" required value="{{ $po->invoice_cents !== null ? number_format($po->invoice_cents / 100, 2, '.', '') : '' }}" class="w-sm"></label>
                <input type="hidden" name="accept" value="0"><label class="check"><input type="checkbox" name="accept" value="1"> {{ __('purchasing.invoice_accept') }}</label>
                <div><button class="btn" type="submit">{{ __('purchasing.save') }}</button></div>
            @endif
        </form>
    </div>
    <p class="mt"><a href="{{ route('purchasing.po.print', $po) }}" target="_blank" rel="noopener">{{ __('purchasing.print_po') }}</a></p>
</x-layouts.app>
