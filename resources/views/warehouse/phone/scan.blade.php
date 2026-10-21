<x-layouts.phone :title="__('warehouse.tabs.scan')" :back="route('w.home')">
    @if ($pending)
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
            @if ($here['assets']->isEmpty())
                <p class="muted">{{ __('warehouse.scan.empty_place') }}</p>
            @else
                <ul class="ph-list">@foreach ($here['assets'] as $a)<li><b>{{ $a->code }}</b> {{ $a->item->name }} <small class="muted">· {{ __('equipment.statuses.'.$a->status) }}</small></li>@endforeach</ul>
            @endif
            <p class="muted small">{{ __('warehouse.scan.here_hint') }}</p>
        </section>
    @endif
    <p class="muted small ph-foot">{{ __('warehouse.scan.hint') }}</p>
</x-layouts.phone>
