<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Counted items (cables, softsteels, clamps…) can lie on several rack places, each with its own count
 * (e.g. 10 of the 20 on GA2C1). What is in no place and in no case is "not placed yet".
 * Existing items: everything loose goes on the place where they were kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_item_places', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained('equipment_items')->cascadeOnDelete();
            $t->string('position_code', 30);
            $t->unsignedInteger('qty')->default(0);
            $t->timestamps();
            $t->unique(['item_id', 'position_code']);
        });
        $inCases = DB::table('equipment_case_items')->groupBy('item_id')->selectRaw('item_id, sum(qty) as n')->pluck('n', 'item_id');
        foreach (DB::table('equipment_items')->where('serial', false)->whereNotNull('position_code')->get(['id', 'stock_qty', 'position_code']) as $i) {
            $loose = max(0, (int) $i->stock_qty - (int) ($inCases[$i->id] ?? 0));
            if ($loose > 0) {
                DB::table('equipment_item_places')->insert(['item_id' => $i->id, 'position_code' => $i->position_code, 'qty' => $loose, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_item_places');
    }
};
