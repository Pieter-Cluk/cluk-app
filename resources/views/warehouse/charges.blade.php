@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($pending = $charges->where('status', 'pending'))
<x-layouts.app :title="__('warehouse.office.charges')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('warehouse.index') }}">{{ mb_strtoupper(__('warehouse.office.title')) }}</a> · {{ $order->number }}</div>
            <h1>{{ __('warehouse.office.charges_for', ['title' => $order->title]) }}</h1>
            <p class="muted">{{ $order->customerName() }} · {{ __('warehouse.office.charges_intro') }}</p>
        </div>
        @if ($charges->where('status', 'approved')->isNotEmpty())<a class="btn" href="{{ route('warehouse.charges.proforma', $order) }}" target="_blank" rel="noopener">{{ \App\Http\Controllers\Warehouse\WarehouseController::code($order) }}</a>@endif
    </div>
    @include('partials.flash')
    <section class="card card-flush">
        <table class="table">
            <thead><tr><th>{{ __('warehouse.office.what') }}</th><th class="num">{{ __('warehouse.office.amount') }}</th><th>{{ __('warehouse.office.status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($charges as $c)
                    <tr>
                        <td>{{ $c->label }}@if ($c->asset)<br><small class="muted">{{ $c->asset->code }}</small>@endif</td>
                        <td class="num nowrap">
                            @if ($c->status === 'waived')<s>{{ $c->original_cents !== null ? $eur($c->original_cents) : '—' }}</s>
                            @elseif ($c->amount_cents === null)<span class="pill pill-warn">{{ __('warehouse.office.to_price') }}</span>
                            @else{{ $eur($c->amount_cents) }}@if ($c->original_cents !== null && $c->original_cents !== $c->amount_cents)<br><small class="muted">{{ __('warehouse.office.was', ['amount' => $eur($c->original_cents)]) }}</small>@endif
                            @endif
                        </td>
                        <td>{{ __('warehouse.charge_status.'.$c->status) }}@if ($c->decidedBy)<br><small class="muted">{{ $c->decidedBy->name }}</small>@endif</td>
                        <td>
                            @if ($canDecide && $c->status === 'pending')
                                <div class="inline-form">
                                    <form method="post" action="{{ route('warehouse.charge.update', $c) }}" class="inline-form">@csrf<input type="hidden" name="action" value="price"><input name="amount" type="number" step="0.01" min="0" class="w-sm" value="{{ $c->amount_cents !== null ? number_format($c->amount_cents / 100, 2, '.', '') : '' }}" aria-label="{{ __('warehouse.office.amount') }}"><button class="btn btn-sm" type="submit">{{ __('warehouse.office.set') }}</button></form>
                                    @if ($c->amount_cents)<form method="post" action="{{ route('warehouse.charge.update', $c) }}">@csrf<input type="hidden" name="action" value="half"><button class="btn btn-sm" type="submit">−50%</button></form>@endif
                                    <form method="post" action="{{ route('warehouse.charge.update', $c) }}">@csrf<input type="hidden" name="action" value="waive"><button class="btn btn-sm" type="submit">{{ __('warehouse.office.waive') }}</button></form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="pad card-foot">
            <b>{{ __('warehouse.office.total', ['amount' => $eur($pending->sum('amount_cents'))]) }}</b>
            @if ($canDecide && $pending->isNotEmpty())
                <form method="post" action="{{ route('warehouse.charges.approve', $order) }}">@csrf<button class="primary" type="submit">{{ __('warehouse.office.approve') }}</button></form>
            @endif
        </div>
    </section>
    <p class="muted small">{{ __('warehouse.office.approve_rule') }}</p>
</x-layouts.app>
