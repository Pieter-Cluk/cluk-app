<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Labour rates are for rental quotes, sales quotes (repair, maintenance) or both.
        Schema::table('labour_types', function (Blueprint $t) {
            $t->string('used_for', 8)->default('rental')->after('unit'); // rental | sales | both
        });
        Schema::table('sales_quote_lines', function (Blueprint $t) {
            $t->foreignId('labour_type_id')->nullable()->after('sales_item_id')->constrained('labour_types')->nullOnDelete();
        });

        // Repair and maintenance on sales quotes: technician at €60/hour.
        $now = now();
        DB::table('labour_types')->insert([
            'names' => json_encode(['en' => 'Technician · repair & maintenance', 'nl' => 'Technieker · herstelling & onderhoud', 'fr' => 'Technicien · réparation & entretien', 'es' => 'Técnico · reparación y mantenimiento'], JSON_UNESCAPED_UNICODE),
            'unit' => 'hour', 'used_for' => 'sales', 'price_cents' => 6000, 'active' => true,
            'position' => (int) DB::table('labour_types')->max('position') + 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('sales_quote_lines', fn (Blueprint $t) => $t->dropConstrainedForeignId('labour_type_id'));
        DB::table('labour_types')->where('used_for', 'sales')->delete();
        Schema::table('labour_types', fn (Blueprint $t) => $t->dropColumn('used_for'));
    }
};
