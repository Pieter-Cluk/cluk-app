<?php

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use Illuminate\Database\Migrations\Migration;

/**
 * Dry-hire: the accessories for the Elation Sol I Blinder and the TAF quick clamp,
 * 120 pieces each, counted (no serial numbers). Data from HireHop (23/10/2026).
 * Runs once; an item that is already there keeps its stock.
 */
return new class extends Migration
{
    // number, name, type, description, part number, day price, replacement value, HS code, origin, subcategory slug
    private const ITEMS = [
        ['LIGACCELASOLIBARNDOOR', 'Elation Sol I Barndoor', 'Sol I Barndoor', 'SOL Barndoor (SOL BD)', '1236300099', 314, 10450, '7326909890', 'CN', 'lighting-accessories'],
        ['LIGACCELASOLIBOWENS', 'Elation Sol I Bowens Adapter', 'Sol I Bowens Adapter', 'SOL I Bowens Mount Ring Adapter', '1236300094', 86, 2850, '7326909890', 'CN', 'lighting-accessories'],
        ['LIGACCELASOLIFRESNEL', 'Elation Sol I Fresnel Lens', 'Sol I Fresnel Lens', 'SOL Fresnel Lens (SOL FL)', '1236300095', 143, 4750, '90022000', 'CN', 'lighting-accessories'],
        ['LIGACCELASOLIGELFRAME', 'Elation Sol I Gel Frame Kit', 'Sol I Gel Frame Kit', 'SOL Gel Frame Holder Kit (SOL GFHK)', '1236300100', 103, 3420, '7326909890', 'CN', 'lighting-accessories'],
        ['LIGACCELASOLIOMEGA', 'Elation Sol I Omega Bracket', 'Sol I Omega Bracket', 'Elation Sol I Omega Bracket', '9900007438', 64, 2112, null, 'CN', 'lighting-accessories'],
        ['CABADPELASOLILINK', 'Elation Sol I Interconnect Cable', 'Sol I Interconnect Cable', 'Elation Sol I 12" interconnect (patch) cable', '1236300101', 80, 2660, null, null, 'adapters-and-special-cables'],
        ['RIGTACTAFQUICKCLAMP', 'TAF Quick Selflock Clamp', 'Quick Selflock Clamp', 'Quick selflock coupler 250 kg, 50 mm tube, black', 'TAF8016B', 43, 2272, null, null, 'truss-accessories'],
    ];

    public function up(): void
    {
        // Live data only: the tests start from their own known items (SolBlinderImportTest runs this by hand).
        if (app()->runningUnitTests() && ! app()->bound('test.sol_blinders')) {
            return;
        }
        $added = [];
        foreach (self::ITEMS as [$number, $name, $type, $desc, $part, $day, $value, $hs, $origin, $slug]) {
            $item = Item::firstOrNew(['number' => $number]);
            $isNew = ! $item->exists;
            $item->fill(array_filter([
                'kind' => 'item', 'brand' => str_starts_with($name, 'TAF') ? 'TAF' : 'Elation', 'type' => $type, 'name' => $name, 'unit' => 'pcs', 'serial' => false,
                'day_price_cents' => $day, 'price_unit' => 'day', 'catalog_price_cents' => $value, 'hs_code' => $hs, 'origin' => $origin,
                'descriptions' => array_merge($item->descriptions ?? [], ['en' => $desc]),
                'search_words' => trim($part.' '.$type), 'active' => true,
            ], fn ($v) => $v !== null));
            if ($isNew) {
                $item->stock_qty = 120;
            }
            if (! $item->subcategory_id && ($sub = Subcategory::where('slug', $slug)->first())) {
                $item->placeIn($sub);
            }
            $item->save();
            if ($isNew) {
                $added[] = $number;
            }
        }
        AuditLog::record('import.done', null, ['file' => 'HireHop stock 23/10/2026 · Sol I accessories and TAF clamp (120 each)', 'equipment' => ['created' => count($added), 'updated' => count(self::ITEMS) - count($added), 'skipped' => 0]]);
    }

    public function down(): void {}
};
