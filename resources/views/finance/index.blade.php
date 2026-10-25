@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($ago = fn ($d) => $d->locale(app()->getLocale())->diffForHumans())
<x-layouts.app :title="__('nav.items.preinvoice')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.finance')) }} / {{ mb_strtoupper(__('nav.items.preinvoice')) }}</div>
            <h1>{{ __('nav.items.preinvoice') }}</h1>
            <p class="muted">{{ __('finance.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @if ($ready->isNotEmpty())
        <section class="card pf-ready" id="ready">
            <div class="card-head"><h2>{{ __('finance.ready.title') }} <small class="muted">{{ $ready->count() }}</small></h2><span class="muted small">{{ __('finance.ready.hint') }}</span></div>
            @foreach ($ready as $o)
                <div class="pf-ready-row">
                    <div class="pf-ready-main">
                        <a href="{{ route('orders.show', $o) }}"><b>{{ $o->number }}</b> · {{ $o->customerName() }}</a>
                        <small class="muted">{{ $o->title }} · {{ $o->use_from->format('d/m') }}–{{ $o->use_until->format('d/m/Y') }} · {{ $o->returned_at ? __('finance.ready.back_in') : ($o->loaded_at ? __('finance.ready.out') : __('finance.ready.not_out')) }}@if ($o->transportToPrice()) · <b class="txt-warn">{{ __('finance.ready.transport_missing') }}</b>@endif</small>
                    </div>
                    <b class="nowrap">{{ $eur($o->net_cents) }}</b>
                    @if ($canMake)<form method="post" action="{{ route('finance.make', ['rental', $o->id]) }}">@csrf<button class="btn btn-sm" type="submit">{{ __('finance.send_to') }}</button></form>@endif
                </div>
            @endforeach
        </section>
    @endif
    <div class="tiles">
        <a class="tile tile-waiting" href="{{ route('finance.index', ['f' => 'check']) }}"><span class="tile-k">{{ mb_strtoupper(__('finance.tiles.check')) }}</span><b class="tile-v">{{ $tiles['check'][0] }}</b><small class="muted">{{ $eur($tiles['check'][1]) }} · {{ trans_choice('finance.tiles.red', $tiles['check'][2], ['n' => $tiles['check'][2]]) }}</small></a>
        <a class="tile tile-subhire" href="{{ route('finance.index', ['f' => 'back']) }}"><span class="tile-k">{{ mb_strtoupper(__('finance.tiles.back')) }}</span><b class="tile-v">{{ $tiles['back'][0] }}</b><small class="muted">{{ __('finance.tiles.back_s') }}</small></a>
        <a class="tile" href="{{ route('finance.index', ['f' => 'sent']) }}"><span class="tile-k">{{ mb_strtoupper(__('finance.tiles.sent')) }}</span><b class="tile-v">{{ $tiles['sent'][0] }}</b><small class="muted">{{ $eur($tiles['sent'][1]) }} {{ __('finance.tiles.unpaid') }}</small></a>
        <a class="tile tile-subhire" href="{{ route('finance.index', ['f' => 'late']) }}"><span class="tile-k">{{ mb_strtoupper(__('finance.tiles.late')) }}</span><b class="tile-v">{{ $tiles['late'][0] }}</b><small class="muted">{{ $eur($tiles['late'][1]) }} · {{ __('finance.tiles.late_s') }}</small></a>
    </div>
    <nav class="chips">
        @foreach (\App\Http\Controllers\Finance\PreInvoiceController::FILTERS as $f)<a @class(['chip', 'chip-on' => $filt === $f]) href="{{ route('finance.index', array_filter(['f' => $f, 'flags' => $flagOnly ? 1 : null])) }}">{{ __('finance.filters.'.$f) }} · {{ $counts[$f] }}</a>@endforeach
        <a @class(['chip', 'chip-on' => $flagOnly]) href="{{ route('finance.index', array_filter(['f' => $filt, 'flags' => $flagOnly ? null : 1])) }}">⚑ {{ __('finance.flags_only') }}</a>
    </nav>

    <div class="pf-split mt">
        <section class="card card-flush">
            <ul class="inbox">
                @forelse ($list as $p)
                    <li><a @class(['inbox-item', 'is-on' => $sel?->is($p)]) href="{{ route('finance.index', array_filter(['f' => $filt, 'flags' => $flagOnly ? 1 : null, 'pf' => $p->id])) }}">
                        <span class="inbox-main"><b>{{ $p->number }} · {{ $p->customerName() }}</b>
                            <small class="muted">{{ __('finance.modules.'.$p->module) }} · {{ $p->description }} · {{ $p->madeBy?->first_name ?? '—' }} · {{ $ago($p->created_at) }}</small>
                            @if ($p->reds())<small class="txt-danger">⚑ {{ trans_choice('finance.red_n', $p->reds(), ['n' => $p->reds()]) }}</small>@elseif ($p->ambers())<small class="pf-amber">⚑ {{ trans_choice('finance.amber_n', $p->ambers(), ['n' => $p->ambers()]) }}</small>@endif</span>
                        <span class="inbox-side"><b>{{ $eur($p->gross_cents) }}</b><span @class(['pill', 'pill-warn' => $p->status === 'check', 'pill-danger' => $p->status === 'back' || $p->isOverdue(), 'pill-info' => $p->status === 'sent' && ! $p->isOverdue(), 'pill-ok' => $p->status === 'paid'])>{{ $p->isOverdue() ? __('finance.days_late', ['n' => $p->daysLate()]) : __('finance.statuses.'.$p->status) }}</span></span>
                    </a></li>
                @empty
                    <li class="muted inbox-empty">{{ __('finance.none') }}</li>
                @endforelse
            </ul>
        </section>

        @if ($sel)
            <aside class="stack">
                <section class="card">
                    <div class="kicker">{{ mb_strtoupper(__('finance.proforma')) }} · {{ mb_strtoupper(__('finance.modules.'.$sel->module)) }}</div>
                    <h2>{{ $sel->number }} · {{ $sel->customerName() }}</h2>
                    <p class="muted">{{ $sel->description }} · {{ __('finance.made_by', ['name' => $sel->madeBy?->name ?? '—']) }} · {{ __('finance.from') }} {{ $sel->source_code }}</p>
                    <p><span @class(['pill', 'pill-warn' => $sel->status === 'check', 'pill-danger' => $sel->status === 'back', 'pill-info' => $sel->status === 'sent', 'pill-ok' => $sel->status === 'paid'])>{{ __('finance.statuses.'.$sel->status) }}@if ($sel->invoice_number) · {{ $sel->invoice_number }}@endif</span></p>
                    @if ($sel->reds() && $sel->status === 'check')<div class="flash flash-err">⚑ {{ trans_choice('finance.banner', $sel->reds(), ['n' => $sel->reds()]) }}</div>@endif
                    <dl class="facts">
                        <dt>{{ __('finance.facts.vat_id') }}</dt><dd>{{ $sel->company?->vat_number ? \App\Support\VatNumber::format($sel->company->vat_number) : __('finance.missing') }}</dd>
                        <dt>{{ __('finance.facts.vat') }}</dt><dd>{{ $sel->vat_code ?? '—' }} · {{ rtrim(rtrim(number_format($sel->vat_rate_bp / 100, 2, ',', ''), '0'), ',') }}%</dd>
                        <dt>{{ __('finance.facts.po') }}</dt><dd>{{ $sel->po_number ?: ($sel->company?->po_required ? __('finance.missing') : __('finance.not_required')) }}</dd>
                        <dt>{{ __('finance.facts.term') }}</dt><dd>{{ trans_choice('companies.days', $sel->payment_days, ['days' => $sel->payment_days]) }}@if ($sel->due_date) · {{ __('finance.due', ['date' => $sel->due_date->format('d/m/Y')]) }}@endif</dd>
                        <dt>{{ __('finance.facts.address') }}</dt><dd>{{ $sel->company?->address() ?: __('finance.missing') }}</dd>
                    </dl>
                </section>

                @if ($sel->checks)
                <section class="card">
                    <h2>{{ __('finance.checks_title') }}</h2>
                    @foreach (collect($sel->checks ?? [])->sortBy(fn ($c) => array_search($c['level'], ['bad', 'warn', 'ok'], true)) as $c)
                        <div class="pf-check pf-{{ $c['level'] }}"><span class="pf-mark">{{ ['ok' => '✓', 'warn' => '!', 'bad' => '✕'][$c['level']] }}</span><span><b>{{ __('finance.checks.'.$c['key'], $c['p']) }}</b><small class="muted">{{ __('finance.cats.'.$c['cat']) }}</small></span></div>
                    @endforeach
                </section>
                @endif

                <section class="card card-flush">
                    <table class="table">
                        <thead><tr><th>{{ __('finance.cols.desc') }}</th><th class="num">{{ __('finance.cols.qty') }}</th><th class="num">{{ __('finance.cols.unit') }}</th><th class="num">{{ __('finance.cols.amount') }}</th></tr></thead>
                        <tbody>@foreach ($sel->lines as $l)<tr><td>{{ $l['d'] }}@if (! empty($l['note']))<br><small class="muted">{{ $l['note'] }}</small>@endif</td><td class="num">{{ $l['q'] }}</td><td class="num nowrap">{{ $eur($l['unit_cents']) }}</td><td class="num nowrap">{{ $eur($l['amount_cents']) }}</td></tr>@endforeach</tbody>
                    </table>
                    <div class="mg-pad"><div class="doc-totals">
                        <div><span>{{ __('finance.subtotal') }}</span><span>{{ $eur($sel->net_cents) }}</span></div>
                        <div class="muted"><span>{{ $sel->vat_rate_bp ? __('quotes.vat', ['pct' => rtrim(rtrim(number_format($sel->vat_rate_bp / 100, 2, '.', ''), '0'), '.').'%']) : __('finance.no_vat_line', ['code' => $sel->vat_code]) }}</span><span>{{ $eur($sel->vat_cents) }}</span></div>
                        <div class="grand"><span>{{ __('quotes.total') }}</span><span>{{ $eur($sel->gross_cents) }}</span></div>
                    </div></div>
                </section>

                @if ($canDecide && $sel->status === 'check')
                    <section class="card">
                        <form method="post" action="{{ route('finance.accept', $sel) }}" class="stack-sm">@csrf
                            <label class="field"><span>{{ __('finance.invoice_number') }}</span><input name="invoice_number" maxlength="40" placeholder="INV {{ now()->year }}/…"></label>
                            @if ($sel->reds())<input type="hidden" name="override" value="0"><label class="check"><input type="checkbox" name="override" value="1"> {{ __('finance.override') }}</label>@endif
                            <div class="actionbar pf-actions"><button class="primary" type="submit">{{ __('finance.accept') }}</button><a class="btn" href="{{ route('finance.print', $sel) }}" target="_blank" rel="noopener">{{ __('finance.print') }}</a></div>
                            <small class="muted">{{ __('finance.okioki_hint') }}</small>
                        </form>
                        <details class="mt"><summary>{{ __('finance.send_back', ['name' => $sel->madeBy?->first_name ?? '—']) }}</summary>
                            <form method="post" action="{{ route('finance.back', $sel) }}" class="stack-sm mt">@csrf
                                <select name="reason" aria-label="{{ __('finance.reason') }}">@foreach (['address', 'vat', 'po', 'amount', 'margin', 'other'] as $r)<option value="{{ $r }}">{{ __('finance.reasons.'.$r) }}</option>@endforeach</select>
                                <textarea name="note" rows="3" maxlength="800" required placeholder="{{ __('finance.back_hint') }}" aria-label="{{ __('finance.back_hint') }}"></textarea>
                                <div><button class="btn" type="submit">{{ __('finance.back_go') }}</button></div>
                            </form>
                        </details>
                        <details class="mt"><summary>{{ __('finance.cancel') }}</summary><form method="post" action="{{ route('finance.cancel', $sel) }}" class="mt">@csrf<button class="btn btn-danger" type="submit">{{ __('finance.cancel_go') }}</button></form></details>
                    </section>
                @elseif ($sel->status === 'back')
                    <section class="card">
                        <p><b>{{ __('finance.statuses.back') }}:</b> {{ $sel->back_note }}</p>
                        <form method="post" action="{{ route('finance.resend', $sel) }}" class="stack-sm">@csrf<input name="reply" maxlength="800" placeholder="{{ __('finance.reply_hint') }}" aria-label="{{ __('finance.reply_hint') }}"><div><button class="btn" type="submit">{{ __('finance.resend') }}</button></div></form>
                    </section>
                @elseif ($canDecide && $sel->status === 'sent')
                    <section class="card">
                        <form method="post" action="{{ route('finance.paid', $sel) }}">@csrf<button class="btn" type="submit">{{ __('finance.mark_paid') }}</button></form>
                        <small class="muted">{{ __('finance.paid_hint') }}</small>
                        @if ($sel->isOverdue())
                            @php($fee = \App\Services\Finance\PreInvoices::lateFee($sel))
                            <div class="pf-late mt">
                                <h3>{{ __('finance.late_title', ['n' => $fee['days']]) }}</h3>
                                <p>{{ __('finance.late_text', ['interest' => $eur($fee['interest']), 'rate' => config('afrit6.payment.statutory_rate'), 'fixed' => $eur($fee['fixed'])]) }}</p>
                                @if (\App\Services\Finance\PreInvoices::isPrivate($sel))<p class="muted small">{{ __('finance.late_private') }}</p>@endif
                                @if ($sel->reminder?->decision)<p><span class="pill">{{ __('finance.late_decisions.'.$sel->reminder->decision) }}</span> {{ $sel->reminder->decidedBy?->name }}@if ($sel->reminder->reason) · {{ $sel->reminder->reason }}@endif</p>@endif
                                <form method="post" action="{{ route('finance.late', $sel) }}" class="stack-sm">@csrf
                                    <select name="decision" aria-label="{{ __('finance.late_decide') }}">@php($def = $sel->reminder?->decision ?? (\App\Services\Finance\PreInvoices::isPrivate($sel) ? 'skip' : 'now'))@foreach (\App\Models\Finance\Reminder::DECISIONS as $d)<option value="{{ $d }}" @selected($d === $def)>{{ __('finance.late_decisions.'.$d) }}</option>@endforeach</select>
                                    <input name="reason" maxlength="500" placeholder="{{ __('finance.late_reason') }}" aria-label="{{ __('finance.late_reason') }}">
                                    <div><button class="btn" type="submit">{{ __('finance.late_decide') }}</button></div>
                                </form>
                            </div>
                        @endif
                    </section>
                @endif

                <section class="card">
                    <h2>{{ __('finance.history') }}</h2>
                    @forelse ($activity as $log)
                        <div class="log-line"><small class="muted">{{ $log->created_at->format('d/m H:i') }} · {{ $log->actor?->name ?? __('users.audit.system') }}</small><div>{{ \App\Support\AuditText::action($log) }}@if ($d = \App\Support\AuditText::details($log)) <span class="muted">· {{ $d }}</span>@endif</div></div>
                    @empty
                        <p class="muted">{{ __('users.edit.no_activity') }}</p>
                    @endforelse
                </section>
            </aside>
        @endif
    </div>
</x-layouts.app>
