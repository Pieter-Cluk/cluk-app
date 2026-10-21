<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The fixed-price services that are already known: Afrit 6 co-working,
 * warehouse services and Cluk Factory (brochure, "starting from").
 * Prices in cents excl. VAT. Rental equipment, companies and contacts are
 * NOT loaded here: Pieter imports those himself.
 */
return new class extends Migration
{
    private const ITEMS = [
        // number, category, subcategory, name, price cents, unit, en, nl
        ['A6-DESK-MONTH', 'Afrit 6', 'Desks', 'Fixed desk', 35000, 'month', 'Fixed desk per contract month: 2 screens, keyboard, mouse, fast internet.', 'Vast bureau per contractmaand: 2 schermen, toetsenbord, muis, snel internet.'],
        ['A6-DESK-HALF', 'Afrit 6', 'Desks', 'Desk half day', 3000, 'half_day', 'Free desk for a morning (08:00–13:00) or an afternoon (13:00–18:00).', 'Vrij bureau voor een voormiddag (08:00–13:00) of namiddag (13:00–18:00).'],
        ['A6-DESK-DAY', 'Afrit 6', 'Desks', 'Desk day', 5000, 'day', 'Free desk for a whole day (08:00–18:00).', 'Vrij bureau voor een hele dag (08:00–18:00).'],
        ['A6-ROOM-HOUR', 'Afrit 6', 'Meeting room', 'Meeting room per hour', 1250, 'hour', 'Meeting room beyond the free weekly slot, billed per half hour.', 'Vergaderzaal buiten het gratis weekmoment, aangerekend per half uur.'],
        ['A6-OFFICE', 'Afrit 6', 'Private office', 'Private office upstairs', 0, 'month', 'Private office, long term. Price on request, depending on the equipment.', 'Privékantoor, lange termijn. Prijs op aanvraag, afhankelijk van de uitrusting.'],
        ['WH-SHELF-DAY', 'Warehouse', 'Storage', 'Shelf per day', 50, 'day', 'Shelf 55 cm wide, 100 cm deep, 55 cm high, per day.', 'Rek 55 cm breed, 100 cm diep, 55 cm hoog, per dag.'],
        ['WH-PALLET-DAY', 'Warehouse', 'Storage', 'Pallet place per day', 100, 'day', 'Pallet place, per day.', 'Palletplaats, per dag.'],
        ['WH-SHELF-24H', 'Warehouse', 'Storage', '24 h pick-up shelf', 0, 'coli', 'Goods on the 24 h pick-up shelf: free when collected in time.', 'Goederen op het 24 u-afhaalrek: gratis als ze tijdig opgehaald worden.'],
        ['WH-PACK-COLI', 'Warehouse', 'Packing', 'Packing per coli', 160, 'coli', 'Packing labour per coli (3–5 min).', 'Inpakwerk per collo (3–5 min).'],
        ['WH-PACK-PALLET', 'Warehouse', 'Packing', 'Packing per pallet', 1500, 'pallet', 'Packing labour per pallet (15 min).', 'Inpakwerk per pallet (15 min).'],
        ['WH-HANDLING-COLI', 'Warehouse', 'Handling', 'Handling per coli', 200, 'coli', 'Receiving into storage or sending goods, per coli.', 'Ontvangen in opslag of versturen van goederen, per collo.'],
        ['WH-HANDLING-PALLET', 'Warehouse', 'Handling', 'Handling per pallet', 800, 'pallet', 'Receiving into storage or sending goods, per pallet.', 'Ontvangen in opslag of versturen van goederen, per pallet.'],
        ['WH-CLEAN-2', 'Warehouse', 'Cleaning', 'Cleaning stage 2', 1500, 'event', 'Cleaning after return: more than normal use.', 'Reiniging na retour: meer dan normaal gebruik.'],
        ['WH-CLEAN-3', 'Warehouse', 'Cleaning', 'Cleaning stage 3', 3000, 'event', 'Cleaning after return: very dirty.', 'Reiniging na retour: zeer vuil.'],
        ['CF-XS', 'Cluk Factory', 'Venue size', 'Cluk Factory XS', 100000, 'event_day', 'XS: 120 m² event floor + 54 m² entrance. Basic level included. Starting price per event day.', 'XS: 120 m² eventvloer + 54 m² inkom. Basic inbegrepen. Vanafprijs per eventdag.'],
        ['CF-S', 'Cluk Factory', 'Venue size', 'Cluk Factory S', 150000, 'event_day', 'S: 180 m² event floor + 81 m² entrance. Basic level included. Starting price per event day.', 'S: 180 m² eventvloer + 81 m² inkom. Basic inbegrepen. Vanafprijs per eventdag.'],
        ['CF-M', 'Cluk Factory', 'Venue size', 'Cluk Factory M', 200000, 'event_day', 'M: 240 m² event floor + 108 m² entrance. Basic level included. Starting price per event day.', 'M: 240 m² eventvloer + 108 m² inkom. Basic inbegrepen. Vanafprijs per eventdag.'],
        ['CF-L', 'Cluk Factory', 'Venue size', 'Cluk Factory L', 300000, 'event_day', 'L: 300 m² event floor + 135 m² entrance. Basic level included. Starting price per event day.', 'L: 300 m² eventvloer + 135 m² inkom. Basic inbegrepen. Vanafprijs per eventdag.'],
        ['CF-XL', 'Cluk Factory', 'Venue size', 'Cluk Factory XL', 400000, 'event_day', 'XL: 360 m² event floor + 162 m² entrance. Basic level included. Starting price per event day.', 'XL: 360 m² eventvloer + 162 m² inkom. Basic inbegrepen. Vanafprijs per eventdag.'],
        ['CF-XXL', 'Cluk Factory', 'Venue size', 'Cluk Factory XXL', 500000, 'event_day', 'XXL: 600 m² event floor + 54 m² entrance. Basic level included. Starting price per event day.', 'XXL: 600 m² eventvloer + 54 m² inkom. Basic inbegrepen. Vanafprijs per eventdag.'],
        ['CF-ADVANCED', 'Cluk Factory', 'Level', 'Cluk Factory Advanced', 200000, 'event', 'Advanced: stage, professional audio, stage lighting, LED backdrop, technical support. Starting price.', 'Advanced: podium, professionele audio, podiumverlichting, LED-backdrop, technische ondersteuning. Vanafprijs.'],
        ['CF-ALLIN', 'Cluk Factory', 'Level', 'Cluk Factory All-in', 0, 'event', 'All-in: hospitality, bar, furniture, catering support, full customisation. Custom quotation.', 'All-in: hospitality, bar, meubilair, catering-ondersteuning, volledig op maat. Offerte op maat.'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::ITEMS as [$number, $cat, $sub, $name, $price, $unit, $en, $nl]) {
            if (DB::table('equipment_items')->where('number', $number)->exists()) {
                continue;
            }
            DB::table('equipment_items')->insert([
                'number' => $number, 'kind' => 'service', 'category' => $cat, 'subcategory' => $sub, 'name' => $name,
                'unit' => 'pcs', 'serial' => false, 'stock_qty' => 0, 'day_price_cents' => $price, 'price_unit' => $unit,
                'descriptions' => json_encode(['en' => $en, 'nl' => $nl], JSON_UNESCAPED_UNICODE),
                'in_search' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('equipment_items')->whereIn('number', array_column(self::ITEMS, 0))->delete();
    }
};
