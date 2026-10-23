@extends('rental.docs.layout')
@section('body')
    <table class="doc-table doc-check">
        <thead><tr><th>{{ __('docs.cols.item') }}</th><th class="num">{{ __('docs.cols.expected') }}</th><th class="num">{{ __('docs.cols.received') }}</th><th>{{ __('docs.cols.condition') }}</th></tr></thead>
        <tbody>
            @foreach ($order->lines->filter(fn ($l) => $l->needsStock()) as $line)
                <tr class="row-main">
                    <td><b>{{ $line->name }}</b><br><small>{{ $line->number }}@if ($line->assets->isNotEmpty()) · {{ $line->assets->map(fn ($a) => $a->asset->code)->implode(', ') }}@endif</small>
                        @foreach ($line->subhireLines->filter(fn ($s) => $s->request->status === 'accepted') as $s)
                            <div class="sub-note">{{ __('docs.subhire_back', ['n' => $s->qty, 'partner' => $s->request->supplier->name, 'date' => $s->request->return_at?->format('d/m H:i')]) }}</div>
                        @endforeach
                    </td>
                    <td class="num"><b>{{ $line->pieces() }}</b></td>
                    <td class="num write">&nbsp;</td>
                    <td class="write">&nbsp;</td>
                </tr>
                @foreach ($line->item->kind === 'package' ? $line->item->contents : $line->item->kit as $c)
                    <tr class="row-sub"><td>↳ {{ $c->name }}</td><td class="num">{{ $line->pieces() * (int) $c->pivot->qty }}</td><td class="num write">&nbsp;</td><td class="write">&nbsp;</td></tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    @include('warehouse._carriers-with', ['tag' => 'div', 'class' => 'doc-blank'])
    <p class="doc-summary">☐ {{ __('docs.ret.complete') }} &nbsp; ☐ {{ __('docs.ret.partial') }} &nbsp; ☐ {{ __('docs.ret.released') }} &nbsp; ☐ {{ __('docs.ret.hold') }}</p>
@endsection
@section('notes')
    <b>{{ __('docs.notes_title.ret') }}</b>
    <p>{{ __('docs.notes.ret') }}</p>
@endsection
@section('signs')
    @include('rental.docs._signs', ['a' => 'returned', 'b' => 'received_checked'])
@endsection
