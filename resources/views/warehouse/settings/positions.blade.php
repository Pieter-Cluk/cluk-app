<x-layouts.app :title="__('warehouse.settings.positions')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.settings')) }} / <a href="{{ route('locations.index') }}">{{ mb_strtoupper(__('nav.items.locations')) }}</a> / {{ mb_strtoupper(__('warehouse.settings.positions')) }}</div>
            <h1>{{ __('warehouse.settings.positions') }}</h1>
            <p class="muted">{{ __('warehouse.settings.positions_intro') }}</p>
        </div>
    </div>
    @include('partials.flash')

    <div class="wp-anat" aria-label="{{ __('warehouse.settings.anatomy') }}">
        @foreach (['G' => 'floor', 'A' => 'row', '1' => 'rack', 'B' => 'layer', '2' => 'spot'] as $c => $k)<span><b>{{ $c }}</b><small>{{ __('warehouse.settings.parts.'.$k) }}</small></span>@endforeach
    </div>
    <div class="tiles">
        @foreach (['G', 'M'] as $f)
            @php($t = $totals->where('floor', $f))
            <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('warehouse.floors.'.$f)) }} · {{ $f }}</span><b class="tile-v">{{ $t->sum('n') }}</b><small class="muted">{{ __('warehouse.settings.split', ['pallet' => (int) $t->firstWhere('kind', 'pallet')?->n, 'shelf' => (int) $t->firstWhere('kind', 'shelf')?->n]) }}</small></div>
        @endforeach
        <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('warehouse.settings.shelf_size')) }}</span><b class="tile-v">55 × 100 × 55</b><small class="muted">{{ __('warehouse.settings.shelf_size_s') }}</small></div>
    </div>

    <nav class="tabs">
        @foreach (['G', 'M'] as $f)<a @class(['tab', 'tab-on' => $floor === $f]) href="{{ route('locations.positions', ['floor' => $f]) }}">{{ __('warehouse.floors.'.$f) }} · {{ $f }}</a>@endforeach
    </nav>
    <nav class="chips mt">
        @foreach ($rows as $r)<a @class(['chip', 'chip-on' => $row === $r]) href="{{ route('locations.positions', ['floor' => $floor, 'row' => $r]) }}">{{ $floor }}{{ $r }}</a>@endforeach
    </nav>

    <div class="split mt">
        <section class="card">
            <div class="wp-racks">
                @foreach ($racks as $rack => $layers)
                    <div class="wp-rack">
                        <b class="wp-rack-name">{{ $floor }}{{ $row }}{{ $rack }}</b>
                        @foreach ($layers as $layer => $spots)
                            <div class="wp-layer"><span class="wp-layer-name">{{ $layer }}</span>
                                @foreach ($spots as $p)
                                    <a href="{{ route('locations.positions', ['floor' => $floor, 'row' => $row, 'p' => $p->code]) }}" title="{{ $p->code }}" @class(['wp-spot', 'wp-'.$p->kind, 'wp-occ' => ($occupied[$p->code] ?? 0) > 0, 'wp-a6' => $p->use === 'afrit6', 'wp-sales' => $p->use === 'sales', 'wp-on' => $sel?->is($p), 'wp-inactive' => ! $p->active])>{{ $p->layer }}{{ $p->spot }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="legend-row"><span><i class="wp-pallet"></i>{{ __('warehouse.settings.pallet') }}</span><span><i class="wp-shelf"></i>{{ __('warehouse.settings.shelf') }}</span><span><i class="wp-occ"></i>{{ __('warehouse.settings.occupied') }}</span><span><i class="wp-a6"></i>{{ __('warehouse.settings.uses.afrit6') }}</span><span><i class="wp-inactive"></i>{{ __('warehouse.settings.inactive') }}</span></div>
            @if ($floor === 'M')<p class="muted small">{{ __('warehouse.settings.mezzanine_note') }}</p>@endif
        </section>

        <aside class="stack">
            @if ($sel)
                <section class="card">
                    <div class="kicker">{{ __('warehouse.settings.selected') }}</div>
                    <h2><code>{{ $sel->code }}</code></h2>
                    <p class="muted">{{ __('warehouse.settings.position_desc', ['floor' => __('warehouse.floors.'.$sel->floor), 'row' => $sel->row, 'rack' => $sel->rack, 'layer' => $sel->layer, 'spot' => $sel->spot]) }}</p>
                    <div class="wp-qr">{!! \App\Support\QrSvg::make(route('w.loc', $sel->code)) !!}</div>
                    <dl class="facts">
                        <dt>{{ __('warehouse.settings.type') }}</dt><dd>{{ __('warehouse.settings.'.$sel->kind) }}</dd>
                        <dt>{{ __('warehouse.settings.size') }}</dt><dd>{{ $sel->kind === 'shelf' ? __('warehouse.settings.shelf_size_v') : __('warehouse.settings.pallet_size_v') }}</dd>
                        <dt>{{ __('warehouse.settings.now') }}</dt><dd>@if ($here->isEmpty()){{ __('warehouse.settings.free') }}@else{{ $here->map(fn ($a) => $a->code)->implode(', ') }}@endif</dd>
                        @if ($sel->use === 'afrit6')<dt>{{ __('warehouse.settings.a6_member') }}</dt><dd>@if ($holder)@if (Route::has('afrit6.staff.show') && auth()->user()->canUseModule('afrit6'))<a href="{{ route('afrit6.staff.show', $holder->member_id) }}#storage">{{ $holder->member?->companyName() }}</a>@else{{ $holder->member?->companyName() }}@endif <small class="muted">{{ __('workspace.services.since', ['date' => $holder->date->format('d/m/Y')]) }}</small>@else{{ __('warehouse.settings.a6_free') }}@endif</dd>@endif
                    </dl>
                    <form method="post" action="{{ route('locations.position.update', $sel) }}" class="inline-form">@csrf @method('PUT')
                        <select name="use" aria-label="{{ __('warehouse.settings.used_for') }}">@foreach (\App\Models\Warehouse\Position::USES as $u)<option value="{{ $u }}" @selected($sel->use === $u)>{{ __('warehouse.settings.uses.'.$u) }}</option>@endforeach</select>
                        <button class="btn" type="submit">{{ __('warehouse.settings.save') }}</button>
                    </form>
                    <div class="mt">
                        <b>{{ $sel->active ? __('warehouse.settings.is_on') : __('warehouse.settings.is_off') }}</b>
                        <div class="inline-form mt">
                            @foreach (['spot' => $sel->code, 'layer' => $sel->layerCode(), 'rack' => $sel->floor.$sel->row.$sel->rack] as $scope => $what)
                                <form method="post" action="{{ route('locations.position.active', $sel) }}">@csrf
                                    <input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="active" value="{{ $sel->active ? 0 : 1 }}">
                                    <button class="btn" type="submit">{{ __($sel->active ? 'warehouse.settings.off_'.$scope : 'warehouse.settings.on_'.$scope, ['what' => $what]) }}</button>
                                </form>
                            @endforeach
                        </div>
                        @error('active')<p class="txt-danger small">{{ $message }}</p>@enderror
                        <small class="muted">{{ __('warehouse.settings.active_hint') }}</small>
                    </div>
                    @if ($sel->floor === 'G' && ! $sel->isGroundLayer())
                        <form method="post" action="{{ route('locations.position.switch', $sel) }}" class="mt">@csrf<button class="btn" type="submit">{{ $sel->kind === 'shelf' ? __('warehouse.settings.to_pallet') : __('warehouse.settings.to_shelf') }}</button></form>
                        <small class="muted">{{ __('warehouse.settings.switch_hint') }}</small>
                    @elseif ($sel->isGroundLayer())
                        <small class="muted">{{ __('warehouse.settings.ground_hint') }}</small>
                    @endif
                </section>
                <section class="card">
                    <h2>{{ __('warehouse.settings.print') }}</h2>
                    <div class="inline-form">
                        <a class="btn" href="{{ route('locations.labels', ['p' => $sel->code]) }}" target="_blank" rel="noopener">{{ __('warehouse.settings.this_label') }}</a>
                        <a class="btn" href="{{ route('locations.labels', ['rack' => $sel->floor.$sel->row.$sel->rack]) }}" target="_blank" rel="noopener">{{ __('warehouse.settings.rack_labels', ['rack' => $sel->floor.$sel->row.$sel->rack]) }}</a>
                        <a class="btn" href="{{ route('locations.labels', ['row' => $sel->floor.$sel->row]) }}" target="_blank" rel="noopener">{{ __('warehouse.settings.row_labels', ['row' => $sel->floor.$sel->row]) }}</a>
                    </div>
                    <small class="muted">{{ __('warehouse.settings.dymo') }}</small>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
