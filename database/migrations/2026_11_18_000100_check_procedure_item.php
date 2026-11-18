<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A procedure is built for a dry-hire item (its name comes from that item).
        Schema::table('check_procedures', function (Blueprint $t) {
            $t->foreignId('item_id')->nullable()->after('id')->constrained('equipment_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('check_procedures', fn (Blueprint $t) => $t->dropConstrainedForeignId('item_id'));
    }
};
