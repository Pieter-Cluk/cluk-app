<?php

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use Illuminate\Database\Migrations\Migration;

/**
 * Pieter, 29/10/2026: the "Sol I Interconnect Cable" is in fact the 25 cm jumper cable (TrueCon + 5-pin XLR combi), and the
 * real SŌL I Interconnect (SOL1 IC, Elation 1236300102: the bracket that links SŌL I Blinders) is added at € 2.50 per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $cable = Item::where('number', 'CABADPELASOLILINK')->first();
        if ($cable && str_contains($cable->name, 'Interconnect')) {
            $old = $cable->name;
            $cable->update([
                'name' => 'Elation SŌL I jumper cable 25 cm (TrueCon + 5-pin XLR)', 'type' => 'SŌL I jumper cable 25 cm (TrueCon + 5-pin XLR)',
                'descriptions' => [
                    'en' => 'Short 25 cm combi jumper cable between SŌL I Blinders: TrueCon power and 5-pin XLR DMX in one cable.',
                    'nl' => 'Korte combi-jumperkabel van 25 cm tussen SŌL I Blinders: TrueCon-stroom en 5-polige XLR-DMX in één kabel.',
                    'fr' => 'Câble jumper combi court de 25 cm entre SŌL I Blinders : alimentation TrueCon et DMX XLR 5 broches en un câble.',
                    'es' => 'Cable jumper combinado corto de 25 cm entre SŌL I Blinders: alimentación TrueCon y DMX XLR de 5 pines en un cable.',
                ],
                'search_words' => trim(($cable->search_words ?? '').' jumper jumperkabel truecon xlr 5pin combi sol blinder'),
            ]);
            AuditLog::record('equipment.renamed', $cable, ['from' => $old, 'to' => $cable->name, 'note' => 'Data change asked by Pieter']);
        }
        if (! Item::where('number', 'LIGACCELASOLIIC')->exists()) {
            $ref = Item::where('number', 'LIGACCELASOLIOMEGA')->first();
            $item = Item::create([
                'number' => 'LIGACCELASOLIIC', 'kind' => 'item', 'brand' => 'Elation', 'type' => 'SŌL I Interconnect (SOL1 IC)',
                'name' => 'Elation SŌL I Interconnect (SOL1 IC)', 'unit' => 'pcs', 'serial' => false, 'stock_qty' => 0,
                'category' => $ref?->category, 'subcategory' => $ref?->subcategory, 'category_id' => $ref?->category_id ?? 2, 'subcategory_id' => $ref?->subcategory_id ?? 24,
                'day_price_cents' => 250, 'catalog_price_cents' => 2400,
                'descriptions' => [
                    'en' => 'Rigging bracket that links SŌL I Blinders together. Max. 40.8 kg hung vertically, 13.8 kg at an angle (incl. accessories and cables).',
                    'nl' => 'Riggingbeugel die SŌL I Blinders aan elkaar koppelt. Max. 40,8 kg verticaal gehangen, 13,8 kg onder een hoek (incl. accessoires en kabels).',
                    'fr' => 'Support de levage qui relie les SŌL I Blinders entre eux. Max. 40,8 kg suspendu verticalement, 13,8 kg en biais (accessoires et câbles compris).',
                    'es' => 'Soporte de rigging que une los SŌL I Blinders. Máx. 40,8 kg colgado en vertical, 13,8 kg en ángulo (incl. accesorios y cables).',
                ],
                'search_words' => 'sol1 ic sol i interconnect bracket koppeling blinder elation 1236300102',
                'in_search' => true, 'on_website' => true, 'active' => true,
            ]);
            AuditLog::record('equipment.created', $item, ['number' => $item->number, 'day_price' => '2.50', 'note' => 'Data change asked by Pieter']);
        }
    }

    public function down(): void {}
};
