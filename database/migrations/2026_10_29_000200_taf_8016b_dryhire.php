<?php

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Equipment\ItemPhoto;
use App\Models\ProductSales\SalesItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pieter, 29/10/2026: the TAF 8016-B clamp from sales also in dry-hire. Rigging › Clamps, counted item (one QR for the
 * type), 200 in stock, kept at GA2B3, € 1.50 per day. Photo and replacement value taken from the sales item S-101302.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || Item::where('number', 'RIGCLATAF8016B')->exists()) {
            return;
        }
        $sales = SalesItem::where('number', 'S-101302')->first();
        $names = [
            'en' => 'Black quick-trigger clamp (self-locking coupler) for 50 mm tube, max. 250 kg.',
            'nl' => 'Zwarte quick-trigger klem (zelfsluitende koppeling) voor buis van 50 mm, max. 250 kg.',
            'fr' => 'Collier quick-trigger noir (coupleur autobloquant) pour tube de 50 mm, max. 250 kg.',
            'es' => 'Abrazadera quick-trigger negra (acoplador autoblocante) para tubo de 50 mm, máx. 250 kg.',
        ];
        $item = Item::create([
            'number' => 'RIGCLATAF8016B', 'kind' => 'item', 'category' => 'Rigging', 'subcategory' => 'Clamps',
            'category_id' => 12, 'subcategory_id' => 72, 'brand' => 'TAF', 'type' => '8016-B Quicktrigger clamp black 250 kg',
            'name' => 'TAF 8016-B Quicktrigger clamp black 250 kg', 'unit' => 'pcs', 'serial' => false, 'stock_qty' => 200,
            'position_code' => 'GA2B3', 'day_price_cents' => 150, 'catalog_price_cents' => $sales?->list_price_cents,
            'descriptions' => $names, 'search_words' => 'clamp klem coupler koppeling quick trigger selflock 50mm 8016 8016-B TAF zwart black',
            'in_search' => true, 'on_website' => true, 'active' => true,
        ]);
        if ($sales?->photo_path && Storage::disk('local')->exists($sales->photo_path)) {
            $path = 'equipment/'.Str::random(32).'.'.pathinfo($sales->photo_path, PATHINFO_EXTENSION);
            Storage::disk('local')->copy($sales->photo_path, $path);
            ItemPhoto::create(['item_id' => $item->id, 'path' => $path, 'position' => 1]);
            $item->syncMainPhoto();
        }
        AuditLog::record('equipment.created', $item, ['number' => $item->number, 'stock' => 200, 'kept_at' => 'GA2B3', 'day_price' => '1.50', 'note' => 'Data change asked by Pieter (from sales S-101302)']);
    }

    public function down(): void {}
};
