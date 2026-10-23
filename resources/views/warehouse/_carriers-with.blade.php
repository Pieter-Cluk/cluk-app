{{-- Light dollies and storage boxes that go with this order (no charge). --}}
@php($withOrder = \App\Models\Warehouse\Carrier::withCount('assets')->where('out_order_id', $order->id)->orderBy('code')->get())
@if ($withOrder->isNotEmpty())
    <{{ $tag ?? 'section' }} class="{{ $class ?? 'ph-card' }}">
        <b>{{ __('warehouse.carriers.with_title') }}</b>
        <p>{{ $withOrder->map(fn ($c) => $c->label())->implode(' · ') }}</p>
        <small class="muted">{{ __('warehouse.carriers.with_hint') }}</small>
    </{{ $tag ?? 'section' }}>
@endif
