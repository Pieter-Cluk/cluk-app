@php($fmt = fn ($d, $f = 'D j M · H:i') => $d->locale(app()->getLocale())->translatedFormat($f))
<x-layouts.phone :title="__('warehouse.app')">
    <p class="ph-hello">{{ __('warehouse.hello', ['name' => auth()->user()->first_name, 'date' => $fmt(now(), 'D j M')]) }}</p>
    <a class="primary ph-big" href="{{ route('w.scan') }}">▣ {{ __('warehouse.tabs.scan') }}</a>

    <h2 class="ph-h">{{ __('warehouse.home.picks') }}</h2>
    @forelse ($picks as $o)
        <a class="ph-task" href="{{ route('w.pick', $o) }}">
            <span class="tag tag-b">{{ __('warehouse.home.tag_pick') }}</span>
            <b>{{ $o->title }}</b>
            <small>{{ $o->customerName() }} · {{ __('warehouse.home.leaves', ['when' => $fmt($o->out_from)]) }}</small>
            <small class="ph-prog">{{ \App\Services\Warehouse\Moves::isPicked($o) ? __('warehouse.home.ready_to_load') : $o->number }}</small>
        </a>
    @empty
        <p class="muted">{{ __('warehouse.home.no_picks') }}</p>
    @endforelse

    <h2 class="ph-h">{{ __('warehouse.home.returns') }}</h2>
    @forelse ($returns as $o)
        @php($left = \App\Models\Equipment\Asset::where('out_order_id', $o->id)->where('status', 'out')->count())
        <a class="ph-task" href="{{ route('w.return', $o) }}">
            <span @class(['tag', 'tag-late' => $o->out_until->isPast()])>{{ __('warehouse.home.tag_return') }}</span>
            <b>{{ $o->title }}</b>
            <small>{{ $o->customerName() }} · {{ __('warehouse.home.due', ['when' => $fmt($o->out_until)]) }}</small>
            <small class="ph-prog">{{ trans_choice('warehouse.home.still_out', $left, ['n' => $left]) }}</small>
        </a>
    @empty
        <p class="muted">{{ __('warehouse.home.no_returns') }}</p>
    @endforelse

    <h2 class="ph-h">{{ __('warehouse.home.route') }}</h2>
    @php($order = $zones->keys()->flip())
    @forelse ($route->sortBy(fn ($v, $k) => $order[$k] ?? 999) as $code => $pieces)
        <details class="ph-zone">
            <summary><b>{{ \App\Services\Warehouse\Moves::label($code) }}</b><span class="pill">{{ $pieces->count() }}</span></summary>
            <ul>@foreach ($pieces->sortBy('code') as $a)<li><b>{{ $a->code }}</b> {{ $a->item->name }}@if ($a->status === 'repair') · <span class="txt-danger">{{ __('equipment.statuses.repair') }}</span>@endif</li>@endforeach</ul>
        </details>
    @empty
        <p class="muted">{{ __('warehouse.home.route_empty') }}</p>
    @endforelse
    <p class="muted small ph-foot">{{ __('warehouse.home.route_hint') }}</p>
</x-layouts.phone>
