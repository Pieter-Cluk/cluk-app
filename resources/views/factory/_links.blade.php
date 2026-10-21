{{-- Documents for the customer and the equipment (rental) for the event. --}}
@php($cq = $b->currentQuote())
@php($gq = $b->gearQuote)
@php($canSell = auth()->user()->canUseModule('sales', 2))
@if (in_array($b->status, ['option', 'confirmed', 'done'], true))
    <section class="card">
        <h2>{{ __('factory.docs_title') }}</h2>
        <ul class="plain-list">
            <li><a href="{{ route('factory.print', $b) }}" target="_blank" rel="noopener">{{ __('factory.doc.titles.bc') }}</a> <small class="muted">{{ __('factory.doc.bc_hint') }}</small></li>
            @if ($cq?->status === 'accepted')
                <li><a href="{{ route('factory.print', [$b, 'type' => 'pf']) }}" target="_blank" rel="noopener">{{ __('factory.doc.titles.pf') }}</a> <small class="muted">{{ __('factory.doc.pf_hint') }}</small></li>
            @endif
        </ul>
    </section>
@endif
@if (in_array($b->status, ['confirmed', 'done'], true))
    <section class="card">
        <h2>{{ __('factory.gear.heading') }}</h2>
        @if ($gq)
            @php($gq = \App\Models\Sales\Quote::where('number', $gq->number)->orderByDesc('version')->get()->pipe(fn ($all) => $all->firstWhere('status', 'accepted') ?? $all->first()))
            <p><a href="{{ route('quotes.show', $gq) }}">{{ $gq->code() }}</a> @include('sales.quotes._status', ['quote' => $gq])
                <br><small class="muted">{{ trans_choice('factory.gear.lines', $n = $gq->lines()->where('kind', 'item')->count(), ['n' => $n]) }}</small></p>
            @if ($gq->order)
                <p><a href="{{ route('orders.show', $gq->order) }}">{{ $gq->order->number }}</a> @include('rental.orders._state', ['state' => $gq->order->state()])</p>
            @elseif ($canWork && $canSell && in_array($gq->status, ['draft', 'change'], true))
                @error('gear')<div class="flash flash-err" role="alert">{{ $message }}</div>@enderror
                <form method="post" action="{{ route('factory.gear.included', $b) }}">@csrf<button class="primary" type="submit" @disabled($n === 0)>{{ __('factory.gear.included') }}</button></form>
                <small class="muted">{{ __('factory.gear.included_hint') }}</small>
            @endif
        @elseif ($canWork && $canSell)
            <form method="post" action="{{ route('factory.gear', $b) }}">@csrf<button class="btn" type="submit">{{ __('factory.gear.make') }}</button></form>
            <small class="muted">{{ __('factory.gear.make_hint') }}</small>
        @else
            <p class="muted">{{ __('factory.gear.none') }}</p>
        @endif
    </section>
@endif
