<x-layouts.phone :title="__('warehouse.tabs.scan')" :back="route('w.home')">
    @if ($carrier)
        <section class="ph-card ph-pending">
            <b>{{ $carrier->item->name }} · {{ $carrier->code }}</b>
            @unless ($carrier->out_order_id)
                <form method="post" action="{{ route('w.carrier.mode') }}" class="ph-seg">@csrf
                    <button type="submit" name="mode" value="add" @class(['ph-seg-on' => $mode === 'add'])>＋ {{ __('warehouse.carriers.mode_add') }}</button>
                    <button type="submit" name="mode" value="remove" @class(['ph-seg-on' => $mode === 'remove'])>－ {{ __('warehouse.carriers.mode_remove') }}</button>
                </form>
                <small class="ph-step">{{ __('warehouse.carriers.step_'.$mode) }}</small>
            @endunless
            <small>{{ __('warehouse.scan.now', ['place' => \App\Services\Warehouse\Moves::label($carrier->location)]) }}@if ($carrier->outOrder) · {{ __('warehouse.carriers.with_order', ['order' => $carrier->outOrder->number]) }}@endif</small>
            @if ($carrier->packed->isEmpty() && $carrier->counted->isEmpty())
                <p class="muted">{{ __('warehouse.carriers.empty') }}</p>
            @else
                <ul class="ph-list">
                    @foreach ($carrier->packed as $a)
                        <li><b>{{ $a->code }}</b> {{ $a->item->name }}
                            @unless ($carrier->out_order_id)<form method="post" action="{{ route('w.carrier.out', $a) }}" class="ph-inline">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.carriers.take_out') }}</button></form>@endunless</li>
                    @endforeach
                    @foreach ($carrier->counted as $ci)
                        <li><b>{{ $ci->qty }} ×</b> {{ $ci->item->type ?: $ci->item->name }} <small class="muted">· {{ __('warehouse.carriers.counted') }}</small></li>
                    @endforeach
                </ul>
            @endif
            @php($total = $carrier->packed->count() + $carrier->counted->sum('qty'))
            <small>{{ trans_choice('warehouse.carriers.count', $total, ['n' => $total]) }}</small>
            <form method="post" action="{{ route('w.scan.cancel') }}">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.carriers.close') }}</button></form>
        </section>
    @elseif ($pendingItem ?? null)
        <section class="ph-card ph-pending">
            <b>{{ $pendingItem->number }} · {{ $pendingItem->type ?: $pendingItem->name }}</b>
            <small>{{ __('warehouse.carriers.counted_where', ['n' => $pendingItem->stock_qty, 'place' => $pendingItem->placesLabel() ?: '—', 'left' => $pendingItem->unplacedQty()]) }}</small>
            @if ($pendingPlace)
                @php($atPlace = $pendingItem->places->firstWhere('position_code', $pendingPlace))
                <form method="post" action="{{ route('w.scan.counted') }}" class="ph-count-form">
                    @csrf
                    <label><span>{{ __('warehouse.carriers.counted_how_many', ['place' => \App\Services\Warehouse\Moves::label($pendingPlace)]) }}</span>
                        <input type="number" name="qty" inputmode="numeric" min="0" max="100000" required autofocus value="{{ old('qty', $atPlace?->qty ?? $pendingItem->unplacedQty()) }}"></label>
                    <button class="primary" type="submit">{{ __('warehouse.carriers.counted_save') }}</button>
                </form>
                @error('qty')<p class="txt-danger small">{{ $message }}</p>@enderror
            @else
                <small class="ph-step">{{ __('warehouse.carriers.counted_scan_place') }}</small>
            @endif
            <form method="post" action="{{ route('w.scan.cancel') }}">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.scan.cancel') }}</button></form>
        </section>
    @elseif ($pending)
        <section class="ph-card ph-pending">
            <small class="ph-step">{{ __('warehouse.scan.step2') }}</small>
            <b>{{ $pending->code }} · {{ $pending->item->name }}</b>
            <small>{{ __('warehouse.scan.now', ['place' => \App\Services\Warehouse\Moves::label($pending->location)]) }} · {{ __('equipment.statuses.'.$pending->statusKey()) }}</small>
            @if ($next)<small>{{ __('warehouse.scan.next', ['places' => implode(', ', $next)]) }}</small>@endif
            <form method="post" action="{{ route('w.scan.cancel') }}">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.scan.cancel') }}</button></form>
        </section>
    @else
        <p class="ph-step">{{ __('warehouse.scan.step1') }}</p>
    @endif

    @include('warehouse.phone._scanbox', ['action' => route('w.scan.post'), 'scanned' => $scanned, 'autofocus' => ! ($pendingPlace ?? null)])

    @if ($here)
        <section class="ph-card">
            <b>{{ $here['label'] }}</b> <small class="muted">{{ $here['code'] }}</small>
            @foreach ($here['carriers'] as $c)
                @php($n = $c->packed->count() + (int) $c->counted->sum('qty'))
                <p><b>{{ $c->item->name }} · {{ $c->code }}</b> <small class="muted">· {{ trans_choice('warehouse.carriers.count', $n, ['n' => $n]) }}</small>
                    @if ($n > 0)<br><small class="muted">{{ \Illuminate\Support\Str::limit($c->packed->map(fn ($a) => $a->code.' '.$a->item->name)->merge($c->counted->map(fn ($x) => $x->qty.' × '.($x->item->type ?: $x->item->name)))->implode(' · '), 160) }}</small>@endif</p>
            @endforeach
            @if ($here['counted']->isNotEmpty())
                <ul class="ph-list">@foreach ($here['counted'] as $c)<li><b>{{ $c['loose'] }} ×</b> {{ $c['item']->type ?: $c['item']->name }} <small class="muted">· {{ $c['item']->number }} · {{ __('warehouse.scan.counted_here', ['n' => $c['item']->stock_qty]) }}</small></li>@endforeach</ul>
            @endif
            @if ($here['assets']->isEmpty() && $here['carriers']->isEmpty() && $here['counted']->isEmpty())
                <p class="muted">{{ __('warehouse.scan.empty_place') }}</p>
            @elseif ($here['assets']->isNotEmpty())
                <ul class="ph-list">@foreach ($here['assets'] as $a)<li><b>{{ $a->code }}</b> {{ $a->item->name }} <small class="muted">· {{ __('equipment.statuses.'.$a->statusKey()) }}</small></li>@endforeach</ul>
            @endif
            <p class="muted small">{{ __('warehouse.scan.here_hint') }}</p>
        </section>
    @endif
    <p class="muted small ph-foot">{{ __('warehouse.scan.hint') }}</p>
</x-layouts.phone>
