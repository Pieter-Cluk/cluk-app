<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Supplier prices in another currency: the amount they gave, and the ECB day rate used to turn it into euro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_replies', function (Blueprint $t) {
            $t->string('currency', 3)->default('EUR');
            $t->bigInteger('foreign_cents')->nullable();
            $t->decimal('rate', 14, 6)->nullable();
            $t->date('rate_date')->nullable();
        });
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->string('currency', 3)->default('EUR');
            $t->decimal('rate', 14, 6)->nullable();
            $t->date('rate_date')->nullable();
        });
        Schema::table('purchase_order_lines', fn (Blueprint $t) => $t->bigInteger('unit_foreign_cents')->nullable());
    }

    public function down(): void
    {
        Schema::table('purchase_request_replies', fn (Blueprint $t) => $t->dropColumn(['currency', 'foreign_cents', 'rate', 'rate_date']));
        Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropColumn(['currency', 'rate', 'rate_date']));
        Schema::table('purchase_order_lines', fn (Blueprint $t) => $t->dropColumn('unit_foreign_cents'));
    }
};
