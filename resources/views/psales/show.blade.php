@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($pct = fn ($bp) => number_format($bp / 100, 1, ',', '.').'%')
@php($edit = $q->isEditable() && $canWork)
@php($low = $q->lowLines())
@php($day = fn ($d) => $d->locale(app()->getLocale())->translatedFormat('j M'))
<x-layouts.app :title="$q->code()">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('psales.index') }}">{{ mb_strtoupper(__('nav.items.productsales')) }}</a> · {{ $q->code() }}</div>
            <h1>{{ $q->title }} · {{ $q->customerName() }}</h1>
            <p class="muted">{{ __('psales.head', ['contact' => $q->contact?->name ?? '—', 'owner' => $q->owner?->name ?? '—', 'valid' => $q->valid_until?->format('d/m/Y') ?? '—']) }}</p>
        </div>
        @include('psales._status')
    </div>
    @include('partials.flash')
    @include('psales._tabs')

    @php($steps = ['draft', 'approval', 'sent', 'accepted', 'ready', 'confirmed', 'delivered'])
    @php($now = $q->status === 'draft' ? ($low->isNotEmpty() && $q->needsApproval() ? 'approval' : 'draft') : ($q->status === 'accepted' && $q->datesIn() ? 'ready' : $q->status))
    <ol class="ps-flow mt">@foreach ($steps as $i => $s)<li @class(['on' => $s === $now, 'done' => array_search($now, $steps, true) > $i])>{{ __('psales.flow.'.$s) }}</li>@endforeach</ol>

    <form method="post" action="{{ route('psales.update', $q) }}" class="card card-flush mt" id="lines">@csrf @method('PUT')
        <table class="table ps-lines">
            <thead><tr><th>{{ __('psales.cols.item') }}</th><th class="num">{{ __('psales.cols.qty') }}</th><th class="num">{{ __('psales.cols.list') }}</th><th class="num">{{ __('psales.cols.disc') }}</th><th class="num">{{ __('psales.cols.unit') }}</th>
                @if ($seeCost)<th class="num">{{ __('psales.cols.cost') }}</th><th class="num">{{ __('psales.cols.margin') }}</th>@endif<th>{{ __('psales.cols.delivery') }}</th><th class="num">{{ __('psales.cols.total') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($q->lines as $l)
                    @php($m = $l->marginBp())
                    <tr @class(['ps-low' => $l->kind === 'item' && $m < \App\Models\ProductSales\SalesQuote::MIN_MARGIN_BP && $seeCost])>
                        <td>@if ($l->kind === 'item')<b>{{ $l->name }}</b><br><small class="muted">{{ $l->item?->number }} · {{ $l->description }}</small>@elseif ($edit)<input name="lines[{{ $l->id }}][name]" value="{{ $l->name }}" maxlength="200" aria-label="{{ __('psales.cols.item') }}">@else<i>{{ $l->name }}</i>@endif</td>
                        <td class="num">@if ($edit && $l->kind !== 'text')<input type="number" min="1" name="lines[{{ $l->id }}][qty]" value="{{ $l->qty }}" class="w-xs" aria-label="{{ __('psales.cols.qty') }}">@else<input type="hidden" name="lines[{{ $l->id }}][qty]" value="{{ $l->qty }}">{{ $l->kind === 'text' ? '' : $l->qty }}@endif</td>
                        <td class="num nowrap">@if ($l->kind === 'charge' && $edit)<input type="number" step="0.01" min="0" name="lines[{{ $l->id }}][price]" value="{{ number_format($l->list_cents / 100, 2, '.', '') }}" class="w-sm" aria-label="{{ __('psales.cols.list') }}">@elseif ($l->kind !== 'text'){{ $eur($l->list_cents) }}@endif</td>
                        <td class="num">@if ($edit && $l->kind === 'item')<input type="number" step="0.5" min="0" max="100" name="lines[{{ $l->id }}][discount]" value="{{ rtrim(rtrim(number_format($l->discount_bp / 100, 2, '.', ''), '0'), '.') }}" class="w-xs" aria-label="{{ __('psales.cols.disc') }}">@elseif ($l->discount_bp){{ $pct($l->discount_bp) }}@endif</td>
                        <td class="num nowrap">{{ $l->kind === 'text' ? '' : $eur($l->unit_cents) }}</td>
                        @if ($seeCost)<td class="num nowrap">{{ $l->kind === 'item' ? $eur($l->cost_cents) : '' }}</td><td class="num"><b>{{ $l->kind === 'item' ? $pct($m) : '' }}</b></td>@endif
                        <td>@if ($l->kind === 'item' && $l->item)
                            @if ($l->delivery_date)<b>{{ $day($l->delivery_date) }}</b><br><small class="muted">{{ __('psales.del.confirmed') }}</small>
                            @elseif (in_array($q->status, ['accepted', 'confirmed', 'delivered'], true))<small>{{ $l->from_stock ? __('psales.del.stock_n', ['n' => $l->from_stock]) : '' }}@if ($l->toBuy()) {{ __('psales.del.to_buy', ['n' => $l->toBuy()]) }}@endif</small>
                            @else @php($free = $l->item->freeStock())<small>{{ $free >= $l->qty ? __('psales.del.from_stock') : '± '.$day(\App\Services\Purchasing\Purchasing::workdays(today(), $l->item->leadDays() + 1)) }}</small><br><small class="muted">{{ $free >= $l->qty ? __('psales.del.ready', ['date' => $day(\App\Services\Purchasing\Purchasing::workdays(today(), 1))]) : ($free ? __('psales.del.partly', ['n' => $free, 'buy' => $l->qty - $free]) : __('psales.del.order')).' · '.trans_choice('psales.del.days', $l->item->leadDays(), ['n' => $l->item->leadDays()]) }}</small>
                            @endif
                        @endif</td>
                        <td class="num nowrap"><b>{{ $l->kind === 'text' ? '' : $eur($l->amount_cents) }}</b></td>
                        <td>@if ($edit)<button class="btn btn-sm btn-ghost" type="submit" form="rm-{{ $l->id }}" aria-label="{{ __('psales.remove') }}">×</button>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">{{ __('psales.no_lines') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pad card-foot">
            <div class="ps-totals">
                <span>{{ __('psales.total_excl') }} <b>{{ $eur($q->net_cents) }}</b></span>
                @if ($seeCost)<span>{{ __('psales.cost_total') }} <b>{{ $eur($q->cost_cents) }}</b></span><span>{{ __('psales.margin') }} <b @class(['txt-danger' => $q->marginBp() < \App\Models\ProductSales\SalesQuote::MIN_MARGIN_BP])>{{ $pct($q->marginBp()) }}</b></span>@endif
            </div>
            @if ($edit)<button class="primary" type="submit">{{ __('psales.save') }}</button>@endif
        </div>
        @if ($edit)
            <div class="pad stack-sm">
                <div class="inline-form">
                    <label class="field"><span>{{ __('quotes.language') }}</span><select name="language">@foreach (config('cluk.locales') as $c => $n)<option value="{{ $c }}" @selected($q->language === $c)>{{ $n }}</option>@endforeach</select></label>
                    <label class="field"><span>{{ __('psales.contact') }}</span><select name="contact_id"><option value="">—</option>@foreach ($q->company?->contacts ?? [] as $c)<option value="{{ $c->id }}" @selected($q->contact_id === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
                    <label class="field"><span>{{ __('psales.valid_until') }}</span><input type="date" name="valid_until" value="{{ $q->valid_until?->toDateString() }}"></label>
                    <label class="field"><span>{{ __('psales.delivery') }}</span><select name="delivery_mode">@foreach (['together', 'asap'] as $m)<option value="{{ $m }}" @selected($q->delivery_mode === $m)>{{ __('psales.delivery_modes.'.$m) }}</option>@endforeach</select></label>
                </div>
                <input type="hidden" name="title" value="{{ $q->title }}">
                <label class="field"><span>{{ __('psales.intro_label') }}</span><textarea name="intro" rows="2" maxlength="2000">{{ $q->intro }}</textarea></label>
                <label class="field"><span>{{ __('psales.notes_label') }}</span><textarea name="notes" rows="2" maxlength="2000">{{ $q->notes }}</textarea></label>
            </div>
        @endif
    </form>
    @if ($edit)
        @foreach ($q->lines as $l)<form id="rm-{{ $l->id }}" method="post" action="{{ route('psales.lines.remove', [$q, $l]) }}">@csrf @method('DELETE')</form>@endforeach
        <section class="card mt">
            <form method="post" action="{{ route('psales.lines.add', $q) }}" class="inline-form">@csrf<input type="hidden" name="kind" value="item">
                <select name="item_id" required aria-label="{{ __('psales.add_item') }}" class="ps-pick"><option value="">{{ __('psales.add_item') }}</option>@foreach ($items as $it)<option value="{{ $it->id }}">{{ $it->number }} · {{ $it->brand }} {{ $it->type }}@if ($it->manufacturer_code) ({{ $it->manufacturer_code }})@endif · {{ $eur($it->list_price_cents) }}@if ($it->stock_qty) · {{ __('psales.items.in_stock', ['n' => $it->stock_qty]) }}@endif</option>@endforeach</select>
                <input type="number" name="qty" min="1" value="1" class="w-xs" aria-label="{{ __('psales.cols.qty') }}"><button class="btn" type="submit">{{ __('psales.add') }}</button>
            </form>
            <form method="post" action="{{ route('psales.lines.add', $q) }}" class="inline-form mt">@csrf
                <select name="kind" aria-label="{{ __('psales.add_other') }}"><option value="charge">{{ __('psales.kinds.charge') }}</option><option value="text">{{ __('psales.kinds.text') }}</option></select>
                <input name="name" maxlength="200" required placeholder="{{ __('psales.other_hint') }}" aria-label="{{ __('psales.add_other') }}"><button class="btn" type="submit">{{ __('psales.add') }}</button>
            </form>
        </section>
    @endif

    <div class="split-2 mt">
        <div class="stack">
            @if ($q->status === 'draft' && $low->isNotEmpty())
                <section @class(['card', 'ps-ap', 'ps-ap-ok' => ! $q->needsApproval()])>
                    <h2>{{ $q->needsApproval() ? __('psales.ap.title') : __('psales.ap.ok', ['name' => $q->approvedBy?->name]) }}</h2>
                    <p>{{ __('psales.ap.text', ['lines' => $low->pluck('name')->implode(', ')]) }}</p>
                    @if ($q->approval_note && ! $q->needsApproval())<p class="muted">“{{ $q->approval_note }}”</p>@endif
                    @if ($q->needsApproval() && $canApprove)
                        <form method="post" action="{{ route('psales.approve', $q) }}" class="stack-sm">@csrf<input name="note" maxlength="500" placeholder="{{ __('psales.ap.note') }}" aria-label="{{ __('psales.ap.note') }}"><div><button class="primary" type="submit">{{ __('psales.ap.approve') }}</button></div></form>
                    @elseif ($q->needsApproval())
                        <p class="muted small">{{ __('psales.ap.wait') }}</p>
                    @endif
                    <p class="muted small">{{ __('psales.ap.rule') }}</p>
                </section>
            @endif

            @if ($q->requests->isNotEmpty())
                <section class="card">
                    <h2>{{ __('psales.purchasing') }}</h2>
                    @foreach ($q->requests as $r)
                        <div class="p-line"><a href="{{ route('purchasing.pq', $r) }}"><b>{{ $r->number }}</b></a><span class="muted">{{ __('purchasing.pq_status.'.$r->status) }}</span></div>
                        @foreach ($r->orders as $o)<div class="p-line"><a href="{{ route('purchasing.po', $o) }}">{{ $o->number }}</a><span class="muted">{{ __('purchasing.po_status.'.$o->status) }}</span></div>@endforeach
                    @endforeach
                    <p class="muted small">{{ __('psales.purchasing_hint') }}</p>
                </section>
            @endif
        </div>
        <div class="stack">
            @if ($canWork)
                <section class="card">
                    <h2>{{ __('psales.next') }}</h2>
                    @if (in_array($q->status, ['draft', 'sent'], true))
                        <form method="post" action="{{ route('psales.send', $q) }}" class="stack-sm">@csrf
                            <label class="field"><span>{{ __('psales.send_to') }}</span><input type="email" name="to" required value="{{ $q->sent_to ?? $q->contact?->email }}"></label>
                            <label class="field"><span>{{ __('psales.message') }}</span><textarea name="message" rows="3" maxlength="3000"></textarea></label>
                            <div><button class="primary" type="submit" @disabled($q->needsApproval() || $q->lines->where('kind', 'item')->isEmpty())>{{ $q->needsApproval() ? __('psales.send_blocked') : ($q->status === 'sent' ? __('psales.resend') : __('psales.send')) }}</button></div>
                        </form>
                    @endif
                    @if ($q->status === 'sent')
                        <details class="mt"><summary>{{ __('psales.accept_staff') }}</summary>
                            <form method="post" action="{{ route('psales.accept', $q) }}" class="stack-sm mt">@csrf<input name="name" required maxlength="120" placeholder="{{ __('psales.accepted_by') }}" aria-label="{{ __('psales.accepted_by') }}"><input name="po_number" maxlength="80" placeholder="{{ __('quotes.po') }}" aria-label="{{ __('quotes.po') }}"><div><button class="btn" type="submit">{{ __('psales.mark_accepted') }}</button></div></form>
                        </details>
                    @endif
                    @if ($q->status === 'accepted')
                        <form method="post" action="{{ route('psales.confirm', $q) }}">@csrf<button class="primary" type="submit" @disabled(! $q->datesIn())>{{ __('psales.send_oc', ['number' => $q->docCode('S-OC')]) }}</button></form>
                        @unless ($q->datesIn())<small class="muted">{{ __('psales.oc_wait') }}</small>@endunless
                    @endif
                    @if ($q->status === 'confirmed')
                        <form method="post" action="{{ route('psales.deliver', $q) }}">@csrf<button class="primary" type="submit">{{ __('psales.mark_delivered') }}</button></form>
                        <small class="muted">{{ __('psales.deliver_hint') }}</small>
                    @endif
                    @if (in_array($q->status, ['draft', 'sent', 'accepted'], true))
                        <details class="mt"><summary>{{ __('psales.cancel') }}</summary><form method="post" action="{{ route('psales.cancel', $q) }}" class="mt">@csrf<button class="btn btn-danger" type="submit">{{ __('psales.cancel_go') }}</button></form></details>
                    @endif
                </section>
            @endif
            @if ($q->status === 'delivered')
                <section class="card"><h2>{{ __('nav.items.preinvoice') }}</h2>@include('finance._send', ['type' => 'sales', 'id' => $q->id])</section>
            @endif
            <section class="card">
                <h2>{{ __('psales.docs') }}</h2>
                <ul class="plain-list">
                    <li><a href="{{ route('psales.print', [$q, 'quo']) }}" target="_blank" rel="noopener">{{ $q->number }} · {{ __('documents.types.S-QUO') }}</a></li>
                    @if (in_array($q->status, ['confirmed', 'delivered'], true))
                        <li><a href="{{ route('psales.print', [$q, 'oc']) }}" target="_blank" rel="noopener">{{ $q->docCode('S-OC') }} · {{ __('documents.types.S-OC') }}</a></li>
                    @endif
                    @if (in_array($q->status, ['accepted', 'confirmed', 'delivered'], true))
                        <li><a href="{{ route('psales.print', [$q, 'pick']) }}" target="_blank" rel="noopener">{{ $q->docCode('S-PICK') }} · {{ __('documents.types.S-PICK') }}</a></li>
                        <li><a href="{{ route('psales.print', [$q, 'dn']) }}" target="_blank" rel="noopener">{{ $q->docCode('S-DN') }} · {{ __('documents.types.S-DN') }}</a></li>
                        <li><a href="{{ route('psales.print', [$q, 'pf']) }}" target="_blank" rel="noopener">{{ $q->docCode('S-PF') }} · {{ __('documents.types.S-PF') }}</a></li>
                    @endif
                </ul>
                @if ($q->token)<p class="muted small">{{ __('psales.public_link') }} <a href="{{ route('psales.public', $q->token) }}" target="_blank" rel="noopener">{{ __('psales.open') }}</a></p>@endif
            </section>
            <section class="card">
                <h2>{{ __('users.edit.activity') }}</h2>
                @forelse ($activity as $log)
                    <div class="log-line"><small class="muted">{{ $log->created_at->format('d/m H:i') }} · {{ $log->actor?->name ?? __('users.audit.system') }}</small><div>{{ \App\Support\AuditText::action($log) }}@if ($d = \App\Support\AuditText::details($log)) <span class="muted">· {{ $d }}</span>@endif</div></div>
                @empty
                    <p class="muted">{{ __('users.edit.no_activity') }}</p>
                @endforelse
            </section>
        </div>
    </div>
</x-layouts.app>
