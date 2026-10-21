<x-layouts.app :title="__('nav.items.locations')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.settings')) }} / {{ mb_strtoupper(__('nav.items.locations')) }}</div>
            <h1>{{ __('nav.items.locations') }}</h1>
            <p class="muted">{{ __('warehouse.settings.intro') }}</p>
        </div>
        <div class="inline-form">
            <a class="btn" href="{{ route('locations.positions') }}">{{ __('warehouse.settings.positions_n', ['n' => $positions]) }} →</a>
            <a class="primary" href="{{ route('locations.index', ['zone' => 'new']) }}">{{ __('warehouse.settings.add_zone') }}</a>
        </div>
    </div>
    @include('partials.flash')

    <section class="card">
        <h2>{{ __('warehouse.settings.route_title') }}</h2>
        <div class="wz-route">
            @foreach (['inbound', 'cleaning', 'precheck', 'bench', 'postcheck', 'storage'] as $t)
                <span class="wz wz-{{ $t }}"><b>{{ __('warehouse.types.'.$t) }}</b><small>{{ __('warehouse.types_sub.'.$t) }}</small></span>
            @endforeach
        </div>
        <p class="muted small">{{ __('warehouse.settings.route_text') }}</p>
    </section>

    <div class="split">
        <section class="card card-flush">
            <table class="table">
                <thead><tr><th>{{ __('warehouse.settings.qr') }}</th><th>{{ __('warehouse.settings.zone') }}</th><th>{{ __('warehouse.settings.type') }}</th><th>{{ __('warehouse.settings.for') }}</th><th>{{ __('warehouse.settings.next') }}</th><th class="num">{{ __('warehouse.settings.pieces') }}</th></tr></thead>
                <tbody>
                    @foreach ($zones as $z)
                        <tr @class(['row-sel' => $sel?->is($z), 'row-off' => ! $z->active])>
                            <td><a href="{{ route('locations.index', ['zone' => $z->code]) }}"><code>{{ $z->code }}</code></a></td>
                            <td><b>{{ $z->label() }}</b>@if ($z->note)<br><small class="muted">{{ $z->note }}</small>@endif</td>
                            <td>{{ __('warehouse.types.'.$z->type) }}</td>
                            <td>{{ $z->categories ? implode(', ', $z->categories) : __('warehouse.settings.all') }}</td>
                            <td><small>{{ collect($z->next ?? [])->map(fn ($c) => $c === 'RACKS' ? __('warehouse.zone_names.RACKS') : ($zones->firstWhere('code', $c)?->label() ?? $c))->implode(', ') ?: '—' }}</small></td>
                            <td class="num">{{ $z->type === 'storage' ? '' : ($counts[$z->code] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <aside>
            @if ($sel)
                <section class="card">
                    <h2>{{ $sel->exists ? $sel->label() : __('warehouse.settings.add_zone') }}</h2>
                    <form method="post" action="{{ $sel->exists ? route('locations.update', $sel) : route('locations.store') }}" class="stack-sm">
                        @csrf @if ($sel->exists) @method('PUT') @endif
                        <label class="field"><span>{{ __('warehouse.settings.qr') }}</span><input name="code" @if ($sel->exists) value="{{ $sel->code }}" disabled @else required value="{{ old('code') }}" @endif maxlength="30" pattern="[A-Za-z0-9\-]+"></label>
                        <label class="field"><span>{{ __('warehouse.settings.name') }}</span><input name="name" maxlength="80" value="{{ old('name', $sel->name) }}" placeholder="{{ $sel->exists ? $sel->label() : '' }}"></label>
                        <label class="field"><span>{{ __('warehouse.settings.type') }}</span><select name="type">@foreach (\App\Models\Warehouse\Zone::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $sel->type) === $t)>{{ __('warehouse.types.'.$t) }}</option>@endforeach</select></label>
                        <label class="field"><span>{{ __('warehouse.settings.for') }}</span><input name="categories" maxlength="200" value="{{ old('categories', implode(', ', $sel->categories ?? [])) }}" placeholder="{{ __('warehouse.settings.all') }}"><small class="muted">{{ __('warehouse.settings.for_hint') }}</small></label>
                        <fieldset class="plain"><legend>{{ __('warehouse.settings.next') }}</legend>
                            <div class="chips">
                                @foreach ($zones->where('code', '!=', $sel->code)->where('type', '!=', 'storage') as $z)
                                    <label class="check"><input type="checkbox" name="next[]" value="{{ $z->code }}" @checked(in_array($z->code, old('next', $sel->next ?? []), true))> {{ $z->label() }}</label>
                                @endforeach
                                <label class="check"><input type="checkbox" name="next[]" value="RACKS" @checked(in_array('RACKS', old('next', $sel->next ?? []), true))> {{ __('warehouse.zone_names.RACKS') }}</label>
                            </div>
                        </fieldset>
                        <label class="field"><span>{{ __('warehouse.settings.note') }}</span><input name="note" maxlength="200" value="{{ old('note', $sel->note) }}"></label>
                        <input type="hidden" name="active" value="0">
                        <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $sel->active ?? true))> {{ __('warehouse.settings.active') }}</label>
                        <div class="inline-form">
                            <button class="primary" type="submit">{{ __('warehouse.settings.save') }}</button>
                            @if ($sel->exists && $sel->type !== 'storage')<a class="btn" href="{{ route('locations.labels', ['zone' => $sel->code]) }}" target="_blank" rel="noopener">{{ __('warehouse.settings.print_label') }}</a>@endif
                        </div>
                    </form>
                </section>
            @else
                <section class="card"><p class="muted">{{ __('warehouse.settings.pick_zone') }}</p></section>
            @endif
        </aside>
    </div>
</x-layouts.app>
