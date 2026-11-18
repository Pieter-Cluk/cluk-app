{{-- Tech Check on the phone: scan the fixture's QR code; its check opens (an unfinished one continues). --}}
<x-layouts.phone :title="__('nav.items.techcheck')" :back="route('w.home')">
    <p class="ph-step">{{ __('checks.run.scan_hint') }}</p>
    @include('warehouse.phone._scanbox', ['action' => route('w.tech.scan'), 'scanned' => null, 'autofocus' => true])
    @error('code')<p class="flash flash-err">{{ $message }}</p>@enderror

    @if ($open->isNotEmpty() || $stored->isNotEmpty())
        <h2 class="ph-h">{{ __('checks.run.busy') }}</h2>
        @foreach ($open as $c)
            @php($a = $c->answers ?? [])
            @php($total = 1 + count($c->procedureVersion?->steps ?? []))
            <a class="ph-task" href="{{ route('w.check', $c->asset) }}">
                <b>{{ $c->asset->item->name }} · {{ $c->asset->code }}</b>
                <small>{{ $c->order?->number ?? __('warehouse.check.no_order') }}</small>
                <small class="ph-prog">@if (! $c->procedure_version_id){{ $c->cleaned_at ? __('checks.tech.cleaned') : __('checks.tech.waiting') }}@elseif (empty($a['_view'])){{ __('checks.run.status_view') }}@else{{ __('checks.run.status_tech', ['by' => $a['_view']['by'] ?? '—', 'n' => min($total, $c->step + 2), 'total' => $total]) }}@endif</small>
            </a>
        @endforeach
        @foreach ($stored as $c)
            @php($a = $c->answers ?? [])
            <a class="ph-task" href="{{ route('w.stored', $c->piece) }}">
                <span class="tag">{{ $c->piece->item->contract->company->name }}</span><b>{{ $c->piece->item->name }} · {{ $c->piece->code }}</b>
                <small class="ph-prog">{{ empty($a['_view']) ? __('checks.run.status_view') : __('checks.run.status_tech', ['by' => $a['_view']['by'] ?? '—', 'n' => $c->step + 2, 'total' => 1 + count($c->procedureVersion?->steps ?? [])]) }}</small>
            </a>
        @endforeach
    @endif
</x-layouts.phone>
