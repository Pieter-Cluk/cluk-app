<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who brings the goods: the supplier delivers to our warehouse, or CLUK organises the transport (collects them). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_requests', 'purchase_orders'] as $t) {
            Schema::table($t, fn (Blueprint $b) => $b->string('delivery', 10)->default('supplier'));
        }
    }

    public function down(): void
    {
        foreach (['purchase_requests', 'purchase_orders'] as $t) {
            Schema::table($t, fn (Blueprint $b) => $b->dropColumn('delivery'));
        }
    }
};
