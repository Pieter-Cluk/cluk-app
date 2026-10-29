<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Pieter, 29/10/2026: a place "Factory" for equipment hung in the Cluk Factory venue (scan a piece or case, then FACTORY). */
    public function up(): void
    {
        if (! DB::table('warehouse_zones')->where('code', 'FACTORY')->exists()) {
            DB::table('warehouse_zones')->insert(['code' => 'FACTORY', 'type' => 'venue', 'categories' => null, 'next' => json_encode([]), 'sort' => 210,
                'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('warehouse_zones')->where('code', 'FACTORY')->delete();
    }
};
