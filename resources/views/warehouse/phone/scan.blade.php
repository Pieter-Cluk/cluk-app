<x-layouts.phone :title="__('warehouse.tabs.scan')" :back="route('w.home')">
    @if ($carrier)
        <section class="ph-card ph-pending">
            <small class="ph-step">{{ __('warehouse.carriers.step') }}</small>
            <b>{{ $carrier->item->name }} · {{ $carrier->code }}</b>
            <small>{{ __('warehouse.scan.now', ['place' => \App\Services\Warehouse\Moves::label($carrier->location)]) }}@if ($carrier->outOrder) · {{ __('warehouse.carriers.with_order', ['order' => $carrier->outOrder->number]) }}@endif</small>
            @if ($carrier->packed->isEmpty())
                <p class="muted">{{ __('warehouse.carriers.empty') }}</p>
            @else
                <ul class="ph-list">
                    @foreach ($carrier->packed as $a)
                        <li><b>{{ $a->code }}</b> {{ $a->item->name }}
                            @unless ($carrier->out_order_id)<form method="post" action="{{ route('w.carrier.out', $a) }}" class="ph-inline">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.carriers.take_out') }}</button></form>@endunless</li>
                    @endforeach
                </ul>
            @endif
            <small>{{ trans_choice('warehouse.carriers.count', $carrier->packed->count(), ['n' => $carrier->packed->count()]) }}</small>
            <form method="post" action="{{ route('w.scan.cancel') }}">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.carriers.close') }}</button></form>
        </section>
    @elseif ($pending)
        <section class="ph-card ph-pending">
            <small class="ph-step">{{ __('warehouse.scan.step2') }}</small>
            <b>{{ $pending->code }} · {{ $pending->item->name }}</b>
            <small>{{ __('warehouse.scan.now', ['place' => \App\Services\Warehouse\Moves::label($pending->location)]) }} · {{ __('equipment.statuses.'.$pending->status) }}</small>
            @if ($next)<small>{{ __('warehouse.scan.next', ['places' => implode(', ', $next)]) }}</small>@endif
            <form method="post" action="{{ route('w.scan.cancel') }}">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.scan.cancel') }}</button></form>
        </section>
    @else
        <p class="ph-step">{{ __('warehouse.scan.step1') }}</p>
    @endif

    @include('warehouse.phone._scanbox', ['action' => route('w.scan.post'), 'scanned' => $scanned, 'autofocus' => true])

    @if ($here)
        <section class="ph-card">
            <b>{{ $here['label'] }}</b> <small class="muted">{{ $here['code'] }}</small>
            @foreach ($here['carriers'] as $c)
                <p><b>{{ $c->item->name }} · {{ $c->code }}</b> <small class="muted">· {{ trans_choice('warehouse.carriers.count', $c->packed_count, ['n' => $c->packed_count]) }}</small></p>
            @endforeach
            @if ($here['assets']->isEmpty() && $here['carriers']->isEmpty())
                <p class="muted">{{ __('warehouse.scan.empty_place') }}</p>
            @elseif ($here['assets']->isNotEmpty())
                <ul class="ph-list">@foreach ($here['assets'] as $a)<li><b>{{ $a->code }}</b> {{ $a->item->name }} <small class="muted">· {{ __('equipment.statuses.'.$a->status) }}</small></li>@endforeach</ul>
            @endif
            <p class="muted small">{{ __('warehouse.scan.here_hint') }}</p>
        </section>
    @endif
    <p class="muted small ph-foot">{{ __('warehouse.scan.hint') }}</p>
</x-layouts.phone>
