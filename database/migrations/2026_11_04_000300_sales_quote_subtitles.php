<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sales quotes: subtitles (bold text lines) and, when chosen, a subtotal per subtitle. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_quote_lines', fn (Blueprint $t) => $t->boolean('heading')->default(false)->after('kind'));
        Schema::table('sales_quotes', fn (Blueprint $t) => $t->boolean('heading_totals')->default(false));
    }

    public function down(): void
    {
        Schema::table('sales_quote_lines', fn (Blueprint $t) => $t->dropColumn('heading'));
        Schema::table('sales_quotes', fn (Blueprint $t) => $t->dropColumn('heading_totals'));
    }
};
