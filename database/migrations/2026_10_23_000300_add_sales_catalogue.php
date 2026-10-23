<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sales products get their own catalogue lists, separate from dry-hire (own
 * categories, subcategories, attributes and tags; the same rules and screens).
 * Starts from the current sales categories, each with a "General" subcategory.
 */
return new class extends Migration
{
    private const CATS = [
        // old value => code, en, nl, fr, es
        'Audio' => ['AUD', 'Audio', 'Audio', 'Audio', 'Audio'],
        'Light' => ['LIG', 'Lighting', 'Licht', 'Éclairage', 'Iluminación'],
        'Video' => ['VID', 'Video', 'Video', 'Vidéo', 'Vídeo'],
        'Connectors' => ['CON', 'Connectors', 'Connectoren', 'Connecteurs', 'Conectores'],
        'Rigging' => ['RIG', 'Rigging', 'Rigging', 'Levage', 'Rigging'],
        'Consumables' => ['CSM', 'Consumables', 'Verbruiksgoederen', 'Consommables', 'Consumibles'],
        'Cases' => ['CAS', 'Cases', 'Cases', 'Flight-cases', 'Flightcases'],
        'Other' => ['OTH', 'Other', 'Andere', 'Autres', 'Otros'],
    ];

    public function up(): void
    {
        foreach (['product_categories' => ['code', 'slug'], 'product_attributes' => ['key'], 'product_tags' => ['slug']] as $table => $cols) {
            Schema::table($table, function (Blueprint $t) use ($table, $cols) {
                $t->string('catalogue', 10)->default('dryhire')->index();
                foreach ($cols as $c) {
                    $t->dropUnique($table.'_'.$c.'_unique');
                    $t->unique(['catalogue', $c]);
                }
            });
        }
        Schema::table('sales_items', function (Blueprint $t) {
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->foreignId('subcategory_id')->nullable()->constrained('product_subcategories')->nullOnDelete();
            $t->text('search_words')->nullable();
            $t->string('legacy_category', 60)->nullable();
        });
        Schema::create('sales_item_options', function (Blueprint $t) {
            $t->foreignId('sales_item_id')->constrained('sales_items')->cascadeOnDelete();
            $t->foreignId('option_id')->constrained('product_attribute_options');
            $t->primary(['sales_item_id', 'option_id']);
        });
        Schema::create('sales_item_tag', function (Blueprint $t) {
            $t->foreignId('sales_item_id')->constrained('sales_items')->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained('product_tags');
            $t->primary(['sales_item_id', 'tag_id']);
        });

        $now = now();
        $ids = [];
        $i = 0;
        foreach (self::CATS as $old => [$code, $en, $nl, $fr, $es]) {
            $i++;
            $cid = DB::table('product_categories')->insertGetId(['catalogue' => 'sales', 'code' => $code, 'slug' => Str::slug($en), 'sort' => $i * 10, 'active' => true,
                'name' => json_encode(compact('en', 'nl', 'fr', 'es'), JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now]);
            $sid = DB::table('product_subcategories')->insertGetId(['category_id' => $cid, 'code' => 'GEN', 'slug' => 'general', 'sort' => 10, 'active' => true,
                'name' => json_encode(['en' => 'General', 'nl' => 'Algemeen', 'fr' => 'Général', 'es' => 'General'], JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now]);
            $ids[$old] = [$cid, $sid, $en];
        }
        foreach ([['featured', 'Featured', 'Uitgelicht', 'À la une', 'Destacado'], ['new', 'New', 'Nieuw', 'Nouveau', 'Nuevo'], ['promotion', 'Promotion', 'Promotie', 'Promotion', 'Promoción']] as $k => [$slug, $en, $nl, $fr, $es]) {
            DB::table('product_tags')->insert(['catalogue' => 'sales', 'slug' => $slug, 'name' => json_encode(compact('en', 'nl', 'fr', 'es'), JSON_UNESCAPED_UNICODE), 'sort' => ($k + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (DB::table('sales_items')->get(['id', 'category']) as $item) {
            $m = $item->category !== null ? ($ids[$item->category] ?? null) : null;
            DB::table('sales_items')->where('id', $item->id)->update(['legacy_category' => $item->category]
                + ($m ? ['category_id' => $m[0], 'subcategory_id' => $m[1], 'category' => $m[2]] : []));
        }
    }

    public function down(): void
    {
        foreach (DB::table('sales_items')->get(['id', 'legacy_category']) as $item) {
            DB::table('sales_items')->where('id', $item->id)->update(['category' => $item->legacy_category]);
        }
        Schema::dropIfExists('sales_item_tag');
        Schema::dropIfExists('sales_item_options');
        Schema::table('sales_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('category_id');
            $t->dropConstrainedForeignId('subcategory_id');
            $t->dropColumn(['search_words', 'legacy_category']);
        });
        $sales = DB::table('product_categories')->where('catalogue', 'sales')->pluck('id');
        DB::table('product_subcategories')->whereIn('category_id', $sales)->delete();
        DB::table('product_categories')->where('catalogue', 'sales')->delete();
        DB::table('product_tags')->where('catalogue', 'sales')->delete();
        $salesAttrs = DB::table('product_attributes')->where('catalogue', 'sales')->pluck('id');
        DB::table('product_attribute_options')->whereIn('attribute_id', $salesAttrs)->delete();
        DB::table('product_attributes')->whereIn('id', $salesAttrs)->delete();
        foreach (['product_categories' => ['code', 'slug'], 'product_attributes' => ['key'], 'product_tags' => ['slug']] as $table => $cols) {
            Schema::table($table, function (Blueprint $t) use ($table, $cols) {
                foreach ($cols as $c) {
                    $t->dropUnique($table.'_catalogue_'.$c.'_unique');
                    $t->unique($c);
                }
                $t->dropIndex($table.'_catalogue_index');
                $t->dropColumn('catalogue');
            });
        }
    }
};
