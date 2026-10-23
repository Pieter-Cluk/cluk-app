@php($fmt = fn ($d, $f = 'D j M · H:i') => $d->locale(app()->getLocale())->translatedFormat($f))
@php($code = str_replace('R-OC', 'R-PICK', $order->number))
<x-layouts.phone :title="__('warehouse.pick.title')" :back="route('w.home')">
    <section class="ph-card">
        <small class="ph-step">{{ $code }}</small>
        <b>{{ $order->title }} · {{ $order->customerName() }}</b>
        <small>{{ __('warehouse.home.leaves', ['when' => $fmt($order->out_from)]) }}@if ($order->location) · {{ $order->location }}@endif</small>
    </section>
    @if ($order->loaded_at)
        <p class="flash flash-ok">{{ __('warehouse.pick.is_loaded', ['when' => $fmt($order->loaded_at)]) }}</p>
    @else
        @include('warehouse.phone._scanbox', ['action' => route('w.pick.scan', $order), 'placeholder' => __('warehouse.pick.scan_piece')])
    @endif

    @include('warehouse._carriers-with')
    <p class="muted small">{{ __('warehouse.carriers.pick_hint') }}</p>

    <ul class="ph-lines">
        @foreach ($lines as $l)
            @php($subWait = $l->subhireLines->contains(fn ($s) => $s->request->status === 'accepted' && ! $s->request->received_at))
            @if ($l->bySerial())
                @php($done = $l->assets->filter(fn ($oa) => $oa->asset->location === 'DISPATCH' && $oa->asset->out_order_id === $order->id)->count())
                <li @class(['ph-line', 'ph-line-ok' => $done >= $l->assets->count() && ! $subWait])>
                    <div class="ph-line-head"><b>{{ $l->name }}</b><span class="ph-cnt">{{ $done }} / {{ $l->assets->count() }}</span></div>
                    <div class="ph-chips">
                        @foreach ($l->assets->sortBy(fn ($oa) => $oa->asset->location) as $oa)
                            @php($ok = $oa->asset->location === 'DISPATCH' && $oa->asset->out_order_id === $order->id)
                            <span @class(['ph-chip', 'ph-chip-ok' => $ok])>{{ $ok ? '✓ ' : '' }}{{ $oa->asset->code }} <small>{{ $ok ? '' : ($oa->asset->location ?: '—') }}</small></span>
                        @endforeach
                    </div>
                    @include('warehouse.phone._subhire-line')
                </li>
            @else
                <li @class(['ph-line', 'ph-line-ok' => $l->picked_qty >= $l->ownAllocated() && ! $subWait])>
                    <div class="ph-line-head"><b>{{ $l->name }}</b><span class="ph-cnt">{{ $l->picked_qty }} / {{ $l->ownAllocated() }}</span></div>
                    <small class="muted">{{ $l->item->position_code ? __('warehouse.pick.kept_at', ['place' => $l->item->position_code]) : __('warehouse.pick.counted') }}</small>
                    @include('warehouse.phone._subhire-line')
                    @unless ($order->loaded_at)
                        <div class="ph-step-btns">
                            <form method="post" action="{{ route('w.count', [$order, $l]) }}">@csrf<input type="hidden" name="field" value="picked_qty"><input type="hidden" name="delta" value="-1"><button class="btn" type="submit" aria-label="−1">−</button></form>
                            <form method="post" action="{{ route('w.count', [$order, $l]) }}">@csrf<input type="hidden" name="field" value="picked_qty"><input type="hidden" name="delta" value="1"><button class="btn" type="submit" aria-label="+1">+</button></form>
                            <form method="post" action="{{ route('w.count', [$order, $l]) }}">@csrf<input type="hidden" name="field" value="picked_qty"><input type="hidden" name="delta" value="{{ $l->ownAllocated() - $l->picked_qty }}"><button class="btn" type="submit">{{ __('warehouse.pick.all') }}</button></form>
                        </div>
                    @endunless
                </li>
            @endif
        @endforeach
    </ul>
    @php($waiting = \App\Services\Warehouse\Moves::subhireWaiting($order))
    @foreach ($waiting as $w)
        <a class="ph-task" href="{{ route('w.sub', [$w, 'in']) }}"><span class="tag tag-sub">{{ __('warehouse.sub.tag_in') }}</span><b>{{ $w->supplier->name }}</b><small>{{ __('warehouse.sub.expected', ['when' => $w->delivery_at ? $fmt($w->delivery_at) : '—']) }}</small><small class="ph-prog">{{ __('warehouse.sub.blocks_load') }}</small></a>
    @endforeach
    @unless ($order->loaded_at)
        <form method="post" action="{{ route('w.load', $order) }}">@csrf<button class="primary ph-big" type="submit" @disabled(! $picked)>{{ $picked ? __('warehouse.pick.load') : __('warehouse.pick.not_yet') }}</button></form>
    @endunless
    <p class="muted small ph-foot">{{ __('warehouse.pick.rule') }}</p>
</x-layouts.phone>
