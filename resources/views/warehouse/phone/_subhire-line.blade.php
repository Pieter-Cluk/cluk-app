{{-- Under a pick line: the pieces a partner delivers, and whether they came in. --}}
@foreach ($l->subhireLines->filter(fn ($s) => $s->request->status === 'accepted') as $s)
    <small @class(['ph-sub', 'ph-sub-ok' => $s->request->received_at])>
        {{ $s->request->received_at ? '✓' : '⏳' }} {{ trans_choice('warehouse.pick.from_partner', $s->qty, ['n' => $s->qty]) }} · {{ $s->request->supplier->name }}
        · @if ($s->request->received_at){{ __('warehouse.sub.in_n', ['n' => $s->received_qty, 'of' => $s->qty]) }}@else<a href="{{ route('w.sub', [$s->request, 'in']) }}">{{ __('warehouse.sub.not_in') }}</a>@endif
    </small>
@endforeach
