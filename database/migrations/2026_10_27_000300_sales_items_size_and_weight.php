<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sales products: weight (kg) and width × length × height (stored in metres, entered in cm), like the dry-hire items. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_items', function (Blueprint $t) {
            $t->decimal('weight_kg', 9, 3)->nullable();
            $t->decimal('width_m', 7, 4)->nullable();
            $t->decimal('length_m', 7, 4)->nullable();
            $t->decimal('height_m', 7, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_items', fn (Blueprint $t) => $t->dropColumn(['weight_kg', 'width_m', 'length_m', 'height_m']));
    }
};
