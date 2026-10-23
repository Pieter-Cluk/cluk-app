@php($fmt = fn ($d, $f = 'D j M · H:i') => $d->locale(app()->getLocale())->translatedFormat($f))
<x-layouts.phone :title="__('warehouse.ret.title')" :back="route('w.home')">
    <section class="ph-card">
        <small class="ph-step">{{ str_replace('R-OC', 'R-RET', $order->number) }}</small>
        <b>{{ $order->title }} · {{ $order->customerName() }}</b>
        <small>{{ __('warehouse.home.due', ['when' => $fmt($order->out_until)]) }}</small>
    </section>
    @if ($order->returned_at)
        <p class="flash flash-ok">{{ __('warehouse.ret.all_back', ['when' => $fmt($order->returned_at)]) }}</p>
    @endif
    @include('warehouse.phone._scanbox', ['action' => route('w.return.scan', $order), 'placeholder' => __('warehouse.ret.scan_piece')])

    @if ($retCarrier)
        <section class="ph-card ph-pending">
            <small class="ph-step">{{ __('warehouse.carriers.ret_step') }}</small>
            <b>{{ $retCarrier->label() }}</b>
            <small>{{ trans_choice('warehouse.carriers.count', $retCarrier->assets_count, ['n' => $retCarrier->assets_count]) }}</small>
            <form method="post" action="{{ route('w.return.carrier_done', $order) }}">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.carriers.ret_done') }}</button></form>
        </section>
    @endif
    @include('warehouse._carriers-with')
    <p class="muted small">{{ __('warehouse.carriers.ret_hint') }}</p>

    <ul class="ph-lines">
        @foreach ($lines as $l)
            @if ($l->bySerial())
                @php($back = $l->assets->filter(fn ($oa) => $oa->asset->status !== 'out' || $oa->asset->out_order_id !== $order->id)->count())
                <li @class(['ph-line', 'ph-line-ok' => $back >= $l->assets->count()])>
                    <div class="ph-line-head"><b>{{ $l->name }}</b><span class="ph-cnt">{{ $back }} / {{ $l->assets->count() }}</span></div>
                    <div class="ph-chips">
                        @foreach ($l->assets as $oa)
                            @php($isOut = $oa->asset->status === 'out' && $oa->asset->out_order_id === $order->id)
                            <span @class(['ph-chip', 'ph-chip-ok' => ! $isOut])>{{ $isOut ? '' : '✓ ' }}{{ $oa->asset->code }} <small>{{ $isOut ? __('warehouse.ret.still_out') : \App\Services\Warehouse\Moves::label($oa->asset->location) }}</small></span>
                        @endforeach
                    </div>
                </li>
            @else
                <li @class(['ph-line', 'ph-line-ok' => $l->returned_qty >= $l->picked_qty])>
                    <div class="ph-line-head"><b>{{ $l->name }}</b><span class="ph-cnt">{{ $l->returned_qty }} / {{ $l->picked_qty }}</span></div>
                    <div class="ph-step-btns">
                        <form method="post" action="{{ route('w.count', [$order, $l]) }}">@csrf<input type="hidden" name="field" value="returned_qty"><input type="hidden" name="delta" value="-1"><button class="btn" type="submit" aria-label="−1">−</button></form>
                        <form method="post" action="{{ route('w.count', [$order, $l]) }}">@csrf<input type="hidden" name="field" value="returned_qty"><input type="hidden" name="delta" value="1"><button class="btn" type="submit" aria-label="+1">+</button></form>
                        <form method="post" action="{{ route('w.count', [$order, $l]) }}">@csrf<input type="hidden" name="field" value="returned_qty"><input type="hidden" name="delta" value="{{ $l->picked_qty - $l->returned_qty }}"><button class="btn" type="submit">{{ __('warehouse.pick.all') }}</button></form>
                    </div>
                </li>
            @endif
        @endforeach
    </ul>
    <form method="post" action="{{ route('w.return.close', $order) }}" onsubmit="return confirm(@js(__('warehouse.ret.close_confirm')))">@csrf<button class="btn ph-big" type="submit">{{ __('warehouse.ret.close') }}</button></form>
    <p class="muted small ph-foot">{{ __('warehouse.ret.rule') }}</p>
</x-layouts.phone>
