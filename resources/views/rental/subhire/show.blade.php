@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'))
@php($edit = $canWork && $editable)
<x-layouts.app :title="$r->code()">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('subhire.index') }}">{{ mb_strtoupper(__('orders.subhire_title')) }}</a> · <a href="{{ route('orders.show', $r->order) }}">{{ $r->order->number }}</a></div>
            <h1>{{ $r->code() }} · {{ $r->supplier->name }}</h1>
            <p class="muted">{{ __('orders.sub_for', ['title' => $r->order->title, 'customer' => $r->order->customerName(), 'name' => $r->order->assignee?->name ?? '—']) }}</p>
        </div>
        @include('rental.subhire._status', ['r' => $r])
    </div>
    @include('partials.flash')

    <p class="muted">{{ __('orders.sub_state.'.$r->status, ['to' => $r->sent_to, 'date' => $r->sent_at?->format('d/m/Y H:i'), 'by' => $r->acceptedBy?->name, 'when' => $r->accepted_at?->format('d/m/Y H:i')]) }}</p>

    <div class="split">
        <div class="stack">
            @if ($edit)<form method="post" action="{{ route('subhire.update', $r) }}" id="sub-form">@csrf @method('PUT')@endif
            <section class="card">
                <h2>{{ __('orders.partner_quote') }}</h2>
                <div class="form-grid">
                    <label class="field"><span>{{ __('orders.delivery') }}</span>@if ($edit)<input type="datetime-local" name="delivery_at" required value="{{ $r->delivery_at?->format('Y-m-d\TH:i') }}">@else<b>{{ $r->delivery_at?->format('d/m/Y H:i') }}</b>@endif</label>
                    <label class="field"><span>{{ __('orders.return') }}</span>@if ($edit)<input type="datetime-local" name="return_at" required value="{{ $r->return_at?->format('Y-m-d\TH:i') }}">@else<b>{{ $r->return_at?->format('d/m/Y H:i') }}</b>@endif</label>
                    <label class="field"><span>{{ __('orders.their_ref') }}</span>@if ($edit)<input name="their_ref" maxlength="80" value="{{ $r->their_ref }}">@else<b>{{ $r->their_ref ?: '—' }}</b>@endif</label>
                    <div class="field"><span>{{ __('orders.days_charged') }}</span><b>{{ $r->days() }}</b></div>
                </div>
            </section>

            <section class="card">
                <h2>{{ __('orders.lines_prices') }}</h2>
                <div class="table-wrap">
                    <table class="table table-tight quote-lines">
                        <thead><tr><th>{{ __('quotes.cols.item') }}</th><th class="num">{{ __('quotes.cols.qty') }}</th><th class="num">{{ __('orders.our_last') }}</th><th class="num">{{ __('orders.their_price') }}</th><th class="num">{{ __('quotes.factor') }}</th><th class="num">{{ __('quotes.cols.discount') }}</th><th class="num">{{ __('orders.amount') }}</th>@if ($canWork && $r->status === 'draft')<th></th>@endif</tr></thead>
                        <tbody>
                            @foreach ($r->lines as $l)
                                <tr>
                                    <td><b>{{ $l->name }}</b>@if ($l->item)<br><small class="muted">{{ $l->item->number }}</small>@endif</td>
                                    @if ($edit)
                                        <td class="num"><input type="number" name="lines[{{ $l->id }}][qty]" min="1" value="{{ $l->qty }}" class="in-num in-sm" aria-label="{{ __('quotes.cols.qty') }}"></td>
                                        <td class="num muted">{{ $l->last_price_cents !== null ? $eur($l->last_price_cents) : '—' }}</td>
                                        <td class="num"><input type="number" step="0.01" min="0" name="lines[{{ $l->id }}][price]" value="{{ $l->price_cents !== null ? number_format($l->price_cents / 100, 2, '.', '') : '' }}" class="in-num" aria-label="{{ __('orders.their_price') }}"></td>
                                        <td class="num"><input type="number" step="0.01" min="0" name="lines[{{ $l->id }}][factor]" value="{{ $num($l->factor) }}" class="in-num in-sm" aria-label="{{ __('quotes.factor') }}"></td>
                                        <td class="num"><input type="number" step="0.1" min="0" max="100" name="lines[{{ $l->id }}][discount]" value="{{ $l->discount_bp ? $num($l->discount_bp / 100) : '' }}" placeholder="0" class="in-num in-sm" aria-label="{{ __('quotes.cols.discount') }}"></td>
                                    @else
                                        <td class="num">{{ $l->qty }}</td>
                                        <td class="num muted">{{ $l->last_price_cents !== null ? $eur($l->last_price_cents) : '—' }}</td>
                                        <td class="num">{{ $l->price_cents !== null ? $eur($l->price_cents) : '—' }}</td>
                                        <td class="num">{{ $num($l->factor) }}</td>
                                        <td class="num">{{ $l->discount_bp ? $num($l->discount_bp / 100).'%' : '' }}</td>
                                    @endif
                                    <td class="num nowrap"><b>{{ $eur($l->amount_cents) }}</b>@if ($l->price_cents !== null && $l->last_price_cents)<br><small @class(['muted' => $l->price_cents <= $l->last_price_cents, 'txt-warn' => $l->price_cents > $l->last_price_cents])>{{ $l->price_cents === $l->last_price_cents ? __('orders.same_price') : ($l->price_cents > $l->last_price_cents ? '+' : '−').$eur(abs($l->price_cents - $l->last_price_cents)) }}</small>@endif</td>
                                    @if ($canWork && $r->status === 'draft')<td><button type="submit" form="rm-{{ $l->id }}" class="iconbtn" aria-label="{{ __('quotes.remove') }}: {{ $l->name }}">✕</button></td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-grid mt">
                    <label class="field"><span>{{ __('orders.extra') }}</span>@if ($edit)<input type="number" step="0.01" min="0" name="extra" value="{{ $r->extra_cents ? number_format($r->extra_cents / 100, 2, '.', '') : '' }}" placeholder="€">@else<b>{{ $eur($r->extra_cents) }}</b>@endif</label>
                    <label class="field"><span>{{ __('orders.extra_text') }}</span>@if ($edit)<input name="extra_text" maxlength="200" value="{{ $r->extra_text }}" placeholder="{{ __('orders.extra_hint') }}">@else<b>{{ $r->extra_text ?: '—' }}</b>@endif</label>
                    <label class="field span-2"><span>{{ __('orders.note') }}</span>@if ($edit)<textarea name="note" rows="2" maxlength="2000">{{ $r->note }}</textarea>@else{{ $r->note ?: '—' }}@endif</label>
                </div>
                <div class="totals"><div class="tot tot-grand"><span>{{ __('orders.partner_total') }}</span><span>{{ $eur($r->total_cents) }}</span></div></div>
                @if ($edit)<div class="form-actions"><button class="primary" type="submit">{{ __('orders.save_prices') }}</button></div>@endif
            </section>
            @if ($edit)</form>@endif
            @if ($canWork && $r->status === 'draft')
                @foreach ($r->lines as $l)<form method="post" action="{{ route('subhire.line.remove', [$r, $l]) }}" id="rm-{{ $l->id }}" hidden>@csrf @method('DELETE')</form>@endforeach
            @endif
        </div>

        <aside class="stack">
            @if ($seeMargin)
                @php($rev = $r->revenueCents())
                @php($margin = $rev - $r->total_cents)
                <section class="card">
                    <h2>{{ __('orders.pays_off') }}</h2>
                    <dl class="facts">
                        <dt>{{ __('orders.we_charge') }}</dt><dd>{{ $eur($rev) }}</dd>
                        <dt>{{ __('orders.partner_total') }}</dt><dd>{{ $r->hasPrices() ? $eur($r->total_cents) : '—' }}</dd>
                        <dt>{{ __('orders.margin') }}</dt>@if ($r->hasPrices())<dd @class(['txt-warn' => $rev > 0 && $margin / $rev < 0.15])><b>{{ $eur($margin) }}</b>@if ($rev > 0) ({{ round($margin / $rev * 100) }}%)@endif</dd>@else<dd class="muted">—</dd>@endif
                    </dl>
                    <p class="muted small">{{ __('orders.margin_hint') }}</p>
                </section>
            @endif
            @if ($canWork)
                <section class="card">
                    <h2>{{ __('orders.next_step') }}</h2>
                    @if ($r->status === 'draft')
                        <p class="muted small">{{ __('orders.draft_hint') }}</p>
                        <a class="btn" href="{{ route('orders.show', $r->order) }}">{{ __('orders.to_order') }}</a>
                    @elseif ($r->status === 'sent')
                        <p class="muted small">{{ __('orders.sent_hint') }}</p>
                        <form method="post" action="{{ route('subhire.accept', $r) }}">@csrf<button class="primary" type="submit" @disabled(! $r->hasPrices())>{{ __('orders.accept_go') }}</button></form>
                        @unless ($r->hasPrices())<small class="muted">{{ __('orders.errors.prices_first') }}</small>@endunless
                        <form method="post" action="{{ route('subhire.na', $r) }}" class="mt">@csrf<button class="btn" type="submit">{{ __('orders.na_go') }}</button></form>
                        <form method="post" action="{{ route('subhire.resend', $r) }}" class="mt">@csrf<button class="btn" type="submit">{{ __('orders.remind') }}</button></form>
                    @endif
                    @if (in_array($r->status, ['draft', 'sent'], true))
                        <details class="mt"><summary>{{ __('orders.sub_cancel') }}</summary><form method="post" action="{{ route('subhire.cancel', $r) }}" class="mt">@csrf<button class="btn btn-danger" type="submit">{{ __('orders.sub_cancel_go') }}</button></form></details>
                    @endif
                </section>
            @endif
            <section class="card">
                <h2>{{ __('orders.partner') }}</h2>
                <p><a href="{{ route('companies.show', $r->supplier) }}"><b>{{ $r->supplier->name }}</b></a>@if ($r->supplier->email)<br><a href="mailto:{{ $r->supplier->email }}">{{ $r->supplier->email }}</a>@endif @if ($r->supplier->phone)<br>{{ $r->supplier->phone }}@endif</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
