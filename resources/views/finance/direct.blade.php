{{-- A pre-invoice without a deal or quote: hours, a repair, extra costs. Same road as every pre-invoice: checks, accountant, OkiOki. --}}
@php($rows = collect(old('lines', collect($pf->lines ?? [])->map(fn ($l) => ['d' => $l['d'], 'q' => $l['q'], 'unit' => number_format(($l['unit_cents'] ?? 0) / 100, 2, '.', '')])->all())))
@php($rows = $rows->concat(array_fill(0, max(3, 8 - $rows->count()), ['d' => '', 'q' => '', 'unit' => '']))->values())
<x-layouts.app :title="__('finance.direct.title')">
    <div class="pagehead"><div>
        <div class="kicker"><a href="{{ route('finance.index') }}">{{ mb_strtoupper(__('nav.items.preinvoice')) }}</a>@if ($pf->exists) · {{ $pf->number }}@endif</div>
        <h1>{{ $pf->exists ? __('finance.direct.edit_title', ['number' => $pf->number]) : __('finance.direct.title') }}</h1>
        <p class="muted">{{ __('finance.direct.intro') }}</p>
    </div></div>
    @include('partials.flash')
    <form method="post" action="{{ $pf->exists ? route('finance.direct.update', $pf) : route('finance.direct.store') }}" class="stack" id="direct-form">
        @csrf @if ($pf->exists) @method('PUT') @endif
        <section class="card">
            <div class="form-grid">
                <label class="field"><span>{{ __('finance.direct.customer') }}</span>
                    <select data-pick name="company_id" required><option value="">—</option>@foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) old('company_id', $pf->company_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('finance.direct.module') }}</span>
                    <select name="module" required>@foreach (\App\Models\Finance\PreInvoice::MODULES as $m)<option value="{{ $m }}" @selected(old('module', $pf->module) === $m)>{{ __('finance.modules.'.$m) }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('finance.direct.description') }}</span><input name="description" maxlength="200" required value="{{ old('description', $pf->description) }}" placeholder="{{ __('finance.direct.description_hint') }}"></label>
                <label class="field"><span>{{ __('finance.direct.date') }}</span><input type="date" name="service_date" required value="{{ old('service_date', $pf->service_date?->toDateString()) }}"></label>
                <label class="field"><span>{{ __('finance.direct.po') }}</span><input name="po_number" maxlength="60" value="{{ old('po_number', $pf->po_number) }}"></label>
            </div>
        </section>
        <section class="card card-flush">
            <table class="table" id="direct-lines">
                <thead><tr><th>{{ __('finance.direct.line') }}</th><th class="num">{{ __('finance.direct.qty') }}</th><th class="num">{{ __('finance.direct.unit') }}</th><th class="num">{{ __('finance.direct.amount') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $i => $r)
                        <tr><td><input name="lines[{{ $i }}][d]" maxlength="200" value="{{ $r['d'] }}" aria-label="{{ __('finance.direct.line') }}"></td>
                            <td class="num"><input type="number" step="0.25" name="lines[{{ $i }}][q]" value="{{ $r['q'] }}" class="w-xs" aria-label="{{ __('finance.direct.qty') }}"></td>
                            <td class="num nowrap">€ <input type="number" step="0.01" name="lines[{{ $i }}][unit]" value="{{ $r['unit'] }}" class="w-sm" aria-label="{{ __('finance.direct.unit') }}"></td>
                            <td class="num nowrap" data-amount></td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td colspan="3" class="num">{{ __('finance.direct.net') }}</td><td class="num nowrap"><b data-net>€ 0,00</b></td></tr></tfoot>
            </table>
            <div class="card-pad">
                <div class="inline-form">
                    <button class="btn" type="button" data-add-row>{{ __('finance.direct.add_row') }}</button>
                    @if ($labour->isNotEmpty())
                        <select data-labour aria-label="{{ __('finance.direct.labour') }}"><option value="">{{ __('finance.direct.labour') }}</option>@foreach ($labour as $lt)<option value="{{ $lt->id }}" data-name="{{ $lt->name() }}" data-price="{{ number_format($lt->price_cents / 100, 2, '.', '') }}">{{ $lt->name() }} · {{ \App\Services\Afrit6\Pricing::eur($lt->price_cents) }}/{{ __('quotes.labour.per.'.$lt->unit) }}</option>@endforeach</select>
                    @endif
                </div>
                <p class="muted small mt">{{ __('finance.direct.vat_note') }}</p>
                @error('lines')<p class="flash flash-err">{{ $message }}</p>@enderror
            </div>
        </section>
        <div class="form-actions"><button class="primary" type="submit">{{ $pf->exists ? __('finance.direct.save') : __('finance.direct.make') }}</button><a class="btn" href="{{ route('finance.index') }}">{{ __('finance.direct.cancel') }}</a></div>
    </form>
    <script>
    (function () {
        var table = document.getElementById('direct-lines'), body = table.tBodies[0];
        var eur = function (c) { return '€ ' + (c / 100).toLocaleString('nl-BE', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
        function sum() {
            var net = 0;
            Array.prototype.forEach.call(body.rows, function (tr) {
                var q = parseFloat(tr.querySelector('[name$="[q]"]').value), u = parseFloat(tr.querySelector('[name$="[unit]"]').value);
                var has = tr.querySelector('[name$="[d]"]').value.trim() !== '';
                var a = has ? Math.round((isNaN(q) ? 1 : q) * (isNaN(u) ? 0 : u) * 100) : 0;
                tr.querySelector('[data-amount]').textContent = has ? eur(a) : '';
                net += a;
            });
            table.querySelector('[data-net]').textContent = eur(net);
        }
        function addRow() {
            var last = body.rows[body.rows.length - 1], tr = last.cloneNode(true), n = body.rows.length;
            tr.querySelectorAll('input').forEach(function (i) { i.value = ''; i.name = i.name.replace(/\[\d+\]/, '[' + n + ']'); });
            body.appendChild(tr);
            return tr;
        }
        function emptyRow() {
            for (var i = 0; i < body.rows.length; i++) { if (body.rows[i].querySelector('[name$="[d]"]').value.trim() === '') return body.rows[i]; }
            return addRow();
        }
        document.querySelector('[data-add-row]').addEventListener('click', function () { addRow().querySelector('input').focus(); });
        var pick = document.querySelector('[data-labour]');
        if (pick) pick.addEventListener('change', function () {
            var o = pick.selectedOptions[0];
            if (!o || !o.value) return;
            var tr = emptyRow();
            tr.querySelector('[name$="[d]"]').value = o.getAttribute('data-name');
            tr.querySelector('[name$="[q]"]').value = 1;
            tr.querySelector('[name$="[unit]"]').value = o.getAttribute('data-price');
            pick.value = '';
            sum();
            tr.querySelector('[name$="[q]"]').focus();
        });
        body.addEventListener('input', sum);
        sum();
    })();
    </script>
</x-layouts.app>
