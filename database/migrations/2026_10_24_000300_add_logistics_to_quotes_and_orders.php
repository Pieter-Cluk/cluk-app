<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics on rental quotes and orders: not known yet, the customer picks up,
 * the customer arranges transport, or CLUK delivers (with a transport price that
 * can be added or changed after the quote is won, until the pre-invoice).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'orders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('logistics', 12)->default('open');
                $t->integer('transport_cents')->default(0);
                $t->string('transport_note', 300)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'orders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['logistics', 'transport_cents', 'transport_note']);
            });
        }
    }
};
