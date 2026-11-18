@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($pending = $charges->where('status', 'pending'))
<x-layouts.app :title="__('warehouse.office.charges')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('warehouse.index') }}">{{ mb_strtoupper(__('warehouse.office.title')) }}</a> · {{ $order->number }}</div>
            <h1>{{ __('warehouse.office.charges_for', ['title' => $order->title]) }}</h1>
            <p class="muted">{{ $order->customerName() }} · {{ __('checks.notice.owner_is', ['name' => $owner?->name ?? '—']) }}</p>
        </div>
        @if ($charges->where('status', 'approved')->isNotEmpty())<a class="btn" href="{{ route('warehouse.charges.proforma', $order) }}" target="_blank" rel="noopener">{{ \App\Http\Controllers\Warehouse\WarehouseController::code($order) }}</a>@endif
    </div>
    @include('partials.flash')
    @foreach (\App\Models\Warehouse\Check::with('asset')->where('order_id', $order->id)->whereNotNull('finished_at')->get() as $ck)
        @foreach (\App\Services\Warehouse\TechCheck::runtimeWarnings($ck) as $w)
            @if ($w['warn'] === 'over')<p class="flash flash-warn">⚠ <b>{{ $ck->asset?->code }}</b> · {{ __('checks.run.runtime_over', ['used' => $w['used'], 'allowed' => $w['allowed']]) }} {{ __('checks.run.runtime_owner') }}</p>@endif
        @endforeach
    @endforeach
    @if ($charges->contains('kind', 'missing'))<p class="flash flash-warn"><b>{{ __('checks.notice.incomplete_title') }}</b> · {{ __('checks.notice.incomplete_hint') }}</p>@endif
    @if ($notice)
        <p @class(['flash', 'flash-warn' => in_array($notice->status, ['review', 'objected'], true), 'flash-ok' => in_array($notice->status, ['sent', 'invoiced'], true)])>
            <b>{{ __('checks.notice.status.'.$notice->status) }}</b>
            @if ($notice->status === 'sent') · {{ __('checks.notice.sent_info', ['to' => $notice->sent_to, 'date' => $notice->deadline->format('d/m/Y')]) }} · <a href="{{ route('costs.public', $notice->token) }}" target="_blank" rel="noopener">{{ __('checks.notice.customer_page') }}</a>@endif
            @if ($notice->status === 'objected')<br>“{{ $notice->objection }}”@endif
        </p>
    @elseif (! $allChecked && $charges->where('status', 'pending')->isNotEmpty())
        <p class="flash">{{ __('checks.notice.wait_checks') }}</p>
    @endif
    <section class="card card-flush">
        <table class="table">
            <thead><tr><th>{{ __('warehouse.office.what') }}</th><th class="num">{{ __('warehouse.office.amount') }}</th><th>{{ __('warehouse.office.status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($charges as $c)
                    <tr>
                        <td>{{ $c->label }}@if ($c->asset)<br><small class="muted">{{ $c->asset->code }}</small>@endif
                            @if ($c->check && $c->check->photos)<div class="ch-photos">@foreach ($c->check->photos as $pi => $ph)<a href="{{ route('warehouse.charges.photo', [$c, $pi]) }}" target="_blank"><img src="{{ route('warehouse.charges.photo', [$c, $pi]) }}" alt="" loading="lazy"></a>@endforeach</div>@endif</td>
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
            <b>{{ __('warehouse.office.total', ['amount' => $eur($charges->whereIn('status', ['pending', 'informed'])->sum('amount_cents'))]) }}</b>
            @if ($canDecide && $pending->isNotEmpty())
                <div class="inline-form">
                    <form method="post" action="{{ route('warehouse.charges.inform', $order) }}" class="inline-form">@csrf
                        <input type="email" name="to" value="{{ \App\Services\Warehouse\CostNotices::emailFor($order) }}" class="w-md" aria-label="{{ __('checks.notice.to') }}">
                        <button class="primary" type="submit">{{ __('checks.notice.inform', ['days' => config('warehouse.costs_days', 7)]) }}</button></form>
                    <details><summary>{{ __('checks.notice.invoice_now') }}</summary><form method="post" action="{{ route('warehouse.charges.approve', $order) }}" class="mt">@csrf<p class="muted small">{{ __('checks.notice.invoice_now_hint') }}</p><button class="btn" type="submit">{{ __('warehouse.office.approve') }}</button></form></details>
                </div>
            @elseif ($canDecide && $notice?->status === 'sent')
                <details><summary>{{ __('checks.notice.invoice_now') }}</summary><form method="post" action="{{ route('warehouse.charges.approve', $order) }}" class="mt">@csrf<button class="btn" type="submit">{{ __('warehouse.office.approve') }}</button></form></details>
            @endif
        </div>
    </section>
    @if ($charges->where('status', 'approved')->isNotEmpty())
        <section class="card mt"><h2>{{ __('nav.items.preinvoice') }}</h2>@include('finance._send', ['type' => 'charges', 'id' => $order->id])</section>
    @endif
    <p class="muted small">{{ __('warehouse.office.approve_rule') }}</p>
</x-layouts.app>
