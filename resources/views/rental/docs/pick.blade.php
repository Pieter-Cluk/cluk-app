@extends('rental.docs.layout')
@php($lines = $order->lines->filter(fn ($l) => $l->needsStock()))
@php($kg = 0)
@section('body')
    <table class="doc-table doc-check">
        <thead><tr><th>{{ __('docs.cols.location_item') }}</th><th class="num">{{ __('docs.cols.required') }}</th><th class="box">{{ __('docs.cols.picked') }}</th><th>{{ __('docs.cols.assets') }}</th></tr></thead>
        <tbody>
            @foreach ($lines as $line)
                @php($kg += (float) $line->qty * (float) ($line->item->kind === 'package' ? $line->item->totalWeight() : $line->item->weight_kg))
                @php($own = $line->ownAllocated())
                <tr class="row-main">
                    <td><b>{{ $line->name }}</b><br><small>{{ $line->number }}</small></td>
                    <td class="num"><b>{{ $line->pieces() }}</b></td>
                    <td class="box">☐</td>
                    <td>
                        @if ($line->bySerial())
                            @foreach ($line->assets->sortBy(fn ($a) => $a->asset->location.$a->asset->code) as $oa)
                                <span class="tag">☐ {{ $oa->asset->code }}@if ($oa->asset->location) · {{ $oa->asset->location }}@endif</span>
                            @endforeach
                        @elseif ($own)
                            {{ __('docs.from_stock', ['n' => $own]) }}
                        @endif
                        @foreach ($line->subhireLines->filter(fn ($s) => $s->request->status === 'accepted') as $s)
                            <div class="sub-note">{{ __('docs.subhired', ['n' => $s->qty, 'partner' => $s->request->supplier->name, 'number' => $s->request->confirm_number, 'date' => $s->request->delivery_at?->format('d/m H:i')]) }}</div>
                        @endforeach
                    </td>
                </tr>
                @foreach ($line->item->kind === 'package' ? $line->item->contents : $line->item->kit as $c)
                    <tr class="row-sub">
                        <td>↳ {{ $c->name }} <small>{{ $c->number }}</small></td>
                        <td class="num">{{ $line->pieces() * (int) $c->pivot->qty }}</td>
                        <td class="box">☐</td>
                        <td><small>{{ $line->item->kind === 'package' ? __('docs.in_package') : __('docs.kit') }}</small></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    <p class="doc-summary"><b>{{ __('docs.summary') }}:</b> {{ trans_choice('docs.lines_count', $lines->count(), ['n' => $lines->count()]) }} · {{ __('docs.pieces', ['n' => $lines->sum(fn ($l) => $l->pieces())]) }}@if ($kg > 0) · {{ number_format($kg, 0, ',', '.') }} kg @endif</p>
@endsection
@section('notes')
    <b>{{ __('docs.notes_title.pick') }}</b>
    <p>{{ __('docs.notes.pick') }}</p>
@endsection
@section('signs')
    @include('rental.docs._signs', ['a' => 'picked', 'b' => 'checked'])
@endsection
