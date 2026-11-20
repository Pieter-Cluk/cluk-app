<?php

use App\Models\Sales\Quote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 1. "Loading truck", the same price as "Unloading truck" (Pieter, 20/11/2026).
 * 2. Services are net prices: line discounts on services in draft quotes are removed
 *    and those quotes recalculated. Sent and accepted quotes are left as they were sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        if (! DB::table('equipment_items')->where('number', 'WH-LOAD-PALLET')->exists()) {
            $now = now();
            DB::table('equipment_items')->insert([
                'number' => 'WH-LOAD-PALLET', 'kind' => 'service', 'category' => 'Warehouse', 'subcategory' => 'Handling', 'name' => 'Loading truck',
                'unit' => 'pcs', 'serial' => false, 'stock_qty' => 0,
                'day_price_cents' => (int) (DB::table('equipment_items')->where('number', 'WH-UNLOAD-PALLET')->value('day_price_cents') ?? 200), 'price_unit' => 'pallet',
                'descriptions' => json_encode([
                    'en' => 'Manual task with use of in-house equipment. Price per pallet.',
                    'nl' => 'Manuele taak met gebruik van eigen materiaal. Prijs per pallet.',
                    'fr' => 'Tâche manuelle avec utilisation du matériel interne. Prix par palette.',
                    'es' => 'Tarea manual con uso de equipo propio. Precio por palé.',
                ], JSON_UNESCAPED_UNICODE),
                'in_search' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $services = DB::table('equipment_items')->where('kind', 'service')->pluck('id');
        $quoteIds = DB::table('quote_lines')->join('quotes', 'quotes.id', '=', 'quote_lines.quote_id')
            ->where('quotes.status', 'draft')->whereIn('quote_lines.item_id', $services)
            ->where(fn ($q) => $q->where('quote_lines.discount_bp', '>', 0)->orWhere('quotes.project_discount_bp', '>', 0))
            ->distinct()->pluck('quote_lines.quote_id');
        foreach (Quote::whereIn('id', $quoteIds)->get() as $quote) {
            $quote->recalculate(); // saving a service line clears its discount
        }
    }

    public function down(): void
    {
    }
};
