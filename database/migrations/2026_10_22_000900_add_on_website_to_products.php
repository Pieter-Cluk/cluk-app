<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Products that may be shown on the coming website (off until someone ticks it). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_items', fn (Blueprint $t) => $t->boolean('on_website')->default(false)->index());
        Schema::table('sales_items', fn (Blueprint $t) => $t->boolean('on_website')->default(false)->index());
    }

    public function down(): void
    {
        Schema::table('equipment_items', fn (Blueprint $t) => $t->dropColumn('on_website'));
        Schema::table('sales_items', fn (Blueprint $t) => $t->dropColumn('on_website'));
    }
};
