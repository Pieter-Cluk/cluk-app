<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Light dollies and storage boxes become dry-hire items of the kind "case" (never on
 * quotes, no price), with DOLLY-01… and BOX-01… as their pieces. Every piece of a case
 * item can hold other pieces (equipment_assets.case_id). Replaces warehouse_carriers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_assets', function (Blueprint $t) {
            $t->foreignId('case_id')->nullable()->constrained('equipment_assets')->nullOnDelete();
        });
        Schema::table('warehouse_moves', function (Blueprint $t) {
            $t->foreignId('case_id')->nullable()->constrained('equipment_assets')->nullOnDelete();
        });

        // Live data only: the tests start from their own known items (WarehouseCarriersTest runs seed() by hand).
        $seed = ! app()->runningUnitTests();
        $map = $seed ? $this->seed() : [];
        if (Schema::hasTable('warehouse_carriers')) {
            foreach ($map as $old => $new) {
                DB::table('equipment_assets')->where('carrier_id', $old)->update(['case_id' => $new]);
                DB::table('warehouse_moves')->where('carrier_id', $old)->update(['case_id' => $new]);
            }
            Schema::table('warehouse_moves', fn (Blueprint $t) => $t->dropConstrainedForeignId('carrier_id'));
            Schema::table('equipment_assets', fn (Blueprint $t) => $t->dropConstrainedForeignId('carrier_id'));
            Schema::dropIfExists('warehouse_carriers');
        }
    }

    /**
     * The two case items and their pieces: from the dollies and boxes made earlier today
     * (old id => new piece id), or DOLLY-01…06 and BOX-01…22 when there are none.
     */
    public function seed(): array
    {
        $now = now();
        $items = [];
        foreach (['dolly' => ['CASLIGHTDOLLY', 'Light dolly (meat rack)', 'Light dolly'], 'box' => ['CASSTORAGEBOX', 'Storage box', 'Storage box']] as $kind => [$number, $name, $type]) {
            $items[$kind] = DB::table('equipment_items')->where('number', $number)->value('id') ?? DB::table('equipment_items')->insertGetId([
                'number' => $number, 'kind' => 'case', 'brand' => 'CLUK', 'type' => $type, 'name' => $name, 'unit' => 'pcs', 'serial' => true,
                'day_price_cents' => 0, 'price_unit' => 'day', 'in_search' => false, 'on_website' => false, 'active' => true,
                'descriptions' => json_encode(['en' => 'Goes with the equipment at no charge; comes back with it.']),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $map = [];
        $old = Schema::hasTable('warehouse_carriers') ? DB::table('warehouse_carriers')->orderBy('id')->get() : collect();
        if ($old->isEmpty()) {
            $old = collect(range(1, 6))->map(fn ($i) => (object) ['id' => 'd'.$i, 'code' => sprintf('DOLLY-%02d', $i), 'kind' => 'dolly', 'location' => null, 'out_order_id' => null])
                ->concat(collect(range(1, 22))->map(fn ($i) => (object) ['id' => 'b'.$i, 'code' => sprintf('BOX-%02d', $i), 'kind' => 'box', 'location' => null, 'out_order_id' => null]));
        }
        foreach ($old as $c) {
            if (DB::table('equipment_assets')->where('code', $c->code)->exists()) {
                continue;
            }
            $map[$c->id] = DB::table('equipment_assets')->insertGetId([
                'item_id' => $items[$c->kind] ?? $items['box'], 'code' => $c->code, 'status' => $c->location === 'OUT' ? 'out' : 'available',
                'location' => $c->location, 'out_order_id' => $c->out_order_id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return $map;
    }

    public function down(): void {}
};
