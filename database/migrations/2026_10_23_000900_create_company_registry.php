<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Official register data per company (Belgium: KBO/BCE open data), refreshed
 * by the "KBO update" workflow. Used for the credit traffic light on the
 * customer card. One row per company; the history of changes is in the audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_registry', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('source', 8)->default('kbo');
            $t->string('enterprise_number', 12)->index();     // 0123.456.789
            $t->boolean('found')->default(true);              // false: not in the register (stopped long ago, wrong number)
            $t->string('status', 4)->nullable();              // AC = active
            $t->string('situation_code', 4)->nullable();      // juridical situation, 000 = normal
            $t->json('situation')->nullable();                // description per language
            $t->string('form_code', 4)->nullable();           // legal form
            $t->json('form')->nullable();
            $t->date('start_date')->nullable();
            $t->string('official_name', 255)->nullable();
            $t->string('address', 255)->nullable();
            $t->date('snapshot_date')->nullable();            // date of the KBO extract
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_registry');
    }
};
