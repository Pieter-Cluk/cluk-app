<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sales catalogue: a managed list of brands (own / distributed / other, with
 * logo and text for the website) and the manufacturer's item code on each
 * product. Our own S-number stays the main number; suppliers keep their own
 * article number per supplier (sales_item_suppliers.supplier_ref).
 * Existing brand texts become brands of kind "other"; nothing else changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->string('slug', 90)->unique();
            $t->json('old_slugs')->nullable();          // a changed slug keeps working on the website
            $t->string('kind', 12)->default('other');   // own, distributed, other
            $t->string('website', 190)->nullable();
            $t->string('logo_path')->nullable();
            $t->json('intro')->nullable();               // per language, for the brand page on the website
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(100);
            $t->timestamps();
        });
        Schema::table('sales_items', function (Blueprint $t) {
            $t->foreignId('brand_id')->nullable()->after('brand')->constrained('brands');
            $t->string('manufacturer_code', 60)->nullable()->after('type')->index();
            $t->index('ean');
        });

        $now = now();
        $seen = [];
        foreach (DB::table('sales_items')->whereNotNull('brand')->where('brand', '!=', '')->orderBy('id')->get(['id', 'brand']) as $row) {
            $name = trim($row->brand);
            $key = Str::lower($name);
            if (! isset($seen[$key])) {
                $slug = Str::slug($name) ?: 'brand';
                $base = $slug;
                for ($i = 2; DB::table('brands')->where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $seen[$key] = DB::table('brands')->insertGetId(['name' => Str::limit($name, 80, ''), 'slug' => $slug, 'kind' => 'other', 'active' => true, 'sort' => 100, 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('sales_items')->where('id', $row->id)->update(['brand_id' => $seen[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('sales_items', function (Blueprint $t) {
            $t->dropIndex(['ean']);
            $t->dropIndex(['manufacturer_code']);
            $t->dropConstrainedForeignId('brand_id');
            $t->dropColumn('manufacturer_code');
        });
        Schema::dropIfExists('brands');
    }
};
