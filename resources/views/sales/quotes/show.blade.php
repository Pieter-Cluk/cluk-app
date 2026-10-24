@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($num = fn ($n, $d = 2) => rtrim(rtrim(number_format((float) $n, $d, '.', ''), '0'), '.'))
@php($def = $quote->defaultFactor())
<x-layouts.app :title="$quote->code()">
    <div class="pagehead">
        <div>
            <div class="kicker">
                <a href="{{ route('quotes.index') }}">{{ mb_strtoupper(__('quotes.title')) }}</a>
                @if ($quote->deal) · <a href="{{ route('deals.show', $quote->deal) }}">{{ $quote->deal->title }}</a>@endif
            </div>
            <h1>{{ $quote->title }}</h1>
            <p class="muted">{{ __('quotes.kind') }} · <b>{{ $quote->number }}</b> · {{ __('quotes.version', ['n' => $quote->version]) }} · {{ $quote->customerName() }}@if ($quote->contact) · {{ $quote->contact->name }}@endif · {{ __('quotes.vat_line', ['pct' => $quote->vatPercent()]) }} · {{ config('cluk.locales')[$quote->language] ?? $quote->language }}</p>
        </div>
        @include('sales.quotes._status', ['quote' => $quote, 'lg' => true])
    </div>
    @include('partials.flash')

    @if ($quote->status !== 'draft')
        <section @class(['card', 'answer-box', 'answer-'.$quote->displayStatus()])>
            @switch($quote->displayStatus())
                @case('sent')
                    <p>{{ __('quotes.state.sent', ['to' => $quote->sent_to, 'date' => $quote->sent_at?->format('d/m/Y H:i'), 'until' => $quote->reserved_until?->format('d/m/Y H:i'), 'valid' => $quote->valid_until?->format('d/m/Y')]) }}</p>
                    @break
                @case('expired')
                    <p>{{ __('quotes.state.expired', ['date' => $quote->valid_until?->format('d/m/Y')]) }}</p>
                    @break
                @case('accepted')
                    <p><b>{{ __('quotes.state.accepted', ['name' => $quote->answered_name, 'date' => $quote->answered_at?->format('d/m/Y H:i')]) }}</b>@if ($quote->po_number) · {{ __('quotes.po') }}: <b>{{ $quote->po_number }}</b>@endif</p>
                    @break
                @case('change')
                    <p><b>{{ __('quotes.state.change', ['name' => $quote->answered_name ?: '—', 'date' => $quote->answered_at?->format('d/m/Y H:i')]) }}</b></p>
                    <blockquote>{!! nl2br(e($quote->answer_note)) !!}</blockquote>
                    @break
                @case('declined')
                    <p><b>{{ __('quotes.state.declined', ['reason' => __('quotes.decline_reasons.'.$quote->decline_reason), 'date' => $quote->answered_at?->format('d/m/Y H:i')]) }}</b></p>
                    @if ($quote->answer_note)<blockquote>{{ $quote->answer_note }}</blockquote>@endif
                    @break
                @case('replaced')
                    <p>{{ __('quotes.state.replaced') }}</p>
                    @break
            @endswitch
            <div class="form-actions">
                @if ($quote->token)<a class="btn" href="{{ route('quote.public', $quote->token) }}" target="_blank" rel="noopener">{{ __('quotes.customer_page') }}</a>@endif
                <a class="btn" href="{{ route('quotes.print', $quote) }}" target="_blank" rel="noopener">{{ __('quotes.print') }}</a>
                @if ($canWork && $quote->isLatest() && in_array($quote->status, ['sent', 'change', 'declined'], true))
                    <form method="post" action="{{ route('quotes.revise', $quote) }}">@csrf<button class="primary" type="submit">{{ __('quotes.revise') }}</button></form>
                @endif
            </div>
            @if ($canWork && in_array($quote->status, ['sent', 'change'], true))
                <details class="mt">
                    <summary>{{ __('quotes.mark_accepted') }}</summary>
                    <p class="muted small">{{ __('quotes.mark_accepted_hint') }}</p>
                    <form method="post" action="{{ route('quotes.accepted', $quote) }}" class="inline-form">
                        @csrf
                        <input name="name" required maxlength="120" placeholder="{{ __('quotes.confirmed_by') }}" aria-label="{{ __('quotes.confirmed_by') }}" value="{{ $quote->contact?->name }}">
                        <input name="po_number" maxlength="80" placeholder="{{ __('quotes.po') }}" aria-label="{{ __('quotes.po') }}">
                        <button class="btn" type="submit">{{ __('quotes.mark_accepted_go') }}</button>
                    </form>
                </details>
            @endif
        </section>
    @endif

    <div class="split split-wide">
        <div class="stack">
            @if ($canEdit)<form method="post" action="{{ route('quotes.update', $quote) }}" id="quote-form">@csrf @method('PUT')@endif
            <section class="card">
                <div class="period">
                    <div>
                        <div class="kicker">{{ __('quotes.out_label') }}</div>
                        @if ($canEdit)
                            <div class="inline-form">
                                <label class="field"><span>{{ __('quotes.out_from') }}</span><input type="datetime-local" name="out_from" required value="{{ old('out_from', $quote->out_from->format('Y-m-d\TH:i')) }}"></label>
                                <label class="field"><span>{{ __('quotes.out_until') }}</span><input type="datetime-local" name="out_until" required value="{{ old('out_until', $quote->out_until->format('Y-m-d\TH:i')) }}"></label>
                            </div>
                        @endif
                        <p class="muted small">{{ __('quotes.out_text', ['days' => $quote->outDays(), 'from' => $quote->out_from->format('d/m H:i'), 'until' => $quote->out_until->format('d/m H:i')]) }}</p>
                    </div>
                    <div>
                        <div class="kicker">{{ __('quotes.use_label') }}</div>
                        @if ($canEdit)
                            <div class="inline-form">
                                <label class="field"><span>{{ __('quotes.use_from') }}</span><input type="date" name="use_from" required value="{{ old('use_from', $quote->use_from->toDateString()) }}"></label>
                                <label class="field"><span>{{ __('quotes.use_until') }}</span><input type="date" name="use_until" required value="{{ old('use_until', $quote->use_until->toDateString()) }}"></label>
                            </div>
                        @endif
                        <p class="muted small">{{ trans_choice('quotes.used_days', $quote->usedDays(), ['n' => $quote->usedDays()]) }} → {{ __('quotes.factor') }} <b>{{ $num($def) }}</b></p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table table-tight quote-lines">
                        <thead><tr>
                            <th>{{ __('quotes.cols.item') }}</th><th class="num">{{ __('quotes.cols.qty') }}</th>
                            <th class="num">{{ __('quotes.factor') }}</th><th class="num">{{ __('quotes.cols.price') }}</th><th class="num">{{ __('quotes.cols.discount') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th>@if ($canEdit)<th><span class="sr-only">{{ __('quotes.remove') }}</span></th>@endif
                        </tr></thead>
                        <tbody>
                            @forelse ($quote->lines as $line)
                                @php($free = $line->item ? $avail->free($line->item) : null)
                                @php($short = $free !== null && (float) $line->qty > $free)
                                <tr @class(['line-text' => $line->kind === 'text'])>
                                    <td>
                                        @if ($canEdit)
                                            <input type="hidden" name="lines[{{ $line->id }}][position]" value="{{ $line->position }}">
                                            <input name="lines[{{ $line->id }}][name]" value="{{ $line->name }}" maxlength="200" required aria-label="{{ __('quotes.cols.item') }}">
                                        @else
                                            <b>{{ $line->name }}</b>
                                        @endif
                                        @if ($line->number)<br><small class="muted">{{ $line->number }}@if ($line->item?->kind === 'package') · {{ $line->item->contents->map(fn ($c) => $c->pivot->qty.' × '.$c->name)->implode(' · ') }}@endif</small>@endif
                                        @if ($line->kind === 'item' && $free !== null)
                                            <span @class(['avail', 'av-ok' => ! $short, 'av-short' => $short])>{{ $short ? __('quotes.short', ['free' => max(0, $free), 'missing' => (int) ceil((float) $line->qty) - max(0, $free)]) : __('quotes.free', ['n' => $free]) }}</span>
                                        @endif
                                    </td>
                                    @if ($line->kind === 'text')
                                        <td colspan="5"></td>
                                    @elseif ($canEdit)
                                        <td class="num"><input type="number" step="0.01" min="0" name="lines[{{ $line->id }}][qty]" value="{{ $line->qtyLabel() }}" class="in-num" aria-label="{{ __('quotes.cols.qty') }}"></td>
                                        <td class="num">@if ($line->usesFactor())<input type="number" step="0.01" min="0" name="lines[{{ $line->id }}][factor]" value="{{ $line->factor !== null ? $num($line->factor) : '' }}" placeholder="{{ $num($def) }}" class="in-num" aria-label="{{ __('quotes.factor') }}"><small class="muted">{{ $line->factor !== null ? __('quotes.manual') : __('quotes.auto') }}</small>@else<input type="hidden" name="lines[{{ $line->id }}][factor]" value="{{ $line->factor !== null ? $num($line->factor) : '' }}">—@endif</td>
                                        <td class="num"><input type="number" step="0.01" name="lines[{{ $line->id }}][price]" value="{{ number_format($line->unit_price_cents / 100, 2, '.', '') }}" class="in-num" aria-label="{{ __('quotes.cols.price') }}"></td>
                                        <td class="num"><input type="number" step="0.1" min="0" max="100" name="lines[{{ $line->id }}][discount]" value="{{ $line->discount_bp ? $num($line->discount_bp / 100) : '' }}" placeholder="0" class="in-num in-sm" aria-label="{{ __('quotes.cols.discount') }}"></td>
                                        <td class="num nowrap"><b>{{ $eur($line->amount_cents) }}</b></td>
                                    @else
                                        <td class="num">{{ $line->qtyLabel() }}</td>
                                        <td class="num">{{ $line->usesFactor() ? $num($line->effectiveFactor($def)) : '—' }}</td>
                                        <td class="num nowrap">{{ $eur($line->unit_price_cents) }}</td>
                                        <td class="num">{{ $line->discount_bp ? $num($line->discount_bp / 100).'%' : '' }}</td>
                                        <td class="num nowrap"><b>{{ $eur($line->amount_cents) }}</b></td>
                                    @endif
                                    @if ($canEdit)
                                        @if ($line->kind === 'text')<input type="hidden" name="lines[{{ $line->id }}][qty]" value="0">@endif
                                        <td><button type="submit" form="rm-{{ $line->id }}" class="iconbtn" title="{{ __('quotes.remove') }}" aria-label="{{ __('quotes.remove') }}: {{ $line->name }}">✕</button></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="muted">{{ __('quotes.no_lines') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card" id="transport">
                <h2>{{ __('quotes.transport.title') }}</h2>
                @if ($canEdit)
                    <div class="lg-choice" role="radiogroup" aria-label="{{ __('quotes.transport.title') }}">
                        @foreach (\App\Models\Sales\Quote::logisticsOptions() as $opt)
                            <label class="lg-opt"><input type="radio" name="logistics" value="{{ $opt }}" @checked(old('logistics', $quote->logistics) === $opt)><span><b>{{ __('quotes.transport.options.'.$opt) }}</b><small class="muted">{{ __('quotes.transport.hints.'.$opt) }}</small></span></label>
                        @endforeach
                    </div>
                    <div class="form-grid mt lg-delivery">
                        <label class="field"><span>{{ __('quotes.transport.price') }}</span><input type="number" step="0.01" min="0" name="transport" value="{{ old('transport', $quote->transport_cents ? number_format($quote->transport_cents / 100, 2, '.', '') : '') }}" placeholder="{{ __('quotes.transport.price_ph') }}"></label>
                        <label class="field"><span>{{ __('quotes.transport.note') }}</span><input name="transport_note" maxlength="300" value="{{ old('transport_note', $quote->transport_note) }}" placeholder="{{ __('quotes.transport.note_ph') }}"></label>
                    </div>
                    <p class="muted small">{{ __('quotes.transport.later_hint') }}</p>
                @else
                    <p><b>{{ __('quotes.transport.options.'.$quote->logistics) }}</b>@if ($quote->logistics === 'delivery') · {{ $quote->transportToPrice() ? __('quotes.transport.price_follows') : $eur($quote->transport_cents) }}@endif @if ($quote->transport_note)<br><span class="muted">{{ $quote->transport_note }}</span>@endif</p>
                    @if ($quote->order && $quote->order->status !== 'cancelled')
                        <p class="muted small">{!! __('quotes.transport.on_order', ['order' => '<a href="'.e(route('orders.show', $quote->order).'#logistics').'">'.e($quote->order->number).'</a>']) !!}</p>
                    @endif
                @endif
            </section>

            <section class="card">
                <div class="totals">
                    <div class="tot"><span>{{ __('quotes.subtotal', ['n' => $quote->lines->where('kind', '!=', 'text')->count()]) }}</span><span>{{ $eur($quote->subtotal_cents) }}</span></div>
                    <div class="tot">
                        <span>{{ __('quotes.project_discount') }} @if ($canEdit)<input type="number" step="0.1" min="0" max="100" name="project_discount" value="{{ $quote->project_discount_bp ? $num($quote->project_discount_bp / 100) : '' }}" placeholder="0" class="in-num in-sm" aria-label="{{ __('quotes.project_discount') }}"> %@elseif ($quote->project_discount_bp) {{ $num($quote->project_discount_bp / 100) }}%@endif</span>
                        <span>− {{ $eur($quote->discount_cents) }}</span>
                    </div>
                    @if ($quote->logistics === 'delivery')
                        <div class="tot"><span>{{ __('quotes.transport.transport_line') }}</span><span>{{ $quote->transportToPrice() ? __('quotes.transport.price_follows') : $eur($quote->transport_cents) }}</span></div>
                    @endif
                    <div class="tot tot-strong"><span>{{ __('quotes.total_excl') }}</span><span>{{ $eur($quote->net_cents) }}</span></div>
                    <div class="tot muted"><span>{{ __('quotes.vat', ['pct' => $quote->vatPercent()]) }}</span><span>{{ $eur($quote->vat_cents) }}</span></div>
                    <div class="tot tot-grand"><span>{{ __('quotes.total') }}</span><span>{{ $eur($quote->gross_cents) }}</span></div>
                </div>
            </section>

            <section class="card">
                <h2>{{ __('quotes.texts') }}</h2>
                @if ($canEdit)
                    <div class="form-grid">
                        <label class="field span-2"><span>{{ __('deals.fields.title') }}</span><input name="title" required maxlength="200" value="{{ old('title', $quote->title) }}"></label>
                        <label class="field"><span>{{ __('deals.fields.contact_id') }}</span><select name="contact_id"><option value="">—</option>@foreach ($contacts as $c)<option value="{{ $c->id }}" @selected($quote->contact_id === $c->id)>{{ $c->name }}@if ($c->email) · {{ $c->email }}@endif</option>@endforeach @if ($quote->contact && ! $contacts->contains('id', $quote->contact_id))<option value="{{ $quote->contact_id }}" selected>{{ $quote->contact->name }}</option>@endif</select></label>
                        <label class="field"><span>{{ __('quotes.language') }}</span><select name="language">@foreach (config('cluk.locales') as $code => $label)<option value="{{ $code }}" @selected($quote->language === $code)>{{ $label }}</option>@endforeach</select></label>
                        <label class="field span-2"><span>{{ __('quotes.location') }}</span><input name="location" maxlength="200" value="{{ old('location', $quote->location) }}" placeholder="{{ __('quotes.location_hint') }}"></label>
                        <label class="field span-2"><span>{{ __('quotes.intro_label') }}</span><textarea name="intro" rows="2" maxlength="2000" placeholder="{{ __('quotes.intro_hint') }}">{{ old('intro', $quote->intro) }}</textarea></label>
                        <label class="field span-2"><span>{{ __('quotes.notes_label') }}</span><textarea name="notes" rows="2" maxlength="2000" placeholder="{{ __('quotes.notes_hint') }}">{{ old('notes', $quote->notes) }}</textarea></label>
                    </div>
                    <div class="form-actions"><button class="primary" type="submit">{{ __('quotes.save') }}</button><a class="btn" href="{{ route('quotes.print', $quote) }}" target="_blank" rel="noopener">{{ __('quotes.print') }}</a></div>
                @else
                    <dl class="facts">
                        <dt>{{ __('quotes.location') }}</dt><dd>{{ $quote->location ?: '—' }}</dd>
                        @if ($quote->intro)<dt>{{ __('quotes.intro_label') }}</dt><dd>{!! nl2br(e($quote->intro)) !!}</dd>@endif
                        @if ($quote->notes)<dt>{{ __('quotes.notes_label') }}</dt><dd>{!! nl2br(e($quote->notes)) !!}</dd>@endif
                    </dl>
                @endif
            </section>
            @if ($canEdit)</form>@endif

            @if ($canEdit)
                @foreach ($quote->lines as $line)
                    <form method="post" action="{{ route('quotes.lines.remove', [$quote, $line]) }}" id="rm-{{ $line->id }}" hidden>@csrf @method('DELETE')</form>
                @endforeach
            @endif
        </div>

        <aside class="stack">
            @if ($canEdit)
                <section class="card">
                    <h2>{{ __('quotes.add_item') }}</h2>
                    <p class="muted small">{{ __('quotes.add_item_hint', ['period' => $quote->out_from->format('d/m').' – '.$quote->out_until->format('d/m')]) }}</p>
                    <form method="post" action="{{ route('quotes.lines.add', $quote) }}" class="stack-sm" data-search="{{ route('quotes.search', $quote) }}">
                        @csrf
                        <input type="hidden" name="kind" value="item">
                        <input name="number" required autocomplete="off" placeholder="{{ __('quotes.search_hint') }}" aria-label="{{ __('quotes.search_hint') }}" class="js-item-search">
                        <ul class="suggest" hidden></ul>
                        <div class="inline-form"><input type="number" name="qty" value="1" min="0.01" step="0.01" class="in-num" aria-label="{{ __('quotes.cols.qty') }}"><button class="primary" type="submit">{{ __('quotes.add') }}</button></div>
                    </form>
                    <details class="mt">
                        <summary>{{ __('quotes.add_charge') }}</summary>
                        <form method="post" action="{{ route('quotes.lines.add', $quote) }}" class="stack-sm mt">
                            @csrf
                            <input type="hidden" name="kind" value="charge">
                            <input name="name" required maxlength="200" placeholder="{{ __('quotes.charge_hint') }}" aria-label="{{ __('quotes.charge_name') }}">
                            <div class="inline-form">
                                <input type="number" name="qty" value="1" min="0.01" step="0.01" class="in-num" aria-label="{{ __('quotes.cols.qty') }}">
                                <input type="number" name="price" step="0.01" required placeholder="€" class="in-num" aria-label="{{ __('quotes.cols.price') }}">
                                <button class="btn" type="submit">{{ __('quotes.add') }}</button>
                            </div>
                        </form>
                    </details>
                    <details class="mt">
                        <summary>{{ __('quotes.add_text') }}</summary>
                        <form method="post" action="{{ route('quotes.lines.add', $quote) }}" class="stack-sm mt">
                            @csrf
                            <input type="hidden" name="kind" value="text">
                            <input name="name" required maxlength="200" placeholder="{{ __('quotes.text_hint') }}" aria-label="{{ __('quotes.add_text') }}">
                            <button class="btn" type="submit">{{ __('quotes.add') }}</button>
                        </form>
                    </details>
                </section>

                <section class="card">
                    <h2>{{ __('quotes.send') }}</h2>
                    <p class="muted small">{{ __('quotes.send_hint') }}</p>
                    <form method="post" action="{{ route('quotes.send', $quote) }}" class="stack-sm">
                        @csrf
                        <label class="field"><span>{{ __('quotes.send_to') }}</span><input type="email" name="to" required maxlength="190" value="{{ old('to', $quote->contact?->email ?? $quote->company?->email) }}" list="quote-emails"></label>
                        <datalist id="quote-emails">@foreach ($contacts->whereNotNull('email') as $c)<option value="{{ $c->email }}">{{ $c->name }}</option>@endforeach</datalist>
                        <label class="field"><span>{{ __('quotes.message') }}</span><textarea name="message" rows="4" maxlength="3000" placeholder="{{ __('quotes.message_hint') }}">{{ old('message') }}</textarea></label>
                        <button class="primary" type="submit" @disabled($quote->lines->isEmpty())>{{ __('quotes.send_go', ['name' => $quote->contact?->first_name ?? __('quotes.customer')]) }}</button>
                        <small class="muted">{{ __('quotes.save_first') }}</small>
                    </form>
                </section>
            @endif

            <section class="card">
                <h2>{{ __('quotes.logistics') }}</h2>
                <dl class="facts">
                    <dt>{{ __('quotes.weight') }}</dt><dd>{{ number_format($logistics['kg'], 0, ',', '.') }} kg</dd>
                    <dt>{{ __('quotes.volume') }}</dt><dd>{{ number_format($logistics['m3'], 2, ',', '.') }} m³</dd>
                    <dt>{{ __('quotes.insured') }}</dt><dd>{{ $eur($logistics['insured_cents']) }}</dd>
                </dl>
                <p class="muted small">{{ __('quotes.logistics_hint') }}</p>
            </section>

            @if ($versions->count() > 1)
                <section class="card">
                    <h2>{{ __('quotes.versions') }}</h2>
                    <ul class="plain-list">
                        @foreach ($versions as $v)
                            <li>@if ($v->id === $quote->id)<b>v{{ $v->version }}</b>@else<a href="{{ route('quotes.show', $v->id) }}">v{{ $v->version }}</a>@endif · {{ __('quotes.statuses.'.$v->status) }} · {{ $eur($v->gross_cents) }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    @if ($canEdit)
        <script>
        (() => {
            const form = document.querySelector('[data-search]');
            const input = form?.querySelector('.js-item-search');
            const list = form?.querySelector('.suggest');
            if (!input) return;
            let timer;
            input.addEventListener('input', () => {
                clearTimeout(timer);
                const q = input.value.trim();
                if (q.length < 2) { list.hidden = true; return; }
                timer = setTimeout(async () => {
                    const res = await fetch(form.dataset.search + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    const items = await res.json();
                    list.innerHTML = '';
                    items.forEach(i => {
                        const li = document.createElement('li');
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'suggest-item';
                        const free = i.free === null ? '' : ' · ' + @json(__('quotes.free', ['n' => '__N__'])).replace('__N__', i.free);
                        b.innerHTML = '<b></b><small></small>';
                        b.querySelector('b').textContent = i.name;
                        b.querySelector('small').textContent = i.number + ' · ' + (i.chips && i.chips.length ? i.chips.join(' · ') + ' · ' : '') + i.price + ' ' + i.unit + free;
                        b.addEventListener('click', () => { input.value = i.number; list.hidden = true; form.querySelector('[name=qty]').focus(); });
                        li.appendChild(b);
                        list.appendChild(li);
                    });
                    list.hidden = items.length === 0;
                }, 200);
            });
        })();
        </script>
    @endif
</x-layouts.app>
