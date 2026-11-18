<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A piece that is technically OK but came back without some accessories: rentable, not a standard configuration.
        Schema::table('equipment_assets', function (Blueprint $t) {
            $t->json('missing_kit')->nullable(); // [{item_id, qty}]
        });
    }

    public function down(): void
    {
        Schema::table('equipment_assets', fn (Blueprint $t) => $t->dropColumn('missing_kit'));
    }
};
