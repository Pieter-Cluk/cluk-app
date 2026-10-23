<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Counted items (no own codes) in a case: "12 × Sol I Barndoor in BOX-01". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_case_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('case_id')->constrained('equipment_assets')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained('equipment_items')->cascadeOnDelete();
            $t->unsignedInteger('qty')->default(0);
            $t->timestamps();
            $t->unique(['case_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_case_items');
    }
};
