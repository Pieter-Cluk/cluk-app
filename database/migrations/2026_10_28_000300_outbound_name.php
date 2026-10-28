<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Pieter, 28/10/2026: "Dispatch / loading" is called Outbound (as Inbound on the way back). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('warehouse_zones')->where('code', 'DISPATCH')->where('name', 'like', 'Dispatch%')->update(['name' => null]);
    }

    public function down(): void {}
};
