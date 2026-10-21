@php($fmt = fn ($d) => $d->locale(app()->getLocale())->translatedFormat('D j M'))
@php($mine = $task->assignee_id === auth()->id() || ! $task->assignee_id)
<x-layouts.phone :title="__('workspace.kinds.'.$task->kind)" :back="route('w.a6')">
    <section class="ph-card">
        <small class="ph-step">{{ __('workspace.kinds.'.$task->kind) }} · {{ __('warehouse.a6.status.'.$task->status) }}</small>
        <b>{{ $task->member?->companyName() }} · {{ $task->member?->name }}</b>
        <small>{{ $fmt($task->date) }}@if ($task->colis) · {{ trans_choice('warehouse.colli_n', $task->colis, ['n' => $task->colis]) }}@endif @if ($task->pallets) · {{ trans_choice('warehouse.pallets_n', $task->pallets, ['n' => $task->pallets]) }}@endif @if ($task->from_text) · {{ $task->from_text }}@endif @if ($task->carrier) · {{ $task->carrier }}@endif</small>
        @if ($task->note)<small>“{{ $task->note }}”</small>@endif
        @if ($task->assignee)<small>{{ __('warehouse.a6.has_it', ['name' => $task->assignee->name]) }}</small>@endif
    </section>
    @if ($task->status === 'pending')<p class="flash flash-err">{{ __('workspace.tasks.confirm_first') }}</p>@endif

    @if ($mine && $task->status !== 'done')
        <h2 class="ph-h">1 · {{ __('warehouse.a6.photo') }}</h2>
        <form method="post" action="{{ route('w.a6.photo', $task) }}" enctype="multipart/form-data">@csrf
            <div class="ph-photos">
                @foreach ($task->photos ?? [] as $i => $p)<a href="{{ route('w.media', ['a6', $task->id, $i]) }}" target="_blank"><img src="{{ route('w.media', ['a6', $task->id, $i]) }}" alt="" loading="lazy"></a>@endforeach
                <label class="ph-photo-add">＋<span>{{ __('warehouse.photo') }}</span><input type="file" name="photos[]" accept="image/*" capture="environment" multiple data-autosend hidden></label>
            </div>
        </form>
        <h2 class="ph-h">2 · {{ __('warehouse.a6.scan_places') }}</h2>
        @if (! empty($task->scans))<ul class="ph-list">@foreach ($task->scans as $s)<li>✓ <b>{{ \App\Services\Warehouse\Moves::label($s['code']) }}</b> <small class="muted">{{ $s['code'] }}</small></li>@endforeach</ul>@endif
        @include('warehouse.phone._scanbox', ['action' => route('w.a6.scan', $task), 'placeholder' => __('warehouse.a6.scan_hint')])
        <form method="post" action="{{ route('w.a6.done', $task) }}">@csrf
            @if ($task->kind === 'packing')
                <label class="ph-acc"><span><b>{{ __('warehouse.a6.colli_packed') }}</b><small>{{ __('warehouse.a6.billed_real') }}</small></span><input type="number" name="colis" min="0" max="9999" value="{{ $task->colis }}" class="ph-qty"></label>
            @endif
            <button class="primary ph-big" type="submit" @disabled($task->status !== 'confirmed')>{{ __('warehouse.a6.done') }}</button>
        </form>
    @elseif ($task->status === 'done')
        <p class="flash flash-ok">{{ __('warehouse.a6.is_done') }}</p>
    @endif
    <p class="muted small ph-foot"><a href="{{ route('afrit6.staff.tasks.show', $task) }}">{{ __('warehouse.a6.office_link') }}</a></p>
</x-layouts.phone>
