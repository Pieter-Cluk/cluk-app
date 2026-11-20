<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A repair is for one piece: its serial number (or piece code) on the quote line and the order line. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', fn (Blueprint $t) => $t->string('serial', 80)->nullable()->after('note'));
        Schema::table('order_lines', fn (Blueprint $t) => $t->string('serial', 80)->nullable()->after('note'));
    }

    public function down(): void
    {
        Schema::table('quote_lines', fn (Blueprint $t) => $t->dropColumn('serial'));
        Schema::table('order_lines', fn (Blueprint $t) => $t->dropColumn('serial'));
    }
};
