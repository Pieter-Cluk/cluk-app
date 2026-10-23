{{-- Cases (light dollies, storage boxes…) that go with this order at no charge: not on the order, picked with it. --}}
@php($withOrder = \App\Models\Equipment\Asset::with('item')->where('out_order_id', $order->id)->whereHas('item', fn ($q) => $q->where('kind', 'case'))
    ->whereNotIn('id', $order->assets()->pluck('asset_id'))->orderBy('code')->get())
@if ($withOrder->isNotEmpty())
    <{{ $tag ?? 'section' }} class="{{ $class ?? 'ph-card' }}">
        <b>{{ __('warehouse.carriers.with_title') }}</b>
        <p>{{ $withOrder->map(fn ($c) => $c->item->name.' '.$c->code)->implode(' · ') }}</p>
        <small class="muted">{{ __('warehouse.carriers.with_hint') }}</small>
    </{{ $tag ?? 'section' }}>
@endif
