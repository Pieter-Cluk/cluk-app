<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Four more warehouse services (Pieter, 20/11/2026). Prices in cents excl. VAT.
 * Skipped when the number already exists, so it never overwrites a change made in the app.
 */
return new class extends Migration
{
    private const ITEMS = [
        // number, subgroup, name, price cents, unit, descriptions
        ['WH-CHECK-CLEAN', 'Cleaning', 'Check & Clean', 1300, 'piece', [
            'en' => 'Standard technical check and clean of the item.',
            'nl' => 'Standaard technische controle en reiniging van het item.',
            'fr' => 'Contrôle technique standard et nettoyage de l’article.',
            'es' => 'Control técnico estándar y limpieza del artículo.',
        ]],
        ['WH-UNLOAD-PALLET', 'Handling', 'Unloading truck', 200, 'pallet', [
            'en' => 'Manual task with use of in-house equipment. Price per pallet.',
            'nl' => 'Manuele taak met gebruik van eigen materiaal. Prijs per pallet.',
            'fr' => 'Tâche manuelle avec utilisation du matériel interne. Prix par palette.',
            'es' => 'Tarea manual con uso de equipo propio. Precio por palé.',
        ]],
        ['WH-UNPACK-PALLET', 'Handling', 'Unpacking pallet', 300, 'pallet', [
            'en' => 'Manual task unpacking and unloading a pallet. Waste is sorted in the recycling containers. Price per pallet.',
            'nl' => 'Manuele taak: een pallet uitpakken en leegmaken. Het afval wordt gesorteerd in de recyclagecontainers. Prijs per pallet.',
            'fr' => 'Tâche manuelle : déballer et décharger une palette. Les déchets sont triés dans les conteneurs de recyclage. Prix par palette.',
            'es' => 'Tarea manual: desembalar y descargar un palé. Los residuos se separan en los contenedores de reciclaje. Precio por palé.',
        ]],
        ['WH-INTERNAL-COLI', 'Handling', 'Internal handling', 50, 'coli', [
            'en' => 'Moving items from one place to another in the warehouse. Price per packing unit.',
            'nl' => 'Items verplaatsen van de ene plaats naar een andere in het magazijn. Prijs per verpakkingseenheid.',
            'fr' => 'Déplacer des articles d’un endroit à un autre dans l’entrepôt. Prix par unité d’emballage.',
            'es' => 'Mover artículos de un lugar a otro del almacén. Precio por unidad de embalaje.',
        ]],
    ];

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $now = now();
        foreach (self::ITEMS as [$number, $sub, $name, $price, $unit, $desc]) {
            if (DB::table('equipment_items')->where('number', $number)->exists()) {
                continue;
            }
            DB::table('equipment_items')->insert([
                'number' => $number, 'kind' => 'service', 'category' => 'Warehouse', 'subcategory' => $sub, 'name' => $name,
                'unit' => 'pcs', 'serial' => false, 'stock_qty' => 0, 'day_price_cents' => $price, 'price_unit' => $unit,
                'descriptions' => json_encode($desc, JSON_UNESCAPED_UNICODE),
                'in_search' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Left in place: the items may already be on quotes.
    }
};
