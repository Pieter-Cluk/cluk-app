<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Warehouse › Labels: pick what to print. Location labels go on Dymo 2112290
 * (59 × 102 mm), item labels on Dymo 1933083 (25 × 25 mm). Every QR code has
 * the CLUK "c" in the middle.
 */
class LabelController extends Controller
{
    /**
     * Layouts for shelf and pallet places, on the same 59 × 102 mm label: "beam" is a smaller print,
     * centred both ways, for the narrow rack beams; "large" fills the label. Zone labels are always large.
     */
    public const LAYOUTS = ['beam', 'large'];

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'items' ? 'items' : 'locations';

        if ($tab === 'locations') {
            $floor = in_array($request->query('floor'), ['G', 'M'], true) ? $request->query('floor') : null;
            $row = preg_match('/^[A-Z]$/', (string) $request->query('row')) ? $request->query('row') : null;
            $positions = Position::where('active', true)
                ->when($floor, fn ($q) => $q->where('floor', $floor))
                ->when($row, fn ($q) => $q->where('row', $row))
                ->orderBy('code')->limit(800)->get();

            return view('warehouse.labels', [
                'tab' => $tab, 'floor' => $floor, 'row' => $row,
                'zones' => $floor || $row ? collect() : Zone::where('active', true)->where('type', '!=', 'storage')->orderBy('sort')->get(),
                'positions' => $positions,
                'rows' => Position::where('active', true)->when($floor, fn ($q) => $q->where('floor', $floor))->distinct()->orderBy('row')->pluck('row'),
            ]);
        }

        $q = trim((string) $request->query('q', ''));
        $new = $request->boolean('new');
        // Every word must match somewhere: the piece's code or serial number, or the item's name, number, brand, type or search words.
        $words = array_values(array_filter(preg_split('/\s+/', $q)));
        $itemMatch = fn ($i, string $w) => $i->where(fn ($i) => $i->where('name', 'like', "%{$w}%")->orWhere('number', 'like', "%{$w}%")
            ->orWhere('brand', 'like', "%{$w}%")->orWhere('type', 'like', "%{$w}%")->orWhere('search_words', 'like', "%{$w}%"));
        $assets = Asset::with('item:id,name,number')
            ->where('status', '!=', Asset::RETIRED)
            ->when($new, fn ($w) => $w->whereNull('label_printed_at'))
            ->when($words, function ($w) use ($words, $itemMatch) {
                foreach ($words as $word) {
                    $w->where(fn ($w) => $w->where('code', 'like', "%{$word}%")->orWhere('serial_number', 'like', "%{$word}%")
                        ->orWhereHas('item', fn ($i) => $itemMatch($i, $word)));
                }
            })
            ->orderBy('code')->limit(300)->get();
        // Counted items (no serial numbers): one label per piece with the item's QR code, as many as you want.
        $counted = $words && ! $new ? Item::where('active', true)->where('serial', false)->where('kind', 'item')
            ->where(function ($w) use ($words, $itemMatch) {
                foreach ($words as $word) {
                    $itemMatch($w, $word);
                }
            })->orderBy('name')->limit(50)->get() : collect();

        return view('warehouse.labels', ['tab' => $tab, 'q' => $q, 'new' => $new, 'assets' => $assets, 'counted' => $counted]);
    }

    /** A4 sheet with big QR codes for the pallet trucks / reach trucks, or for the packing zone (PDF). */
    public function sheet(string $sheet): \Symfony\Component\HttpFoundation\Response
    {
        return response(\App\Services\Warehouse\QrSheets::pdf($sheet), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cluk-qr-'.$sheet.'-'.now()->format('Ymd').'.pdf"']);
    }

    public function locations(Request $request): View
    {
        $d = $request->validate(['codes' => ['required', 'array', 'max:800'], 'codes.*' => ['string', 'max:20'], 'layout' => ['nullable', 'in:'.implode(',', self::LAYOUTS)]]);

        return view('warehouse.settings.labels', ['labels' => self::locationLabels($d['codes']), 'layout' => $d['layout'] ?? 'beam', 'back' => route('warehouse.labels')]);
    }

    public function items(Request $request): View
    {
        if ($request->filled('bulk')) {
            $d = $request->validate(['bulk' => ['required', 'integer'], 'qty' => ['required', 'integer', 'min:1', 'max:500']]);

            return view('equipment.labels', ['assets' => collect(), 'bulk' => ['item' => Item::where('serial', false)->findOrFail($d['bulk']), 'qty' => (int) $d['qty']]]);
        }
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']])['ids'];
        $assets = Asset::with('item')->whereIn('id', $ids)->where('status', '!=', Asset::RETIRED)->orderBy('code')->get();
        abort_if($assets->isEmpty(), 404);
        Asset::whereIn('id', $assets->pluck('id'))->update(['label_printed_at' => now()]);

        return view('equipment.labels', ['assets' => $assets]);
    }

    /** @return Collection<int, array{code: string, name: string, zone: bool}> */
    public static function locationLabels(array $codes): Collection
    {
        $codes = array_values(array_unique(array_map(fn ($c) => strtoupper((string) $c), $codes)));
        $zones = Zone::whereIn('code', $codes)->get()->keyBy('code');
        $positions = Position::whereIn('code', $codes)->get()->keyBy('code');
        $labels = collect($codes)->map(function (string $c) use ($zones, $positions) {
            if ($z = $zones->get($c)) {
                return ['code' => $z->code, 'name' => $z->label(), 'zone' => true];
            }
            if ($p = $positions->get($c)) {
                return ['code' => $p->code, 'name' => self::positionName($p), 'zone' => false];
            }

            return null;
        })->filter()->values();
        abort_if($labels->isEmpty(), 404);

        return $labels;
    }

    public static function positionName(Position $p): string
    {
        return __('warehouse.settings.position_desc', ['floor' => __('warehouse.floors.'.$p->floor), 'row' => $p->row, 'rack' => $p->rack, 'layer' => $p->layer, 'spot' => $p->spot]);
    }
}
