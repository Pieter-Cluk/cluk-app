@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($can = auth()->user()->canUseModule('warehouse', 2))
@php($pieces = $c->items->flatMap->pieces)
<x-layouts.app :title="$c->company->name">
    <div class="pagehead"><div>
        <div class="kicker"><a href="{{ route('storage.index') }}">{{ mb_strtoupper(__('nav.items.storage')) }}</a> · {{ $c->code() }}</div>
        <h1>{{ $c->company->name }}</h1>
        <p class="muted">{{ __('storage.since') }} {{ $c->start_date->format('d/m/Y') }}@if ($c->end_date) – {{ $c->end_date->format('d/m/Y') }}@endif · {{ __('checks.notice.owner_is', ['name' => $c->company->accountOwner?->name ?? '—']) }}@if ($c->note) · {{ $c->note }}@endif</p>
    </div></div>
    @include('partials.flash')
    @if ($errors->any())<p class="flash flash-err">{{ $errors->first() }}</p>@endif

    <div class="cb-grid">
        <div class="stack">
            {{-- Their equipment and our labels --}}
            <section class="card card-flush">
                <div class="card-head pad"><h2>{{ __('storage.equipment') }} <small class="muted">{{ $pieces->where('status', 'in')->count() }} / {{ $pieces->count() }} {{ __('storage.here') }}</small></h2></div>
                <table class="table">
                    <thead><tr><th>{{ __('storage.item') }}</th><th>{{ __('storage.labels') }}</th><th>{{ __('storage.procedure') }}</th></tr></thead>
                    <tbody>
                        @forelse ($c->items as $it)
                            <tr>
                                <td><b>{{ $it->name }}</b>@if ($it->their_number)<br><small class="muted">{{ $it->their_number }}</small>@endif @if ($it->sameAs)<br><small class="muted">= {{ $it->sameAs->name }}</small>@endif</td>
                                <td class="st-codes">@foreach ($it->pieces as $pc)<span @class(['pill', 'pill-ok' => $pc->status === 'in']) title="{{ $pc->serial_number ? 'S/N '.$pc->serial_number : '' }}">{{ $pc->code }}@if ($pc->checks->first()?->result === 'repair') ⚠@endif</span> @endforeach</td>
                                <td>@if ($can)<form method="post" action="{{ route('storage.item.update', [$c, $it]) }}" class="inline-form">@csrf
                                    <select name="check_procedure_id" aria-label="{{ __('storage.procedure') }}"><option value="">—</option>@foreach ($procedures as $pr)<option value="{{ $pr->id }}" @selected($it->check_procedure_id === $pr->id)>{{ $pr->name() }}</option>@endforeach</select>
                                    <input type="hidden" name="same_as_item_id" value="{{ $it->same_as_item_id }}"><button class="btn btn-sm" type="submit">{{ __('storage.save') }}</button></form>@else{{ $it->checkProcedure?->name() ?? '—' }}@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">{{ __('storage.no_items') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            @if ($can)
                <details class="card">
                    <summary><b>{{ __('storage.add_item') }}</b></summary>
                    <form method="post" action="{{ route('storage.item', $c) }}" class="form-grid">@csrf
                        <label class="field"><span>{{ __('storage.item_name') }}</span><input name="name" maxlength="160" required placeholder="{{ __('storage.item_hint') }}"></label>
                        <label class="field"><span>{{ __('storage.their_number') }}</span><input name="their_number" maxlength="60"></label>
                        <label class="field"><span>{{ __('storage.how_many') }}</span><input type="number" name="pieces" min="1" max="500" value="1" required class="w-xs"></label>
                        <label class="field"><span>{{ __('storage.procedure') }}</span><select name="check_procedure_id"><option value="">—</option>@foreach ($procedures as $pr)<option value="{{ $pr->id }}">{{ $pr->name() }}</option>@endforeach</select></label>
                        <label class="field"><span>{{ __('storage.same_as') }}</span><select data-pick name="same_as_item_id"><option value="">—</option>@foreach ($ourItems as $oi)<option value="{{ $oi->id }}">{{ $oi->name }} · {{ $oi->number }}</option>@endforeach</select><small class="muted">{{ __('storage.same_as_hint') }}</small></label>
                        <label class="field"><span>{{ __('storage.serials') }}</span><textarea name="serials" rows="3" placeholder="{{ __('storage.serials_hint') }}"></textarea></label>
                        <div><button class="primary" type="submit">{{ __('storage.add') }}</button></div>
                    </form>
                </details>

                {{-- Goods in / out --}}
                <details class="card" @if ($errors->hasAny(['kind', 'date'])) open @endif>
                    <summary><b>{{ __('storage.move_title') }}</b> <span class="muted small">{{ __('storage.move_hint') }}</span></summary>
                    <form method="post" action="{{ route('storage.move', $c) }}" class="stack-sm">@csrf
                        <div class="form-grid">
                            <label class="field"><span>{{ __('storage.move_kind') }}</span><select name="kind"><option value="in">{{ __('storage.kinds.in') }}</option><option value="out">{{ __('storage.kinds.out') }}</option></select></label>
                            <label class="field"><span>{{ __('storage.date') }}</span><input type="date" name="date" value="{{ today()->toDateString() }}" required></label>
                            <label class="field"><span>{{ __('storage.colis') }}</span><input type="number" name="colis" min="0" value="0" class="w-xs"></label>
                            <label class="field"><span>{{ __('storage.pallets') }}</span><input type="number" name="pallets" min="0" value="0" class="w-xs"></label>
                            <label class="field"><span>{{ __('storage.signed') }}</span><input name="signed_name" maxlength="120"></label>
                            <label class="field"><span>{{ __('storage.note') }}</span><input name="note" maxlength="300"></label>
                        </div>
                        @if ($pieces->isNotEmpty())
                            <div class="st-pick">@foreach ($pieces as $pc)<label class="check"><input type="checkbox" name="pieces[]" value="{{ $pc->id }}"> {{ $pc->code }} <small class="muted">{{ $pc->item->name ?? '' }} · {{ __('storage.status.'.$pc->status) }}</small></label>@endforeach</div>
                        @endif
                        <div><button class="primary" type="submit">{{ __('storage.save_move') }}</button></div>
                    </form>
                </details>
            @endif

            <section class="card card-flush">
                <div class="card-head pad"><div><h2>{{ __('storage.checks') }}</h2><p class="muted small">{{ __('storage.checks_hint', ['rate' => $eur($rate)]) }}</p></div></div>
                <table class="table">
                    <tbody>
                        @forelse ($checks as $ch)
                            <tr><td><b>{{ $ch->piece->code }}</b> {{ $ch->piece->item->name }}</td>
                                <td>@if ($ch->finished_at)<span @class(['pill', 'pill-ok' => $ch->result === 'ok', 'pill-danger' => $ch->result === 'repair'])>{{ __('checks.tech.result.'.$ch->result) }}</span>@else<span class="pill pill-warn">{{ __('storage.busy') }}</span>@endif</td>
                                <td>{{ $ch->done_by === 'customer' ? __('storage.by_customer') : __('storage.by_cluk', ['n' => $ch->minutes ?? '—']) }}</td>
                                <td class="muted">{{ $ch->checkedBy?->first_name }} {{ $ch->finished_at?->format('d/m H:i') }}</td>
                                <td><a class="btn btn-sm" href="{{ route('w.stored', $ch->piece) }}">{{ $ch->finished_at ? __('storage.check_again') : __('checks.tech.continue') }}</a></td></tr>
                        @empty
                            <tr><td class="muted">{{ __('storage.no_checks') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="card card-flush">
                <div class="card-head pad"><h2>{{ __('storage.moves') }}</h2></div>
                <table class="table">
                    <tbody>
                        @forelse ($c->moves as $m)
                            <tr><td>{{ $m->date->format('d/m/Y') }}</td><td><span @class(['pill', 'pill-ok' => $m->kind === 'in', 'pill-info' => $m->kind === 'out'])>{{ __('storage.kinds.'.$m->kind) }}</span></td>
                                <td>{{ \App\Services\Afrit6\StatementBuilder::counts($m->colis, $m->pallets) ?: '—' }} · {{ trans_choice('storage.n_pieces', count($m->pieces ?? []), ['n' => count($m->pieces ?? [])]) }}</td>
                                <td class="muted">{{ $m->signed_name }}@if ($m->note) · {{ $m->note }}@endif</td></tr>
                        @empty
                            <tr><td class="muted">{{ __('storage.no_moves') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="stack">
            <section class="card">
                <h2>{{ __('storage.places') }}</h2>
                <ul class="cb-items">
                    @forelse ($c->places as $pl)
                        <li><span><b>{{ $pl->position->code }}</b> · {{ __('storage.place_kind.'.$pl->kind) }}<br><small class="muted">{{ $pl->from->format('d/m/Y') }} – {{ $pl->until?->format('d/m/Y') ?? __('storage.running') }}</small></span>
                            @if ($can && ! $pl->until)<form method="post" action="{{ route('storage.place.end', [$c, $pl]) }}" class="inline-form">@csrf<input type="date" name="until" value="{{ today()->toDateString() }}" aria-label="{{ __('storage.until') }}"><button class="linkbtn danger" type="submit">{{ __('storage.end_place') }}</button></form>@endif</li>
                    @empty
                        <li class="muted">{{ __('storage.no_places') }}</li>
                    @endforelse
                </ul>
                @if ($can && $c->status === 'active')
                    @if ($freePlaces->isEmpty())
                        <p class="muted small">{{ __('storage.no_free_places') }}</p>
                    @else
                        <form method="post" action="{{ route('storage.place', $c) }}" class="stack-sm">@csrf
                            <select data-pick name="position_id" required aria-label="{{ __('storage.place') }}">@foreach ($freePlaces as $fp)<option value="{{ $fp->id }}">{{ $fp->code }}</option>@endforeach</select>
                            <div class="inline-form"><select name="kind" aria-label="{{ __('storage.place_kind_label') }}"><option value="pallet">{{ __('storage.place_kind.pallet') }}</option><option value="shelf">{{ __('storage.place_kind.shelf') }}</option></select>
                                <input type="date" name="from" value="{{ today()->toDateString() }}" aria-label="{{ __('storage.from') }}"><button class="btn" type="submit">{{ __('storage.add_place') }}</button></div>
                        </form>
                    @endif
                @endif
            </section>
            <section class="card">
                <h2>{{ __('storage.this_month') }}</h2>
                <ul class="cb-versions">@forelse ($preview as $l)<li>{{ $l['d'] }} · <b>{{ $eur($l['amount_cents']) }}</b></li>@empty<li class="muted">—</li>@endforelse</ul>
                <p><b>{{ __('storage.so_far', ['amount' => $eur(collect($preview)->sum('amount_cents'))]) }}</b> <small class="muted">{{ __('storage.excl_vat') }}</small></p>
                <p class="muted small">{{ __('storage.monthly_hint') }}</p>
                @if ($can)
                    <form method="post" action="{{ route('storage.statement', $c) }}" class="inline-form">@csrf<input type="month" name="month" value="{{ today()->subMonth()->format('Y-m') }}" aria-label="{{ __('storage.month') }}"><button class="btn" type="submit">{{ __('storage.make_statement') }}</button></form>
                @endif
                <ul class="cb-versions">@foreach ($c->statements as $st)<li>{{ $st->period_start->format('m/Y') }} · @if ($st->preInvoice)<a href="{{ route('finance.index', ['pf' => $st->preInvoice->id]) }}">{{ $st->preInvoice->number }}</a> · {{ $eur($st->preInvoice->gross_cents) }}@endif</li>@endforeach</ul>
            </section>
            @if ($can && $c->status === 'active')
                <details class="card"><summary><b>{{ __('storage.end_contract') }}</b></summary>
                    <form method="post" action="{{ route('storage.end', $c) }}" class="stack-sm">@csrf<p class="muted small">{{ __('storage.end_hint') }}</p><input type="date" name="end_date" value="{{ today()->toDateString() }}" aria-label="{{ __('storage.until') }}"><div><button class="btn" type="submit">{{ __('storage.end_contract') }}</button></div></form>
                </details>
            @endif
        </aside>
    </div>
</x-layouts.app>
