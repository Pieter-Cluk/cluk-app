@php($fmt = fn ($d) => $d->locale(app()->getLocale())->translatedFormat('D j M'))
<x-layouts.phone :title="__('warehouse.a6.title')" :back="route('w.home')">
    <p class="muted small">{{ __('warehouse.a6.rule') }}</p>
    @forelse ($tasks as $t)
        <a class="ph-task" href="{{ route('w.a6.show', $t) }}">
            <span @class(['tag', 'tag-a6', 'tag-late' => $t->date->lt(today())])>{{ mb_strtoupper(__('workspace.kinds.'.$t->kind)) }}</span>
            <b>{{ $t->member?->companyName() }}</b>
            <small>{{ $fmt($t->date) }}@if ($t->colis) · {{ trans_choice('warehouse.colli_n', $t->colis, ['n' => $t->colis]) }}@endif @if ($t->pallets) · {{ trans_choice('warehouse.pallets_n', $t->pallets, ['n' => $t->pallets]) }}@endif</small>
            <small class="ph-prog">{{ __('warehouse.a6.status.'.$t->status) }}@if ($t->assignee) · {{ $t->assignee->first_name }}@endif</small>
        </a>
    @empty
        <p class="muted">{{ __('warehouse.a6.none') }}</p>
    @endforelse
</x-layouts.phone>
