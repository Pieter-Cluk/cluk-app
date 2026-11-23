<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-hire price requests can start while the quote is being made (Pieter, 23/11/2026),
 * so the quote is priced on the partner's real price. They hang on the quote (and its lines)
 * until the customer accepts; then they move to the order like any other request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subhire_requests', function (Blueprint $t) {
            $t->foreignId('quote_id')->nullable()->after('order_id')->constrained('quotes')->nullOnDelete();
            $t->unsignedBigInteger('order_id')->nullable()->change();
        });
        Schema::table('subhire_lines', function (Blueprint $t) {
            $t->foreignId('quote_line_id')->nullable()->after('order_line_id')->constrained('quote_lines')->nullOnDelete();
            $t->unsignedBigInteger('order_line_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subhire_lines', fn (Blueprint $t) => $t->dropConstrainedForeignId('quote_line_id'));
        Schema::table('subhire_requests', fn (Blueprint $t) => $t->dropConstrainedForeignId('quote_id'));
    }
};
