<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** HireHop names and sub-categories are longer than first planned (up to ~300 characters). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->string('subcategory', 160)->nullable()->change();
            $table->string('type', 255)->nullable()->change();
            $table->string('name', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->string('subcategory', 80)->nullable()->change();
            $table->string('type', 120)->nullable()->change();
            $table->string('name', 160)->change();
        });
    }
};
