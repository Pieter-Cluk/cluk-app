<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who arranges the transport must be chosen (not assumed): CLUK picks up, or the supplier/partner delivers.
 * Sub-hire requests get the choice; purchase requests no longer default to "supplier delivers".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subhire_requests', fn (Blueprint $t) => $t->string('transport', 10)->nullable()->after('status'));
        Schema::table('purchase_requests', fn (Blueprint $t) => $t->string('delivery', 10)->nullable()->default(null)->change());
    }

    public function down(): void
    {
        Schema::table('subhire_requests', fn (Blueprint $t) => $t->dropColumn('transport'));
        Schema::table('purchase_requests', fn (Blueprint $t) => $t->string('delivery', 10)->default('supplier')->change());
    }
};
