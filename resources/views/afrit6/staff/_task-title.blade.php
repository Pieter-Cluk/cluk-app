@switch($t->kind)
    @case('room_reset'){{ __('workspace.tasks.room_reset_text', ['time' => $t->booking?->timeRange() ?? '']) }}@break
    @case('desk_swap'){{ __('workspace.tasks.desk_swap_text', ['from' => $t->fromSpace?->code, 'to' => $t->space?->code]) }}@break
    @case('desk_add'){{ __('workspace.tasks.desk_add_text', ['desk' => $t->space?->code]) }}@break
    @case('shelf') @case('pallet'){{ __('workspace.tasks.storage_text', ['qty' => $t->qty, 'what' => __('workspace.kinds.'.$t->kind)]) }}@break
    @default{{ \App\Services\Afrit6\StatementBuilder::counts($t->colis, $t->pallets) }}@if ($t->kind === 'receive') · {{ __('workspace.tasks.receive_'.($t->receive_mode ?? 'shelf24')) }}@endif @if ($t->then_send) · {{ __('workspace.tasks.then_send') }}@endif
@endswitch
