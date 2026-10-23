<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standard sales conditions per company (extends the brand discounts):
 * a default % (no brand, category or product), a % per brand or per sales
 * category, or a fixed net price per product. Priority when pricing:
 * product net price > brand % > category % > default % > list price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_brand_discounts', function (Blueprint $t) {
            $t->index('company_id', 'cbd_company_index'); // keeps the company link indexed once the unique pair goes
        });
        Schema::table('customer_brand_discounts', function (Blueprint $t) {
            $t->dropUnique(['company_id', 'brand_id']);
            $t->foreignId('category_id')->nullable()->after('brand_id')->constrained('product_categories')->cascadeOnDelete();
            $t->foreignId('sales_item_id')->nullable()->after('category_id')->constrained('sales_items')->cascadeOnDelete();
            $t->unsignedInteger('net_cents')->nullable()->after('discount_bp');
        });
        Schema::table('customer_brand_discounts', function (Blueprint $t) {
            $t->unsignedSmallInteger('discount_bp')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_brand_discounts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sales_item_id');
            $t->dropConstrainedForeignId('category_id');
            $t->dropColumn('net_cents');
        });
    }
};
