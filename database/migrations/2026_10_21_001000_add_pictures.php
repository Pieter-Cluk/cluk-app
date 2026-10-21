<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pictures: staff photo (from Microsoft 365), company logo, contact photo, several photos per item. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('photo_path')->nullable());
        Schema::table('companies', fn (Blueprint $t) => $t->string('logo_path')->nullable());
        Schema::table('contacts', fn (Blueprint $t) => $t->string('photo_path')->nullable());

        Schema::create('equipment_item_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('equipment_items')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_item_photos');
        Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn('photo_path'));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('logo_path'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('photo_path'));
    }
};
