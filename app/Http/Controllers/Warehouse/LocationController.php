<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings › Warehouse locations: the zones and their route, the storage positions and their QR labels. */
class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $zones = Zone::orderBy('sort')->get();
        $sel = $request->query('zone') === 'new' ? new Zone(['type' => 'storage', 'next' => [], 'active' => true]) : $zones->firstWhere('code', strtoupper((string) $request->query('zone')));

        return view('warehouse.settings.zones', [
            'zones' => $zones, 'sel' => $sel,
            'counts' => Asset::whereNotNull('location')->selectRaw('location, count(*) as n')->groupBy('location')->pluck('n', 'location'),
            'positions' => Position::where('active', true)->count(),
        ]);
    }

    private function rules(?Zone $zone): array
    {
        return [
            'code' => [$zone ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('warehouse_zones', 'code')->ignore($zone?->id),
                Rule::notIn(['OUT', 'RACKS'])],
            'name' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in(Zone::TYPES)],
            'categories' => ['nullable', 'string', 'max:200'],
            'next' => ['array'], 'next.*' => ['string', 'max:30'],
            'note' => ['nullable', 'string', 'max:200'],
            'active' => ['boolean'],
        ];
    }

    private function fill(Zone $zone, array $d): Zone
    {
        $cats = collect(explode(',', (string) ($d['categories'] ?? '')))->map(fn ($c) => trim($c))->filter()->values()->all();
        $zone->fill(['name' => $d['name'] ?? null, 'type' => $d['type'], 'categories' => $cats ?: null, 'next' => array_values(array_diff($d['next'] ?? [], [$zone->code])),
            'note' => $d['note'] ?? null, 'active' => (bool) ($d['active'] ?? false)]);

        return $zone;
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate($this->rules(null));
        $zone = $this->fill(new Zone(['code' => strtoupper($d['code']), 'sort' => (int) Zone::max('sort') + 10]), $d);
        $zone->save();
        AuditLog::record('warehouse.zone_saved', $zone, ['code' => $zone->code], $request->user());

        return redirect()->route('locations.index', ['zone' => $zone->code])->with('status', __('warehouse.settings.saved'));
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $d = $request->validate($this->rules($zone));
        $this->fill($zone, $d)->save();
        AuditLog::record('warehouse.zone_saved', $zone, ['code' => $zone->code], $request->user());

        return redirect()->route('locations.index', ['zone' => $zone->code])->with('status', __('warehouse.settings.saved'));
    }

    // ------------------------------------------------------------------ positions

    public function positions(Request $request): View
    {
        $floor = $request->query('floor') === 'M' ? 'M' : 'G';
        $rows = Position::where('floor', $floor)->distinct()->orderBy('row')->pluck('row');
        $row = $rows->contains($request->query('row')) ? $request->query('row') : $rows->first();
        $list = Position::where('floor', $floor)->where('row', $row)->orderBy('rack')->orderByDesc('layer')->orderBy('spot')->get();
        $codes = $list->pluck('code');
        $sel = Position::byCode((string) $request->query('p')) ?? $list->first();

        return view('warehouse.settings.positions', [
            'floor' => $floor, 'rows' => $rows, 'row' => $row, 'racks' => $list->groupBy('rack')->map(fn ($r) => $r->groupBy('layer')),
            'occupied' => Asset::whereIn('location', $codes)->selectRaw('location, count(*) as n')->groupBy('location')->pluck('n', 'location'),
            'sel' => $sel, 'here' => $sel ? Asset::with('item')->where('location', $sel->code)->orderBy('code')->get() : collect(),
            'totals' => Position::where('active', true)->selectRaw('floor, kind, count(*) as n')->groupBy('floor', 'kind')->get(),
        ]);
    }

    public function updatePosition(Request $request, Position $position): RedirectResponse
    {
        $d = $request->validate(['use' => ['required', Rule::in(Position::USES)]]);
        $position->update($d);

        return redirect()->route('locations.positions', ['floor' => $position->floor, 'row' => $position->row, 'p' => $position->code])->with('status', __('warehouse.settings.saved'));
    }

    /** Switch a layer between 3 shelf places and 2 pallet places, only when it is empty. */
    public function switchLayer(Request $request, Position $position): RedirectResponse
    {
        abort_if($position->isGroundLayer() || $position->floor !== 'G', 409);
        $layer = Position::where('floor', $position->floor)->where('row', $position->row)->where('rack', $position->rack)->where('layer', $position->layer)->get();
        if (Asset::whereIn('location', $layer->pluck('code'))->exists()) {
            return back()->withErrors(['layer' => __('warehouse.settings.layer_not_empty')]);
        }
        $toPallet = $position->kind === 'shelf';
        DB::transaction(function () use ($layer, $position, $toPallet) {
            Position::whereIn('id', $layer->pluck('id'))->update(['kind' => $toPallet ? 'pallet' : 'shelf']);
            if ($toPallet) {
                Position::whereIn('id', $layer->where('spot', '>', 2)->pluck('id'))->delete();
            } elseif (! $layer->contains('spot', 3)) {
                Position::create(['code' => $position->layerCode().'3', 'floor' => $position->floor, 'row' => $position->row, 'rack' => $position->rack,
                    'layer' => $position->layer, 'spot' => 3, 'kind' => 'shelf', 'use' => $position->use]);
            }
        });
        AuditLog::record('warehouse.layer_switched', $position, ['layer' => $position->layerCode(), 'to' => $toPallet ? 'pallet' : 'shelf'], $request->user());

        return redirect()->route('locations.positions', ['floor' => $position->floor, 'row' => $position->row, 'p' => $position->layerCode().'1'])->with('status', __('warehouse.settings.saved'));
    }

    /**
     * Location labels on Dymo 11354 (57 × 32 mm): ?zone=CLEAN, ?p=GA1A1, ?rack=GA1, ?row=GA.
     */
    public function labels(Request $request): View
    {
        $labels = collect();
        if ($z = Zone::byCode((string) $request->query('zone'))) {
            $labels->push(['code' => $z->code, 'name' => $z->label()]);
        }
        $q = Position::where('active', true);
        $prefix = strtoupper((string) ($request->query('p') ?? $request->query('rack') ?? $request->query('row') ?? ''));
        if ($prefix !== '' && preg_match('/^[GM][A-Z]([0-9]([A-Z]([0-9])?)?)?$/', $prefix)) {
            foreach ($q->where('code', 'like', $prefix.'%')->orderBy('code')->get() as $p) {
                $labels->push(['code' => $p->code, 'name' => __('warehouse.settings.position_desc', ['floor' => __('warehouse.floors.'.$p->floor), 'row' => $p->row, 'rack' => $p->rack, 'layer' => $p->layer, 'spot' => $p->spot])]);
            }
        }
        abort_if($labels->isEmpty(), 404);

        return view('warehouse.settings.labels', ['labels' => $labels]);
    }
}
