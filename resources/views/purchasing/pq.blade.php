@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($open = in_array($pq->status, ['draft', 'sent'], true) && $canWork)
<x-layouts.app :title="$pq->number">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('purchasing.index') }}">{{ mb_strtoupper(__('nav.items.purchasing')) }}</a> · {{ $pq->number }}</div>
            <h1>{{ __('purchasing.pq_title', ['number' => $pq->number]) }}</h1>
            <p class="muted">@if ($pq->salesQuote){!! __('purchasing.for_sale', ['customer' => e($pq->salesQuote->customerName()), 'quote' => '<a href="'.route('psales.show', $pq->salesQuote).'">'.e($pq->salesQuote->number).'</a>']) !!}@else{{ __('purchasing.for_stock') }}@endif @if ($pq->needed_by) · {{ __('purchasing.needed', ['date' => $pq->needed_by->format('d/m/Y')]) }}@endif</p>
        </div>
        <span @class(['pill', 'pill-ok' => $pq->status === 'ordered', 'pill-warn' => $pq->status === 'sent', 'pill-off' => in_array($pq->status, ['draft', 'cancelled'], true)])>{{ __('purchasing.pq_status.'.$pq->status) }} · {{ __('purchasing.replied', ['n' => $pq->repliedCount(), 'of' => $pq->suppliers->count()]) }}</span>
    </div>
    @include('partials.flash')

    <section class="card">
        <h2>{{ __('purchasing.suppliers') }}</h2>
        @foreach ($pq->suppliers as $s)
            <div class="p-line"><span><b>{{ $s->name }}</b><br><small class="muted">{{ $pq->lines->filter(fn ($l) => ! $l->item || $l->item->suppliers->contains('id', $s->id))->map(fn ($l) => $l->qty.' × '.$l->name)->implode(' · ') }}</small></span>
                <span class="muted">{{ $s->pivot->replied_at ? __('purchasing.replied_on', ['date' => $s->pivot->replied_at->format('d/m H:i')]) : ($s->pivot->sent_at ? __('purchasing.sent_on', ['date' => $s->pivot->sent_at->format('d/m H:i')]) : __('purchasing.not_sent')) }}</span></div>
        @endforeach
        @if ($pq->status === 'draft' && $canWork)<form method="post" action="{{ route('purchasing.send', $pq) }}" class="mt">@csrf<button class="primary" type="submit">{{ __('purchasing.send_all') }}</button></form>@endif
        <p class="mt"><a href="{{ route('purchasing.pq.print', $pq) }}" target="_blank" rel="noopener">{{ __('purchasing.print_pq') }}</a></p>
    </section>

    <form method="post" action="{{ route('purchasing.replies', $pq) }}" class="card">@csrf
        <h2>{{ __('purchasing.compare') }}</h2>
        <p class="muted small">{{ __('purchasing.compare_hint') }}</p>
        @foreach ($pq->lines as $l)
            @php($cheap = $l->cheapest())
            @php($fast = $l->fastest())
            @php($sell = $l->quoteLine?->unit_cents)
            <div class="pq-item">
                <b>{{ $l->qty }} × {{ $l->name }}</b>
                <small class="muted">{{ $l->item?->number }} · {{ __('psales.items.list_price') }} {{ $eur($l->item?->list_price_cents) }}@if ($sell && $seeMargin) · {{ __('purchasing.we_sell', ['price' => $eur($sell)]) }}@endif · {{ __('purchasing.purposes.'.$l->purpose) }}</small>
                <div class="pq-row muted small"><span></span><span>{{ __('purchasing.cols.supplier') }}</span><span>{{ __('purchasing.their_disc') }}</span><span>{{ __('purchasing.net') }}</span><span>{{ __('purchasing.delivery_us') }}</span><span>@if ($seeMargin){{ __('psales.cols.margin') }}@endif</span><span></span></div>
                @foreach ($l->item?->suppliers ?? [] as $s)
                    @php($r = $l->replies->firstWhere('company_id', $s->id))
                    @php($disc = $r?->net_cents !== null && $l->item->list_price_cents ? round((1 - $r->net_cents / $l->item->list_price_cents) * 100, 1) : null)
                    <label @class(['pq-row', 'pq-wait' => ! $r])>
                        <input type="radio" name="choose[{{ $l->id }}]" value="{{ $s->id }}" @checked($l->chosen_company_id === $s->id) @disabled(! $open) aria-label="{{ __('purchasing.use', ['name' => $s->name]) }}">
                        <span><b>{{ $s->name }}</b><br><small class="muted">{{ $r ? __('purchasing.replied_usual', ['n' => $s->pivot->discount_bp / 100]) : __('purchasing.waiting_usual', ['n' => $s->pivot->discount_bp / 100]) }}</small></span>
                        <input type="number" step="0.1" min="0" max="100" name="r[{{ $l->id }}][{{ $s->id }}][disc]" value="{{ $disc }}" @disabled(! $open) aria-label="{{ __('purchasing.their_disc') }}">
                        <input type="number" step="0.01" min="0" name="r[{{ $l->id }}][{{ $s->id }}][net]" value="{{ $r?->net_cents !== null ? number_format($r->net_cents / 100, 2, '.', '') : '' }}" @disabled(! $open) aria-label="{{ __('purchasing.net') }}">
                        <input type="date" name="r[{{ $l->id }}][{{ $s->id }}][date]" value="{{ $r?->delivery_date?->toDateString() }}" @disabled(! $open) aria-label="{{ __('purchasing.delivery_us') }}">
                        <span>@if ($seeMargin && $sell && $r?->net_cents !== null)<b @class(['txt-danger' => ($sell - $r->net_cents) / $sell < 0.1])>{{ number_format(($sell - $r->net_cents) / $sell * 100, 1, ',', '') }}%</b>@endif</span>
                        <span class="pq-tag">{{ collect([$cheap && $cheap->company_id === $s->id && $l->replies->whereNotNull('net_cents')->count() > 1 ? __('purchasing.cheapest') : null, $fast && $fast->company_id === $s->id && $l->replies->whereNotNull('delivery_date')->count() > 1 ? __('purchasing.fastest') : null])->filter()->implode(' · ') }}</span>
                    </label>
                @endforeach
            </div>
        @endforeach
        @if ($open)
            <div class="inline-form mt">
                <button class="btn" type="submit">{{ __('purchasing.save_replies') }}</button>
                <button class="primary" type="submit" name="order" value="1">{{ __('purchasing.order') }}</button>
            </div>
            <p class="muted small">{{ __('purchasing.order_hint') }}</p>
        @endif
    </form>

    @if ($pq->orders->isNotEmpty())
        <section class="card">
            <h2>{{ __('purchasing.orders_sent') }}</h2>
            @foreach ($pq->orders as $o)<div class="p-line"><a href="{{ route('purchasing.po', $o) }}"><b>{{ $o->number }}</b> · {{ $o->supplier->name }}</a><span>{{ $eur($o->total_cents) }} · {{ __('purchasing.po_status.'.$o->status) }}</span></div>@endforeach
        </section>
    @endif
    @if ($open)
        <details class="card"><summary>{{ __('purchasing.cancel') }}</summary><form method="post" action="{{ route('purchasing.cancel', $pq) }}" class="mt">@csrf<button class="btn btn-danger" type="submit">{{ __('purchasing.cancel_go') }}</button></form></details>
    @endif
</x-layouts.app>
