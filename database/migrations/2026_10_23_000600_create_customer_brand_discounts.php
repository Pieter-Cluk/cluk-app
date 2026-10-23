<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales discounts a customer gets, per brand (or one for every other brand).
 * Used on the website (the customer's own purchase price) and on new sales
 * quote lines for that customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_brand_discounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained('brands')->cascadeOnDelete(); // empty = every other brand
            $t->unsignedSmallInteger('discount_bp');
            $t->string('note', 200)->nullable();
            $t->foreignId('set_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_brand_discounts');
    }
};
