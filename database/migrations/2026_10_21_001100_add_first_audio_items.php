<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * First dry-hire items, given by Pieter on 21 Oct 2026 to test sub-hire.
 * Item number: category (AUD) + subcategory (SP / ACC) + first 3 letters
 * of the brand + first 6 characters of the type. No pieces or stock yet:
 * those are added in the app.
 */
return new class extends Migration
{
    private function items(): array
    {
        return [
            ['AUDSPEQSCKLA181', 'QSC KLA181 subwoofer', 'Speaker', 'QSC', 'KLA-181', true, 3000, 47.2, 0.594, 0.653, 0.547, [
                'en' => "The QSC KLA181 is an active 18-inch subwoofer featuring a 1,000 W amplifier and integrated DSP.\nIts rugged birch plywood enclosure supports both flown and ground-stacked configurations for professional sound reinforcement.",
                'nl' => "De QSC KLA181 is een actieve 18-inch subwoofer met een versterker van 1.000 W en ingebouwde DSP.\nDe stevige behuizing in berkenmultiplex kan zowel gevlogen als gestapeld op de grond worden opgesteld voor professionele geluidsversterking.",
                'fr' => "Le QSC KLA181 est un caisson de basses actif de 18 pouces avec un amplificateur de 1 000 W et un DSP intégré.\nSon coffret robuste en contreplaqué de bouleau permet une installation suspendue ou posée au sol pour la sonorisation professionnelle.",
                'es' => "El QSC KLA181 es un subwoofer activo de 18 pulgadas con un amplificador de 1.000 W y DSP integrado.\nSu robusta caja de contrachapado de abedul admite configuraciones voladas o apiladas en el suelo para refuerzo sonoro profesional.",
            ]],
            ['AUDSPEQSCKLA12', 'QSC KLA12 line array', 'Speaker', 'QSC', 'KLA-12', true, 3000, 25, 0.594, 0.422, 0.381, [
                'en' => "The QSC KLA12 is an active 12-inch, two-way line array loudspeaker with a 1,000 W amplifier and integrated DSP.\nFeaturing 90° × 18° coverage, it supports flown and ground-stacked configurations for professional sound reinforcement.",
                'nl' => "De QSC KLA12 is een actieve 12-inch tweeweg line-array-luidspreker met een versterker van 1.000 W en ingebouwde DSP.\nMet een spreiding van 90° × 18° kan hij gevlogen of gestapeld op de grond worden opgesteld voor professionele geluidsversterking.",
                'fr' => "Le QSC KLA12 est une enceinte line array active 12 pouces à deux voies avec un amplificateur de 1 000 W et un DSP intégré.\nAvec une couverture de 90° × 18°, elle s’installe suspendue ou posée au sol pour la sonorisation professionnelle.",
                'es' => "El QSC KLA12 es un altavoz line array activo de 12 pulgadas y dos vías con un amplificador de 1.000 W y DSP integrado.\nCon una cobertura de 90° × 18°, admite configuraciones voladas o apiladas en el suelo para refuerzo sonoro profesional.",
            ]],
            ['AUDACCK&MM20POLE', 'Speaker pole M20', 'Accessories', 'K&M', null, false, 500, 6, 0.05, 0.05, 1.0, [
                'en' => 'Speaker pole with M20 thread bolt.',
                'nl' => 'Luidsprekerpaal met M20-draadbout.',
                'fr' => 'Mât d’enceinte avec boulon fileté M20.',
                'es' => 'Barra para altavoz con perno roscado M20.',
            ]],
            ['AUDACCK&MSTAND1', 'Tripod speaker stand', 'Accessories', 'K&M', null, false, 500, 4, 0.2, 0.2, 1.2, [
                'en' => 'Tripod speaker stand.',
                'nl' => 'Driepoot-luidsprekerstatief.',
                'fr' => 'Pied d’enceinte trépied.',
                'es' => 'Soporte trípode para altavoz.',
            ]],
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->items() as [$number, $name, $sub, $brand, $type, $serial, $price, $kg, $w, $l, $h, $desc]) {
            if (DB::table('equipment_items')->where('number', $number)->exists()) {
                continue;
            }
            DB::table('equipment_items')->insert([
                'number' => $number, 'kind' => 'item', 'category' => 'Audio', 'subcategory' => $sub, 'brand' => $brand, 'type' => $type,
                'name' => $name, 'unit' => 'pcs', 'serial' => $serial, 'stock_qty' => 0, 'day_price_cents' => $price, 'price_unit' => 'day',
                'weight_kg' => $kg, 'width_m' => $w, 'length_m' => $l, 'height_m' => $h, 'descriptions' => json_encode($desc, JSON_UNESCAPED_UNICODE),
                'in_search' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('equipment_items')->whereIn('number', array_column($this->items(), 0))->delete();
    }
};
