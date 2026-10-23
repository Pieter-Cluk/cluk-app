<?php

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use Illuminate\Database\Migrations\Migration;

/**
 * Dry-hire: safety cables and the 1.5 m power cables for the fixtures. Each piece gets
 * its own QR label: labels are printed in advance and a piece only counts once its
 * label is scanned (so stock grows as pieces are found). They stay out of the quote
 * search until they have a price. Runs once; an item that is already there is left alone.
 */
return new class extends Migration
{
    // number, name, brand, type, subcategory slug
    private const ITEMS = [
        ['RIGHARSAFETYCABLE', 'Safety cable (steel wire)', null, 'Safety cable', 'rigging-hardware-and-load-cells'],
        ['POWCABTRUECONCEE', 'Power cable Truecon – CEE 3-pole 1.5 m', 'Neutrik', 'Truecon – CEE 3-pole 1.5 m', 'power-cables-and-kits'],
        ['POWCABTRUECONSCHUKO', 'Power cable Truecon – Schuko 1.5 m', 'Neutrik', 'Truecon – Schuko 1.5 m', 'power-cables-and-kits'],
        ['POWCABPOWERCONSCHUKO', 'Power cable Powercon – Schuko 1.5 m', 'Neutrik', 'Powercon – Schuko 1.5 m', 'power-cables-and-kits'],
    ];

    public function up(): void
    {
        // Live data only: the tests start from their own known items.
        if (app()->runningUnitTests() && ! app()->bound('test.sol_blinders')) {
            return;
        }
        $added = 0;
        foreach (self::ITEMS as [$number, $name, $brand, $type, $slug]) {
            if (Item::where('number', $number)->exists()) {
                continue;
            }
            $item = new Item(['number' => $number, 'kind' => 'item', 'brand' => $brand, 'type' => $type, 'name' => $name, 'unit' => 'pcs',
                'serial' => true, 'stock_qty' => 0, 'day_price_cents' => 0, 'price_unit' => 'day', 'in_search' => false, 'on_website' => false, 'active' => true,
                'search_words' => strtolower($type).' '.($slug === 'power-cables-and-kits' ? 'stroomkabel power cable' : 'safety veiligheidskabel')]);
            if ($sub = Subcategory::where('slug', $slug)->first()) {
                $item->placeIn($sub);
            }
            $item->save();
            $added++;
        }
        AuditLog::record('import.done', null, ['file' => 'Safety and power cables (pieces added as they are found)', 'equipment' => ['created' => $added, 'updated' => 0, 'skipped' => count(self::ITEMS) - $added]]);
    }

    public function down(): void {}
};
