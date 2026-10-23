<?php

namespace Tests\Feature;

use App\Imports\EquipmentImport;
use App\Imports\SalesItemImport;
use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeOption;
use App\Models\Product\Category;
use App\Models\Product\Subcategory;
use App\Models\ProductSales\SalesItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Products\ItemSearch;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function as(string $role, array $rights = [], string $locale = 'en'): User
    {
        $u = User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => $locale]);
        foreach ($rights as $r) {
            $u->extraRights()->create(['right' => $r]);
        }

        return $u->fresh();
    }

    private static function opt(string $attribute, string $value): int
    {
        return (int) AttributeOption::whereHas('attribute', fn ($a) => $a->where('key', $attribute))->where('value', $value)->value('id');
    }

    private function form(array $over = []): array
    {
        return $over + ['number' => 'RIGSQUNEXNH34200', 'kind' => 'item', 'name' => 'NEXT NH34 2 m', 'unit' => 'pcs', 'active' => 1];
    }

    public function test_the_agreed_lists_are_in_place(): void
    {
        $this->assertSame(7, Category::of('dryhire')->count());
        $this->assertSame(57, Subcategory::whereHas('category', fn ($q) => $q->where('catalogue', 'dryhire'))->count());
        $this->assertSame(['AUD', 'LIG', 'VID', 'RIG', 'POW', 'STA', 'CAB'], Category::of('dryhire')->orderBy('sort')->pluck('code')->all());
        $this->assertSame(['AUD', 'LIG', 'VID', 'CON', 'RIG', 'CSM', 'CAS', 'OTH'], Category::of('sales')->orderBy('sort')->pluck('code')->all());
        $this->assertSame('Vierkante truss', Subcategory::where('code', 'SQU')->first()->label('nl'));
        $this->assertSame('Zwart', AttributeOption::find(self::opt('colour', 'black'))->display('nl'));
        $this->assertSame('2,5 m', AttributeOption::find(self::opt('length', '2.5'))->display('nl'));
    }

    public function test_subcategory_must_belong_to_the_category(): void
    {
        $am = $this->as('asset-manager');
        $this->actingAs($am)->post('/dry-hire/items', $this->form(['category_id' => Category::where('code', 'AUD')->value('id'), 'subcategory_id' => self::sub('SQU')]))
            ->assertSessionHasErrors('subcategory_id');
        $this->actingAs($am)->post('/dry-hire/items', $this->form())->assertSessionHasErrors('subcategory_id'); // a product needs its place
        $this->actingAs($am)->post('/dry-hire/items', $this->form(['category_id' => Category::where('code', 'RIG')->value('id'), 'subcategory_id' => self::sub('SQU')]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $item = Item::where('number', 'RIGSQUNEXNH34200')->firstOrFail();
        $this->assertSame(['Rigging & truss', 'Square truss'], [$item->category, $item->subcategory]);
        // A service needs no category.
        $this->actingAs($am)->post('/dry-hire/items', ['number' => 'SRV-1', 'kind' => 'service', 'name' => 'Technician', 'unit' => 'day', 'active' => 1])->assertSessionHasNoErrors();
    }

    public function test_attributes_only_for_the_subcategories_they_apply_to(): void
    {
        $am = $this->as('asset-manager');
        // Colour is for truss, not for a microphone.
        $this->actingAs($am)->post('/dry-hire/items', $this->form(['number' => 'AUDMICX', 'subcategory_id' => self::sub('MIC'), 'attr' => ['colour' => self::opt('colour', 'black')]]))
            ->assertSessionHasErrors('attr.colour');
        // One choice only, unless the attribute takes several.
        $this->actingAs($am)->post('/dry-hire/items', $this->form(['subcategory_id' => self::sub('SQU'), 'attr' => ['colour' => [self::opt('colour', 'black'), self::opt('colour', 'aluminium')]]]))
            ->assertSessionHasErrors('attr.colour');
        // A choice of another attribute is refused.
        $this->actingAs($am)->post('/dry-hire/items', $this->form(['subcategory_id' => self::sub('SQU'), 'attr' => ['colour' => self::opt('truss_series', 'NH34')]]))
            ->assertSessionHasErrors('attr.colour');

        $this->actingAs($am)->post('/dry-hire/items', $this->form(['subcategory_id' => self::sub('SQU'), 'attr' => ['colour' => self::opt('colour', 'black'), 'truss_series' => self::opt('truss_series', 'NH34'), 'length' => self::opt('length', '2')]]))
            ->assertSessionHasNoErrors();
        $item = Item::where('number', 'RIGSQUNEXNH34200')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Black', 'NH34', '2 m'], $item->load('options.attribute')->optionLabels());

        $this->actingAs($am)->post('/dry-hire/items', $this->form(['number' => 'AUDWIRSHUULXD', 'name' => 'Shure ULXD', 'subcategory_id' => self::sub('WMI'),
            'attr' => ['frequency_band' => [self::opt('frequency_band', 'G51'), self::opt('frequency_band', 'H51'), self::opt('frequency_band', 'K52')]]]))->assertSessionHasNoErrors();
        $this->assertSame(3, Item::where('number', 'AUDWIRSHUULXD')->first()->options()->count());

        // The list filters on a value; the item page shows it.
        $this->actingAs($am)->get('/dry-hire?sub='.self::sub('SQU').'&opt[]='.self::opt('colour', 'black'))->assertOk()->assertSee('RIGSQUNEXNH34200')->assertSee('Colour: Black');
        $this->actingAs($am)->get('/dry-hire?sub='.self::sub('SQU').'&opt[]='.self::opt('colour', 'aluminium'))->assertOk()->assertDontSee('RIGSQUNEXNH34200');
        $this->actingAs($am)->get('/dry-hire/items/'.$item->id)->assertOk()->assertSee('Square truss')->assertSee('Black');
        $this->actingAs($am)->get('/dry-hire/items/'.$item->id.'/edit')->assertOk()->assertSee('Truss series');
    }

    public function test_item_number_never_changes_when_recategorised(): void
    {
        $am = $this->as('asset-manager');
        $this->actingAs($am)->post('/dry-hire/items', $this->form(['subcategory_id' => self::sub('SQU'), 'attr' => ['colour' => self::opt('colour', 'black')]]));
        $item = Item::where('number', 'RIGSQUNEXNH34200')->firstOrFail();
        $this->actingAs($am)->put('/dry-hire/items/'.$item->id, $this->form(['subcategory_id' => self::sub('TRI'), 'attr' => ['colour' => self::opt('colour', 'black')]]))->assertRedirect();
        $item->refresh();
        $this->assertSame(['RIGSQUNEXNH34200', 'Triangle truss'], [$item->number, $item->subcategory]);
        $this->assertTrue(AuditLog::where('action', 'equipment.item_updated')->where('data', 'like', '%classification%')->exists());

        // Moving all items when a subcategory is switched off keeps their numbers too, and drops values that no longer apply.
        $admin = $this->as('admin', ['products.classify']);
        $tri = Subcategory::find(self::sub('TRI'));
        $this->actingAs($admin)->put('/settings/product-categories/subcategories/'.$tri->id, ['name' => $tri->name, 'code' => 'TRI'])->assertSessionHasErrors('move_to');
        $this->actingAs($admin)->put('/settings/product-categories/subcategories/'.$tri->id, ['name' => $tri->name, 'code' => 'TRI', 'move_to' => self::sub('HAR')])->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame(['RIGSQUNEXNH34200', 'Rigging hardware & load cells', 0], [$item->number, $item->subcategory, $item->options()->count()]);
        $this->assertFalse($tri->fresh()->active);
    }

    public function test_import_checks_the_lists_with_clear_messages(): void
    {
        $head = ['Itemnumber', 'Category', 'Sub-Category', 'Brand', 'Type', 'Name', 'Rental price per day', 'Catalogue length (m)', 'Colour', 'Truss series', 'Frequency band', 'Tags', 'Search words'];
        $report = (new EquipmentImport)->run([$head,
            ['CAB-1', 'Cables & data', 'DMX & data cables', 'Neutrik', '8-way 100 m', null, '20', '100', null, null, null, null, 'dmx, multi'],
            ['TRU-1', 'Rigging & truss', 'Vierkante truss', 'NEXT', 'NH34 2 m', null, '12', '2', 'Zwart', 'NH34', null, 'Featured', null],
            ['MIC-1', 'Audio', 'Draadloze microfoons', 'Shure', 'ULXD', null, '50', null, null, null, 'G51, H51', null, null],
            ['BAD-1', 'Sound', 'Speakers', 'X', 'Y', null, '1', null, null, null, null, null, null],
            ['BAD-2', 'Lighting', 'Speakers', 'X', 'Y', null, '1', null, null, null, null, null, null],
            ['BAD-3', 'Audio', 'Microphones', 'X', 'Y', null, '1', null, 'Black', null, null, null, null],
            ['BAD-4', 'Rigging & truss', 'Square truss', 'X', 'Y', null, '1', '2', 'Pink', null, null, null, null],
            ['BAD-5', null, null, 'X', 'Y', null, '1', null, null, null, null, null, null],
            ['BAD-6', 'Rigging & truss', 'Square truss', 'X', 'Y', null, '1', null, null, null, null, 'Cheap', null],
        ]);
        $this->assertSame(3, $report['created']);
        $errors = collect($report['messages'])->where('level', 'error')->pluck('text', 'row');
        $this->assertCount(6, $errors);
        $this->assertStringContainsString('Unknown category “Sound”', $errors[5]);
        $this->assertStringContainsString('“Speakers” does not belong to category “Lighting”', $errors[6]);
        $this->assertStringContainsString('“Colour” does not apply to subcategory “Microphones”', $errors[7]);
        $this->assertStringContainsString('“Pink” is not a choice of “Colour”', $errors[8]);
        $this->assertStringContainsString('without a category', $errors[9]);
        $this->assertStringContainsString('Unknown tag “Cheap”', $errors[10]);
        $this->assertFalse(Item::where('number', 'like', 'BAD-%')->exists());

        $truss = Item::where('number', 'TRU-1')->firstOrFail()->load('options.attribute', 'tags');
        $this->assertSame('Square truss', $truss->subcategory);
        $this->assertEqualsCanonicalizing(['2 m', 'Black', 'NH34'], $truss->optionLabels());
        $this->assertSame(['featured'], $truss->tags->pluck('slug')->all());
        $this->assertSame(2, Item::where('number', 'MIC-1')->first()->options()->count());
        $this->assertSame('dmx, multi', Item::where('number', 'CAB-1')->value('search_words'));

        // Again with only a new colour: the other values stay.
        (new EquipmentImport)->run([['Itemnumber', 'Colour'], ['TRU-1', 'Aluminium']]);
        $this->assertEqualsCanonicalizing(['2 m', 'Aluminium', 'NH34'], $truss->fresh()->load('options.attribute')->optionLabels());
    }

    public function test_quote_search_finds_attribute_values_in_any_language(): void
    {
        $am = $this->as('asset-manager');
        foreach (['black' => 'RIG-B', 'aluminium' => 'RIG-A'] as $colour => $n) {
            $this->actingAs($am)->post('/dry-hire/items', $this->form(['number' => $n, 'name' => 'NEXT NH34 2 m', 'subcategory_id' => self::sub('SQU'),
                'attr' => ['colour' => self::opt('colour', $colour), 'truss_series' => self::opt('truss_series', 'NH34'), 'length' => self::opt('length', '2')]]))->assertSessionHasNoErrors();
        }
        $find = fn (string $q) => ItemSearch::words(Item::query(), $q)->pluck('number')->all();
        $this->assertSame(['RIG-B'], $find('NH34 zwart 2m'));
        $this->assertSame(['RIG-B'], $find('nh34 noir'));
        $this->assertSame(['RIG-A'], $find('NH34 aluminium'));
        $this->assertEqualsCanonicalizing(['RIG-A', 'RIG-B'], $find('NEXT 2m'));
    }

    public function test_managing_the_lists_needs_the_right_and_keeps_the_website_stable(): void
    {
        $this->actingAs($this->as('manager'))->get('/settings/product-categories')->assertForbidden(); // off until given
        $admin = $this->as('admin', ['products.classify'], 'nl');
        $this->actingAs($admin)->get('/settings/product-categories')->assertOk()->assertSee('Productcategorieën')->assertSee('Vierkante truss');

        $rig = Category::where('code', 'RIG')->first();
        $this->actingAs($admin)->post('/settings/product-categories/categories/'.$rig->id.'/subcategories', ['name' => ['en' => 'Barricades', 'nl' => 'Nadarhekken'], 'code' => 'BAR'])->assertSessionHasNoErrors();
        $bar = Subcategory::where('code', 'BAR')->firstOrFail();
        $this->assertFalse($bar->active, 'new ones start hidden');
        $this->assertSame('barricades', $bar->slug);

        // Rename: the address stays. New address: the old one is kept as a redirect.
        $squ = Subcategory::find(self::sub('SQU'));
        $this->actingAs($admin)->put('/settings/product-categories/subcategories/'.$squ->id, ['name' => ['en' => 'Square truss (4 tubes)'], 'code' => 'SQU', 'active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('square-truss', $squ->fresh()->slug);
        $this->actingAs($admin)->put('/settings/product-categories/subcategories/'.$squ->id, ['name' => ['en' => 'Square truss'], 'code' => 'SQU', 'slug' => 'truss-square', 'active' => 1]);
        $this->assertSame(['truss-square', ['square-truss']], [$squ->fresh()->slug, $squ->fresh()->old_slugs]);

        // In use: no delete. Unused: delete.
        Item::create(['number' => 'X1', 'name' => 'X', 'serial' => false])->placeIn($squ->fresh());
        Item::where('number', 'X1')->first()->forceFill(['subcategory_id' => $squ->id, 'category_id' => $rig->id])->save();
        $this->actingAs($admin)->delete('/settings/product-categories/subcategories/'.$squ->id)->assertSessionHasErrors('subcategory');
        $this->actingAs($admin)->delete('/settings/product-categories/subcategories/'.$bar->id)->assertSessionHasNoErrors();
        $this->actingAs($admin)->delete('/settings/product-categories/categories/'.$rig->id)->assertSessionHasErrors('category');

        // A new choice for an attribute, a tag.
        $colour = Attribute::where('key', 'colour')->first();
        $this->actingAs($admin)->post('/settings/product-categories/attributes/'.$colour->id.'/options', ['value' => 'white', 'label' => ['en' => 'White', 'nl' => 'Wit']])->assertSessionHasNoErrors();
        $this->assertSame('Wit', AttributeOption::where('value', 'white')->first()->display('nl'));
        $this->actingAs($admin)->post('/settings/product-categories/tags', ['name' => ['en' => 'Silent']])->assertSessionHasNoErrors();

        foreach (['products.subcategory_added', 'products.subcategory_saved', 'products.subcategory_deleted', 'products.option_added', 'products.tag_added'] as $a) {
            $this->assertTrue(AuditLog::where('action', $a)->exists(), $a);
        }
        foreach (['en' => 'Product categories', 'fr' => 'Catégories de produits', 'es' => 'Categorías de productos'] as $loc => $t) {
            $admin->update(['locale' => $loc]);
            $this->actingAs($admin)->get('/settings/product-categories')->assertOk()->assertSee($t);
        }
    }

    public function test_the_migration_maps_items_and_can_be_undone(): void
    {
        $migration = require database_path('migrations/2026_10_23_000200_create_product_classification.php');
        $migration->down();
        Item::unguarded(fn () => Item::create(['number' => 'AUDFULLAC4XK1', 'name' => 'L-Acoustics 4x K1', 'category' => 'Audio', 'subcategory' => 'Full range & tops', 'serial' => false]));
        Item::unguarded(fn () => Item::create(['number' => 'RIGTRUNEXNH34200', 'name' => 'NEXT Truss - NH34 - 2 m', 'category' => 'Rigging', 'subcategory' => 'Truss - NH34 - Square - Black', 'serial' => false]));
        $migration->up();

        $k1 = Item::where('number', 'AUDFULLAC4XK1')->first();
        $this->assertSame(['Audio', 'Speakers', 'Full range & tops'], [$k1->category, $k1->subcategory, $k1->legacy_subcategory]);
        $this->assertSame('AUDFULLAC4XK1', $k1->number);

        $migration->down();
        $this->assertSame(['Audio', 'Full range & tops'], [Item::where('number', 'AUDFULLAC4XK1')->value('category'), Item::where('number', 'AUDFULLAC4XK1')->value('subcategory')]);
        $migration->up();
    }

    public function test_sales_products_have_their_own_lists(): void
    {
        $admin = $this->as('admin', ['products.classify', 'sales.edit_items']);
        $csm = Category::of('sales')->where('code', 'CSM')->first();
        $gen = $csm->subcategories()->first();

        // A dry-hire subcategory is not accepted for a sales product, and the reverse.
        $this->actingAs($admin)->post('/product-sales/items', ['brand' => 'Le Mark', 'type' => 'Gaffer 50 mm', 'unit' => 'roll', 'list_price' => '12', 'subcategory_id' => self::sub('SQU')])
            ->assertSessionHasErrors('subcategory_id');
        $this->actingAs($admin)->post('/product-sales/items', ['brand' => 'Le Mark', 'type' => 'Gaffer 50 mm', 'unit' => 'roll', 'list_price' => '12', 'category_id' => $csm->id, 'subcategory_id' => $gen->id,
            'search_words' => 'tape, gaffa'])->assertSessionHasNoErrors();
        $tape = SalesItem::where('type', 'Gaffer 50 mm')->firstOrFail();
        $this->assertSame(['Consumables', 'tape, gaffa'], [$tape->category, $tape->search_words]);
        $this->actingAs($this->as('asset-manager'))->post('/dry-hire/items', $this->form(['subcategory_id' => $gen->id]))->assertSessionHasErrors('subcategory_id');
        $this->actingAs($admin)->get('/product-sales/items?cat='.$csm->id)->assertOk()->assertSee('Gaffer 50 mm');

        // Settings: the sales tab manages its own lists.
        $this->actingAs($admin)->get('/settings/product-categories?c=sales')->assertOk()->assertSee('Consumables')->assertDontSee('Square truss');
        $this->actingAs($admin)->post('/settings/product-categories/categories', ['c' => 'sales', 'name' => ['en' => 'Batteries'], 'code' => 'BAT'])->assertSessionHasNoErrors();
        $this->assertSame('sales', Category::where('code', 'BAT')->value('catalogue'));
        $this->actingAs($admin)->post('/settings/product-categories/categories', ['c' => 'sales', 'name' => ['en' => 'Audio again'], 'code' => 'AUD'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/settings/product-categories/categories/'.$csm->id.'/subcategories', ['name' => ['en' => 'Tape'], 'code' => 'TAP'])->assertSessionHasNoErrors();

        // Import: category from the sales list (any language); a category with one active subcategory needs no subcategory.
        $r = (new SalesItemImport)->run([['Brand', 'Type', 'Category', 'Sub-Category', 'List price'],
            ['Duracell', 'AA', 'Batteries', null, '1'],
            ['Le Mark', 'Gaffer 25 mm', 'Verbruiksgoederen', 'Tape', '9'],
            ['Le Mark', 'Gaffer 19 mm', 'Consumables', null, '8'],
            ['X', 'Y', 'Square truss', null, '1']]);
        $this->assertSame(2, $r['created']);
        $this->assertSame('Tape', SalesItem::where('type', 'Gaffer 25 mm')->first()->productSubcategory->label('en'));
        $errors = collect($r['messages'])->where('level', 'error')->pluck('text', 'row');
        $this->assertStringContainsString('Choose a subcategory', $errors[2]); // Batteries has no subcategory yet
        $this->assertSame('General', SalesItem::where('type', 'Gaffer 19 mm')->first()->productSubcategory->label('en')); // the new Tape is still hidden
        $this->assertStringContainsString('Unknown category “Square truss”', $errors[5]);
    }
}
