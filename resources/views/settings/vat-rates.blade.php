@php($fmt = fn ($d) => $d?->format('d/m/Y'))
<x-layouts.app :title="__('nav.items.vat')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('users.kicker')) }}</div>
            <h1>{{ __('nav.items.vat') }}</h1>
            <p class="muted">{{ __('settings.vat.intro') }}</p>
        </div>
        <a class="primary" href="{{ route('settings.vat.index', ['edit' => 'new']) }}">{{ __('settings.vat.add') }}</a>
    </div>
    @include('partials.flash')

    <div class="split">
        <div class="stack">
            <section class="card card-flush">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr>
                            <th>{{ __('settings.vat.name') }}</th><th>{{ __('settings.vat.rate') }}</th><th>{{ __('settings.vat.code') }}</th>
                            <th>{{ __('settings.vat.zone') }}</th><th>{{ __('settings.vat.valid') }}</th><th>{{ __('settings.vat.status') }}</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($rates as $r)
                                @php($st = $r->status())
                                <tr @class(['row-sel' => $editing?->id === $r->id, 'row-off' => $st === 'ended'])>
                                    <td><b>{{ $r->name }}</b></td>
                                    <td>{{ $r->percent() }}</td>
                                    <td>{{ $r->code }}</td>
                                    <td>{{ __('settings.vat.zones.'.$r->zone) }}</td>
                                    <td class="nowrap">
                                        {{ $r->valid_from->year <= 1996 ? __('settings.vat.always') : __('settings.vat.from', ['date' => $fmt($r->valid_from)]) }}
                                        @if ($r->valid_until) {{ __('settings.vat.until', ['date' => $fmt($r->valid_until)]) }}@endif
                                    </td>
                                    <td><span @class(['pill', 'pill-ok' => $st === 'active', 'pill-info' => $st === 'planned', 'pill-off' => $st === 'ended'])>{{ __('settings.vat.statuses.'.$st) }}</span></td>
                                    <td><a href="{{ route('settings.vat.index', ['edit' => $r->id]) }}">{{ __('settings.edit') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <form class="card" method="post" action="{{ route('settings.vat.defaults') }}">
                @csrf @method('put')
                <h2>{{ __('settings.vat.defaults') }}</h2>
                <p class="muted">{{ __('settings.vat.defaults_intro') }}</p>
                <div class="form-grid">
                    @foreach (\App\Http\Controllers\Settings\VatRateController::DEFAULTS as $key)
                        <label class="field"><span>{{ __('settings.vat.default_labels.'.$key) }}</span>
                            <select name="{{ $key }}">
                                @foreach ($choices as $c)
                                    <option value="{{ $c->code }}" @selected($company->{$key} === $c->code)>{{ $c->name }}</option>
                                @endforeach
                            </select></label>
                    @endforeach
                </div>
                <p class="muted small">{{ __('settings.vat.venue_note') }}</p>
                <div class="form-actions"><button class="btn" type="submit">{{ __('settings.save') }}</button></div>
            </form>
        </div>

        <aside class="stack">
            @if ($editing)
                <form class="card" method="post" action="{{ $editing->exists ? route('settings.vat.update', $editing) : route('settings.vat.store') }}">
                    @csrf @if ($editing->exists) @method('put') @endif
                    <h2>{{ $editing->exists ? __('settings.vat.edit_title', ['name' => $editing->name]) : __('settings.vat.new_title') }}</h2>
                    <div class="form-grid form-grid-1">
                        <label class="field"><span>{{ __('settings.vat.name') }}</span><input name="name" value="{{ old('name', $editing->name) }}" required maxlength="80"></label>
                        <label class="field"><span>{{ __('settings.vat.rate') }} (%)</span><input type="number" step="0.01" min="0" max="100" name="rate" value="{{ old('rate', $editing->exists ? $editing->rate_bp / 100 : '') }}" required></label>
                        <label class="field"><span>{{ __('settings.vat.code') }}</span><input name="code" value="{{ old('code', $editing->code) }}" required maxlength="4"><small>{{ __('settings.vat.code_hint') }}</small></label>
                        <label class="field"><span>{{ __('settings.vat.zone') }}</span>
                            <select name="zone">
                                @foreach (\App\Models\VatRate::ZONES as $z)<option value="{{ $z }}" @selected(old('zone', $editing->zone) === $z)>{{ __('settings.vat.zones.'.$z) }}</option>@endforeach
                            </select></label>
                        <label class="field"><span>{{ __('settings.vat.valid_from') }}</span><input type="date" name="valid_from" value="{{ old('valid_from', $editing->valid_from?->toDateString()) }}" required></label>
                        <label class="field"><span>{{ __('settings.vat.valid_until') }}</span><input type="date" name="valid_until" value="{{ old('valid_until', $editing->valid_until?->toDateString()) }}"></label>
                    </div>
                    @if ($editing->exists)<p class="muted small">{{ __('settings.vat.edit_note') }}</p>@endif
                    <div class="form-actions">
                        <a class="btn" href="{{ route('settings.vat.index') }}">{{ __('settings.cancel') }}</a>
                        <button class="primary" type="submit">{{ __('settings.save') }}</button>
                    </div>
                </form>

                @if ($editing->exists)
                    <section class="card">
                        @if ($editing->status() === 'active')
                            @if ($scheduling)
                                <form method="post" action="{{ route('settings.vat.schedule', $editing) }}">
                                    @csrf
                                    <h2>{{ __('settings.vat.schedule_title', ['name' => $editing->name]) }}</h2>
                                    <div class="form-grid">
                                        <label class="field"><span>{{ __('settings.vat.new_rate') }}</span><input type="number" step="0.01" min="0" max="100" name="new_rate" value="{{ old('new_rate', $editing->rate_bp / 100) }}" required></label>
                                        <label class="field"><span>{{ __('settings.vat.from_date') }}</span><input type="date" name="from" value="{{ old('from', today()->addYear()->startOfYear()->toDateString()) }}" required></label>
                                    </div>
                                    <p class="muted small">{{ __('settings.vat.schedule_note') }}</p>
                                    <div class="form-actions"><button class="primary" type="submit">{{ __('settings.vat.schedule') }}</button></div>
                                </form>
                            @else
                                <a class="btn" href="{{ route('settings.vat.index', ['edit' => $editing->id, 'schedule' => 1]) }}">{{ __('settings.vat.schedule_change') }}</a>
                            @endif
                            <form method="post" action="{{ route('settings.vat.end', $editing) }}" class="inline-form">@csrf<button class="btn" type="submit">{{ __('settings.vat.end_today') }}</button></form>
                        @elseif ($editing->status() === 'planned')
                            <form method="post" action="{{ route('settings.vat.destroy', $editing) }}" onsubmit="return confirm(@js(__('settings.vat.delete_confirm')))">@csrf @method('delete')<button class="btn btn-danger" type="submit">{{ __('settings.vat.delete') }}</button></form>
                        @else
                            <p class="muted">{{ __('settings.vat.ended_note') }}</p>
                        @endif
                    </section>
                @endif
            @else
                <section class="card">
                    <h2>{{ __('settings.vat.how_title') }}</h2>
                    <p class="muted">{{ __('settings.vat.how_text') }}</p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
