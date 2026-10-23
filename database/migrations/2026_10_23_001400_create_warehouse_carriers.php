<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Light dollies (meat racks) and storage boxes: own QR label, pieces go in them,
 * they move as one and travel with an order at no charge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_carriers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('kind', 8);                      // dolly | box
            $t->string('name', 80)->nullable();
            $t->string('location', 60)->nullable();     // like a piece: zone, position, DISPATCH or OUT
            $t->foreignId('out_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::table('equipment_assets', function (Blueprint $t) {
            $t->foreignId('carrier_id')->nullable()->constrained('warehouse_carriers')->nullOnDelete();
        });
        Schema::table('warehouse_moves', function (Blueprint $t) {
            $t->foreignId('carrier_id')->nullable()->constrained('warehouse_carriers')->nullOnDelete();
        });

        $now = now();
        $rows = [];
        foreach (range(1, 6) as $i) {
            $rows[] = ['code' => sprintf('DOLLY-%02d', $i), 'kind' => 'dolly', 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (range(1, 22) as $i) {
            $rows[] = ['code' => sprintf('BOX-%02d', $i), 'kind' => 'box', 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('warehouse_carriers')->insert($rows);
    }

    public function down(): void
    {
        Schema::table('warehouse_moves', fn (Blueprint $t) => $t->dropConstrainedForeignId('carrier_id'));
        Schema::table('equipment_assets', fn (Blueprint $t) => $t->dropConstrainedForeignId('carrier_id'));
        Schema::dropIfExists('warehouse_carriers');
    }
};
