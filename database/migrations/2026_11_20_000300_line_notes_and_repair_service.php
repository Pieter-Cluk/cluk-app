<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extra information per quote line (and the order line it becomes), shown to the customer,
 * and the service "Repair": no fixed price, it is filled in on each quote (Pieter, 20/11/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', fn (Blueprint $t) => $t->text('note')->nullable()->after('name'));
        Schema::table('order_lines', fn (Blueprint $t) => $t->text('note')->nullable()->after('name'));

        if (app()->runningUnitTests() || DB::table('equipment_items')->where('number', 'WH-REPAIR')->exists()) {
            return;
        }
        $now = now();
        DB::table('equipment_items')->insert([
            'number' => 'WH-REPAIR', 'kind' => 'service', 'category' => 'Warehouse', 'subcategory' => 'Repair', 'name' => 'Repair',
            'unit' => 'pcs', 'serial' => false, 'stock_qty' => 0, 'day_price_cents' => 0, 'price_unit' => 'event',
            'descriptions' => json_encode([
                'en' => 'Repair of equipment. Price depending on the work, filled in on the quote.',
                'nl' => 'Herstelling van materiaal. Prijs afhankelijk van het werk, ingevuld op de offerte.',
                'fr' => 'Réparation de matériel. Prix selon le travail, indiqué sur le devis.',
                'es' => 'Reparación de equipos. Precio según el trabajo, indicado en el presupuesto.',
            ], JSON_UNESCAPED_UNICODE),
            'in_search' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('quote_lines', fn (Blueprint $t) => $t->dropColumn('note'));
        Schema::table('order_lines', fn (Blueprint $t) => $t->dropColumn('note'));
    }
};
