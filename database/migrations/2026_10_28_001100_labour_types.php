<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kinds of labour with a rate (Settings › Labour rates), added as a line on rental and Cluk Factory quotes.
        Schema::create('labour_types', function (Blueprint $t) {
            $t->id();
            $t->json('names');                          // {"en": "...", "nl": "...", "fr": "...", "es": "..."}
            $t->string('unit', 4)->default('hour');     // hour | day
            $t->unsignedInteger('price_cents')->nullable(); // excl. VAT; no rate = not offered on quotes yet
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });
        Schema::table('quote_lines', function (Blueprint $t) {
            $t->foreignId('labour_type_id')->nullable()->after('item_id')->constrained('labour_types')->nullOnDelete();
        });

        $now = now();
        foreach ([
            ['en' => 'Installation / mounting', 'nl' => 'Installatie / montage', 'fr' => 'Installation / montage', 'es' => 'Instalación / montaje'],
            ['en' => 'Programming / commissioning', 'nl' => 'Programmatie / indienststelling', 'fr' => 'Programmation / mise en service', 'es' => 'Programación / puesta en marcha'],
            ['en' => 'Technician / operator', 'nl' => 'Technieker / operator', 'fr' => 'Technicien / opérateur', 'es' => 'Técnico / operador'],
            ['en' => 'Stagehand / handling', 'nl' => 'Stagehand / handling', 'fr' => 'Machiniste / manutention', 'es' => 'Auxiliar / carga y descarga'],
        ] as $i => $names) {
            DB::table('labour_types')->insert(['names' => json_encode($names, JSON_UNESCAPED_UNICODE), 'unit' => 'hour', 'price_cents' => null, 'active' => true, 'position' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $t) {
            $t->dropConstrainedForeignId('labour_type_id');
        });
        Schema::dropIfExists('labour_types');
    }
};
