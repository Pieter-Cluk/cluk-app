@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($dates = $order->out_from->format('d/m').' – '.$order->out_until->format('d/m'))
@php($stockLines = $order->lines->filter(fn ($l) => $l->needsStock()))
@php($drafts = $order->subhires->where('status', 'draft')->filter(fn ($r) => $r->lines->isNotEmpty()))
@php($step = match ($state) { 'subhire', 'waiting' => 1, 'allocate' => 2, 'ready' => 3, default => 4 })
<x-layouts.app :title="$order->number">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('orders.index') }}">{{ mb_strtoupper(__('orders.title')) }}</a> · {{ $order->number }}</div>
            <h1>{{ $order->title }}</h1>
            <p class="muted">{{ $order->customerName() }} · {{ __('orders.out', ['from' => $order->out_from->format('d/m H:i'), 'until' => $order->out_until->format('d/m H:i')]) }} · {{ __('orders.used', ['from' => $order->use_from->format('d/m'), 'until' => $order->use_until->format('d/m')]) }}@if ($order->quote) · <a href="{{ route('quotes.show', $order->quote) }}">{{ $order->quote->code() }}</a>@endif</p>
        </div>
        @include('rental.orders._state', ['state' => $state, 'lg' => true])
    </div>
    @include('partials.flash')

    <div class="handover">
        <div class="card">
            <div class="kicker">{{ mb_strtoupper(__('orders.confirmed')) }}</div>
            <p><b>{{ $order->confirmed_name ?? '—' }}</b> · {{ $order->confirmed_online ? __('orders.online') : __('orders.by_staff') }} · {{ $order->confirmed_at?->format('d/m H:i') }}@if ($order->po_number) · {{ __('quotes.po') }}: <b>{{ $order->po_number }}</b>@endif</p>
        </div>
        <div class="card">
            <div class="kicker">{{ mb_strtoupper(__('orders.handed_by')) }}</div>
            <p><b>{{ $order->handedBy?->name ?? '—' }}</b>@if ($order->handedBy?->title) · {{ $order->handedBy->title }}@endif</p>
        </div>
        <div class="card">
            <div class="kicker">{{ mb_strtoupper(__('orders.assigned_to')) }}</div>
            <p><b>{{ $order->assignee?->name ?? __('orders.nobody') }}</b></p>
            @if ($order->isInAllocation() && $canReassign && $assignees->isNotEmpty())
                <form method="post" action="{{ route('orders.assign', $order) }}" class="inline-form">
                    @csrf
                    <select name="user_id" aria-label="{{ __('orders.assigned_to') }}">@foreach ($assignees as $u)<option value="{{ $u->id }}" @selected($u->id === $order->assignee_id)>{{ $u->name }}@if ($u->title) · {{ $u->title }}@endif</option>@endforeach</select>
                    <button class="btn" type="submit">{{ __('orders.assign') }}</button>
                </form>
                <small class="muted">{{ __('orders.assign_rule') }}</small>
            @endif
        </div>
    </div>

    <ol class="steps3">
        @foreach ([1 => 'subhire', 2 => 'allocate', 3 => 'approve'] as $n => $k)
            <li @class(['st3', 'st3-on' => $step === $n, 'st3-done' => $step > $n])><span>{{ $step > $n ? '✓' : $n }}</span><b>{{ __('orders.steps.'.$k) }}</b><small>{{ __('orders.steps.'.$k.'_hint') }}</small></li>
        @endforeach
    </ol>

    @if ($canWork)
        <div class="actionbar">
            @if ($canSubhire)
                <form method="post" action="{{ route('orders.send', $order) }}">@csrf<button class="btn" type="submit" @disabled($drafts->isEmpty())>{{ __('orders.send_all', ['n' => $drafts->count()]) }}</button></form>
            @endif
            <form method="post" action="{{ route('orders.auto', $order) }}">@csrf<button class="btn" type="submit">{{ __('orders.auto_all') }}</button></form>
        </div>
    @endif

    <section class="stack">
        @foreach ($stockLines as $line)
            @php($free = (int) $avail->free($line->item))
            @php($need = $line->ownNeeded())
            @php($alloc = $line->ownAllocated())
            @php($short = max(0, $need - $free))
            @php($covered = $line->isCovered())
            <article @class(['card', 'ocl', 'ocl-ok' => $covered, 'ocl-rent' => $short > 0])>
                <header class="ocl-head">
                    <div>
                        <h2>{{ $line->qtyLabel() }} × {{ $line->name }}</h2>
                        <small class="muted">{{ $line->number }} · {{ $line->item->kind === 'package' ? __('orders.free_packages', ['n' => max(0, $free), 'dates' => $dates]) : __('orders.free_of', ['n' => max(0, $free), 'total' => $avail->owned($line->item), 'dates' => $dates]) }}@if ($short) · <b class="txt-warn">{{ __('orders.short', ['n' => $short]) }}</b>@endif</small>
                    </div>
                    <span @class(['pill', 'pill-ok' => $covered, 'pill-warn' => ! $covered])>{{ $covered ? __('orders.line_ready') : __('orders.line_open') }}</span>
                </header>
                <div class="ocl-bar" aria-hidden="true">
                    @php($pc = fn ($n) => $line->pieces() ? round(max(0, $n) / $line->pieces() * 100, 2) : 0)
                    <i class="b-own" style="width: {{ $pc($alloc) }}%"></i><i class="b-rent" style="width: {{ $pc($line->rentedQty()) }}%"></i><i class="b-wait" style="width: {{ $pc($line->rentQty() - $line->rentedQty()) }}%"></i>
                </div>
                <p class="muted small">{{ __('orders.legend', ['own' => $alloc, 'need' => $need, 'rented' => $line->rentedQty(), 'pending' => $line->rentQty() - $line->rentedQty()]) }}</p>

                <div class="ocl-grid">
                    <div>
                        <h3>{{ __('orders.own_stock') }}</h3>
                        @if ($line->bySerial())
                            <div class="chips">
                                @foreach ($line->assets as $oa)
                                    <span class="chip asset-chip">{{ $oa->asset->code }}@if ($canWork)<form method="post" action="{{ route('orders.release', [$order, $oa]) }}">@csrf @method('DELETE')<button type="submit" class="chip-x" aria-label="{{ __('orders.release') }} {{ $oa->asset->code }}">✕</button></form>@endif</span>
                                @endforeach
                            </div>
                            @if ($canWork && $alloc < $need)
                                <form method="post" action="{{ route('orders.allocate', [$order, $line]) }}" class="inline-form mt">
                                    @csrf
                                    <input name="code" placeholder="{{ __('orders.code_hint') }}" aria-label="{{ __('orders.code_hint') }}" autocomplete="off">
                                    <button class="btn" type="submit">{{ __('orders.add_piece') }}</button>
                                </form>
                            @endif
                            <small class="muted">{{ __('orders.serial_hint') }}</small>
                        @else
                            @if ($canWork)
                                <form method="post" action="{{ route('orders.allocate', [$order, $line]) }}" class="inline-form">
                                    @csrf
                                    <input type="number" name="own_qty" min="0" max="{{ $need }}" value="{{ $alloc }}" class="in-num" aria-label="{{ __('orders.own_stock') }}">
                                    <span class="muted">/ {{ $need }}</span>
                                    <button class="btn" type="submit">{{ __('orders.save') }}</button>
                                </form>
                            @else
                                <p>{{ $alloc }} / {{ $need }}</p>
                            @endif
                            <small class="muted">{{ $line->item->kind === 'package' ? __('orders.package_hint', ['contents' => $line->item->contents->map(fn ($c) => $c->pivot->qty.' × '.$c->name)->implode(' · ')]) : __('orders.counted_hint') }}</small>
                        @endif
                        @if ($canWork)
                            <div class="inline-form mt">
                                @if ($alloc < $need)<form method="post" action="{{ route('orders.allocate', [$order, $line]) }}">@csrf<button class="btn" type="submit">{{ __('orders.auto') }}</button></form>@endif
                                @if ($alloc)<form method="post" action="{{ route('orders.clear', [$order, $line]) }}">@csrf<button class="btn" type="submit">{{ __('orders.clear') }}</button></form>@endif
                            </div>
                        @endif
                    </div>

                    <div>
                        <h3>{{ __('orders.rent_in') }}</h3>
                        @forelse ($line->subhireLines as $sl)
                            @php($r = $sl->request)
                            <div class="p-line">
                                <span><b>{{ $sl->qty }} ×</b> {{ $r->supplier->name }}<br><small class="muted"><a href="{{ route('subhire.show', $r) }}">{{ $r->code() }}</a>@if ($sl->price_cents !== null) · {{ __('orders.per_day', ['amount' => $eur($sl->price_cents)]) }}@elseif ($sl->last_price_cents) · {{ __('orders.last_price', ['amount' => $eur($sl->last_price_cents)]) }}@endif</small></span>
                                @include('rental.subhire._status', ['r' => $r])
                            </div>
                        @empty
                            <p class="muted small">{{ $short ? __('orders.rent_needed', ['n' => $short]) : __('orders.rent_none') }}</p>
                        @endforelse
                        @if ($canWork && $canSubhire)
                            <form method="post" action="{{ route('orders.subhire', [$order, $line]) }}" class="inline-form mt">
                                @csrf
                                <select name="supplier_id" required aria-label="{{ __('orders.partner') }}">
                                    <option value="">{{ __('orders.choose_partner') }}</option>
                                    @foreach ($suppliers->sortByDesc(fn ($s) => in_array('subhire', $s->supplies ?? [], true)) as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                </select>
                                <input type="number" name="qty" min="1" max="{{ $line->pieces() }}" value="{{ max(1, $short) }}" class="in-num in-sm" aria-label="{{ __('orders.pieces') }}">
                                <button @class(['btn', 'primary' => $short > 0]) type="submit">{{ __('orders.rent_go') }}</button>
                            </form>
                            @if ($suppliers->isEmpty())<small class="muted">{{ __('orders.no_suppliers') }}</small>@endif
                        @endif
                    </div>
                </div>
            </article>
        @endforeach

        @php($others = $order->lines->reject(fn ($l) => $l->needsStock() || $l->kind === 'text'))
        @if ($others->isNotEmpty())
            <section class="card">
                <h2>{{ __('orders.no_stock_lines') }}</h2>
                @foreach ($others as $l)<div class="p-line"><span>{{ $l->qtyLabel() }} × {{ $l->name }}</span><span class="muted">{{ $eur($l->amount_cents) }}</span></div>@endforeach
            </section>
        @endif
    </section>

    <section @class(['card', 'approve-bar', 'is-ready' => $state === 'ready'])>
        @if ($order->status === 'approved')
            <div>
                <h2>{{ __('orders.approved_title') }}</h2>
                <p class="muted">{{ __('orders.approved_by', ['name' => $order->approvedBy?->name ?? '—', 'date' => $order->approved_at?->format('d/m/Y H:i'), 'email' => $order->customer_email ?: '—']) }}</p>
            </div>
            <a class="btn" href="{{ route('orders.print', $order) }}" target="_blank" rel="noopener">{{ __('orders.print') }}</a>
        @elseif ($order->status === 'cancelled')
            <div><h2>{{ __('orders.states.cancelled') }}</h2><p class="muted">{{ $order->cancel_reason }}</p></div>
        @else
            <div>
                <h2>{{ __('orders.summary.'.$state) }}</h2>
                <p class="muted">{{ __('orders.summary_hint') }}</p>
            </div>
            <div class="inline-form">
                <a class="btn" href="{{ route('orders.print', $order) }}" target="_blank" rel="noopener">{{ __('orders.preview') }}</a>
                @if ($canWork && $canApprove)
                    <form method="post" action="{{ route('orders.approve', $order) }}">@csrf<button class="primary" type="submit" @disabled($state !== 'ready')>{{ __('orders.approve') }}</button></form>
                @elseif ($canWork)
                    <small class="muted">{{ __('orders.approve_right') }}</small>
                @endif
            </div>
        @endif
    </section>

    @if ($order->status === 'approved')
        <section class="card">
            <h2>{{ __('docs.title') }}</h2>
            <div class="doc-links">
                @foreach (\App\Http\Controllers\Rental\OrderDocumentController::TYPES as $t => $prefix)
                    <a class="doc-link" href="{{ route('orders.doc', [$order, $t]) }}" target="_blank" rel="noopener">
                        <b>{{ __('docs.titles.'.$t) }}</b>
                        <small class="muted">{{ \App\Http\Controllers\Rental\OrderDocumentController::number($order, $t) }} · {{ __('docs.flags.'.$t) }}</small>
                        <small>{{ __('docs.meaning.'.$t) }}</small>
                    </a>
                @endforeach
            </div>
            @if (auth()->user()->hasRight('orders.approve') && $order->customer_email)
                <form method="post" action="{{ route('orders.proforma', $order) }}" class="inline-form mt">
                    @csrf
                    <button class="btn" type="submit">{{ __('docs.send_pf', ['email' => $order->customer_email]) }}</button>
                </form>
            @endif
        </section>
    @endif

    @if ($order->isInAllocation() && auth()->user()->canUseModule('dryhire', 3))
        <details class="card">
            <summary>{{ __('orders.cancel') }}</summary>
            <form method="post" action="{{ route('orders.cancel', $order) }}" class="stack-sm mt">
                @csrf
                <input name="reason" required maxlength="500" placeholder="{{ __('orders.cancel_reason') }}" aria-label="{{ __('orders.cancel_reason') }}">
                <button class="btn btn-danger" type="submit">{{ __('orders.cancel_go') }}</button>
            </form>
        </details>
    @endif
</x-layouts.app>
