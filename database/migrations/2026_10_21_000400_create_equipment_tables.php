<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rental equipment: an item is a product (e.g. Robe Spiider); serial items
        // have one asset per physical piece, counted items only a quantity.
        Schema::create('equipment_items', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();      // item number, e.g. LT-SPIIDER
            $table->string('kind', 10)->default('item'); // item | package | case | service
            $table->string('category', 60)->nullable();
            $table->string('subcategory', 80)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('type', 120)->nullable();
            $table->string('name', 160);
            $table->string('unit', 12)->default('pcs');
            $table->boolean('serial')->default(true);
            $table->unsignedInteger('stock_qty')->default(0);       // counted items only
            $table->unsignedInteger('day_price_cents')->default(0); // rental per day (factor applies); services: price per unit
            $table->string('price_unit', 12)->nullable();          // services: month, half_day, day, hour, coli, pallet, event_day, event
            $table->unsignedInteger('catalog_price_cents')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('width_m', 6, 3)->nullable();
            $table->decimal('length_m', 6, 3)->nullable();
            $table->decimal('height_m', 6, 3)->nullable();
            $table->char('origin', 2)->nullable();
            $table->string('hs_code', 20)->nullable();
            $table->json('descriptions')->nullable();   // en, nl, fr, es
            $table->string('photo_path')->nullable();
            $table->boolean('in_search')->default(true); // shown in the quote catalogue
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['category', 'active']);
        });

        // Parts of an item: kit (goes with every piece), or what a package/case contains.
        Schema::create('equipment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('equipment_items')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('equipment_items')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->string('role', 10)->default('kit'); // kit | content
            $table->unique(['parent_id', 'child_id', 'role']);
        });

        // One physical piece with its own serial number and QR label.
        Schema::create('equipment_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('equipment_items')->cascadeOnDelete();
            $table->string('code', 30)->unique();         // on the QR label, e.g. LT-0142
            $table->string('serial_number', 80)->nullable();
            $table->string('status', 12)->default('available'); // available | out | repair | retired
            $table->string('location', 60)->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedInteger('purchase_price_cents')->nullable();
            $table->string('supplier', 160)->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('retired_reason', 40)->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamp('label_printed_at')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'status']);
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_assets');
        Schema::dropIfExists('equipment_components');
        Schema::dropIfExists('equipment_items');
    }
};
