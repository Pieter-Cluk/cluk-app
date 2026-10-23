<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed lists for the catalogue (proposal of 23 Oct 2026): categories,
 * subcategories, attributes with fixed choices, and tags. Every dry-hire item
 * gets its new place from database/data/product_mapping.json; the old text is
 * kept in legacy_category / legacy_subcategory so down() restores it exactly.
 * Item numbers are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code', 6)->unique();
            $t->string('slug', 80)->unique();
            $t->json('old_slugs')->nullable();
            $t->json('name');
            $t->integer('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('product_subcategories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('product_categories');
            $t->string('code', 6);
            $t->string('slug', 80);
            $t->json('old_slugs')->nullable();
            $t->json('name');
            $t->integer('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['category_id', 'code']);
            $t->unique(['category_id', 'slug']);
        });
        Schema::create('product_attributes', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->json('name');
            $t->boolean('multiple')->default(false);
            $t->string('unit', 10)->nullable();
            $t->integer('sort')->default(0);
            $t->timestamps();
        });
        Schema::create('product_attribute_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $t->string('value', 60);
            $t->json('label')->nullable();
            $t->integer('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['attribute_id', 'value']);
        });
        Schema::create('product_subcategory_attribute', function (Blueprint $t) {
            $t->foreignId('subcategory_id')->constrained('product_subcategories')->cascadeOnDelete();
            $t->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $t->primary(['subcategory_id', 'attribute_id']);
        });
        Schema::create('product_tags', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->json('name');
            $t->integer('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('equipment_item_options', function (Blueprint $t) {
            $t->foreignId('item_id')->constrained('equipment_items')->cascadeOnDelete();
            $t->foreignId('option_id')->constrained('product_attribute_options');
            $t->primary(['item_id', 'option_id']);
        });
        Schema::create('equipment_item_tag', function (Blueprint $t) {
            $t->foreignId('item_id')->constrained('equipment_items')->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained('product_tags');
            $t->primary(['item_id', 'tag_id']);
        });
        Schema::table('equipment_items', function (Blueprint $t) {
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->foreignId('subcategory_id')->nullable()->constrained('product_subcategories')->nullOnDelete();
            $t->text('search_words')->nullable();
            $t->string('legacy_category', 60)->nullable();
            $t->string('legacy_subcategory', 160)->nullable();
        });

        $now = now();
        $data = require database_path('data/product_classification.php');
        $subIds = [];
        foreach ($data['categories'] as $ci => $c) {
            $cid = DB::table('product_categories')->insertGetId(['code' => $c['code'], 'slug' => $c['slug'], 'name' => json_encode($c['name'], JSON_UNESCAPED_UNICODE),
                'sort' => ($ci + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($c['subs'] as $si => $s) {
                $subIds[$c['code'].'.'.$s['code']] = DB::table('product_subcategories')->insertGetId(['category_id' => $cid, 'code' => $s['code'], 'slug' => $s['slug'],
                    'name' => json_encode($s['name'], JSON_UNESCAPED_UNICODE), 'sort' => ($si + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $optIds = [];
        foreach ($data['attributes'] as $ai => $a) {
            $aid = DB::table('product_attributes')->insertGetId(['key' => $a['key'], 'name' => json_encode($a['name'], JSON_UNESCAPED_UNICODE), 'multiple' => $a['multiple'],
                'unit' => $a['unit'], 'sort' => ($ai + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($a['options'] as $oi => $o) {
                $optIds[$a['key']][$o['value']] = DB::table('product_attribute_options')->insertGetId(['attribute_id' => $aid, 'value' => $o['value'],
                    'label' => isset($o['label']) ? json_encode($o['label'], JSON_UNESCAPED_UNICODE) : null, 'sort' => ($oi + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ($a['subs'] as $s) {
                DB::table('product_subcategory_attribute')->insert(['subcategory_id' => $subIds[$s], 'attribute_id' => $aid]);
            }
        }
        foreach ($data['tags'] as $ti => $tg) {
            DB::table('product_tags')->insert(['slug' => $tg['slug'], 'name' => json_encode($tg['name'], JSON_UNESCAPED_UNICODE), 'sort' => ($ti + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Every item to its new place; the English names also go in the old text columns, so what reads them keeps working.
        $cats = DB::table('product_categories')->get()->keyBy('code');
        $subs = DB::table('product_subcategories')->get()->keyBy('id');
        $map = json_decode((string) file_get_contents(database_path('data/product_mapping.json')), true) ?: [];
        foreach (DB::table('equipment_items')->where('kind', '!=', 'service')->get(['id', 'number', 'category', 'subcategory']) as $item) {
            $m = $map[$item->number] ?? null;
            $update = ['legacy_category' => $item->category, 'legacy_subcategory' => $item->subcategory];
            if ($m && isset($subIds[$m['c'].'.'.$m['s']])) {
                $sid = $subIds[$m['c'].'.'.$m['s']];
                $update += ['category_id' => $cats[$m['c']]->id, 'subcategory_id' => $sid, 'search_words' => $m['w'] ? mb_substr($m['w'], 0, 1000) : null,
                    'category' => json_decode($cats[$m['c']]->name, true)['en'], 'subcategory' => json_decode($subs[$sid]->name, true)['en']];
                foreach ($m['a'] as $key => $values) {
                    foreach ($values as $v) {
                        if (isset($optIds[$key][$v])) {
                            DB::table('equipment_item_options')->insertOrIgnore(['item_id' => $item->id, 'option_id' => $optIds[$key][$v]]);
                        }
                    }
                }
            }
            DB::table('equipment_items')->where('id', $item->id)->update($update);
        }

        // The pre-check zone for lighting also takes the new category name.
        if ($z = DB::table('warehouse_zones')->where('code', 'PRE-LIGHT')->first()) {
            $c = json_decode((string) $z->categories, true) ?: [];
            if (! in_array('Lighting', $c, true)) {
                DB::table('warehouse_zones')->where('id', $z->id)->update(['categories' => json_encode(array_merge($c, ['Lighting']))]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('equipment_items')->whereNotNull('legacy_category')->orWhereNotNull('legacy_subcategory')->get(['id', 'legacy_category', 'legacy_subcategory']) as $i) {
            DB::table('equipment_items')->where('id', $i->id)->update(['category' => $i->legacy_category, 'subcategory' => $i->legacy_subcategory]);
        }
        if ($z = DB::table('warehouse_zones')->where('code', 'PRE-LIGHT')->first()) {
            $c = array_values(array_diff(json_decode((string) $z->categories, true) ?: [], ['Lighting']));
            DB::table('warehouse_zones')->where('id', $z->id)->update(['categories' => $c ? json_encode($c) : null]);
        }
        Schema::table('equipment_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('category_id');
            $t->dropConstrainedForeignId('subcategory_id');
            $t->dropColumn(['search_words', 'legacy_category', 'legacy_subcategory']);
        });
        Schema::dropIfExists('equipment_item_tag');
        Schema::dropIfExists('equipment_item_options');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_subcategory_attribute');
        Schema::dropIfExists('product_attribute_options');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_subcategories');
        Schema::dropIfExists('product_categories');
    }
};
