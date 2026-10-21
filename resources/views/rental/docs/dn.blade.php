@extends('rental.docs.layout')
@section('body')
    <table class="doc-table doc-check">
        <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th><th>{{ __('docs.cols.reference') }}</th><th class="box">{{ __('docs.cols.delivered') }}</th></tr></thead>
        <tbody>
            @foreach ($order->lines->filter(fn ($l) => $l->needsStock()) as $line)
                <tr>
                    <td class="num"><b>{{ $line->pieces() }}</b></td>
                    <td>{{ $line->name }}@if ($line->item->kind === 'package')<br><small>{{ $line->item->contents->map(fn ($c) => $c->pivot->qty.' × '.$c->name)->implode(' · ') }}</small>@endif @if ($line->item->kit->isNotEmpty())<br><small>{{ __('docs.with') }} {{ $line->item->kit->map(fn ($c) => $c->pivot->qty.' × '.$c->name)->implode(' · ') }}</small>@endif</td>
                    <td><small>{{ $line->assets->map(fn ($a) => $a->asset->code)->implode(', ') }}</small></td>
                    <td class="box">☐</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="doc-blank"><b>{{ __('docs.handover_notes') }}</b><span></span><span></span></div>
@endsection
@section('notes')
    <b>{{ __('docs.notes_title.dn') }}</b>
    <p>{{ __('docs.notes.dn', ['order' => $order->number]) }}</p>
    <p>{{ __('quotes.doc_terms_link', ['url' => preg_replace('#^https?://#', '', route('terms', $order->language))]) }}</p>
@endsection
@section('signs')
    @include('rental.docs._signs', ['a' => 'released', 'b' => 'received'])
@endsection
