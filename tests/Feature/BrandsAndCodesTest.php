<?php

namespace Tests\Feature;

use App\Imports\SalesItemImport;
use App\Models\Company;
use App\Models\Product\Brand;
use App\Models\ProductSales\SalesItem;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Purchasing\Purchasing;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Sales catalogue: the brands list, the manufacturer's code and the supplier's article number. */
class BrandsAndCodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    public function test_typing_a_brand_uses_or_adds_it_in_the_list(): void
    {
        $admin = $this->as('admin');
        $this->actingAs($admin)->post('/product-sales/items', ['brand' => 'Shure', 'type' => 'SM58', 'manufacturer_code' => 'SM58-LCE', 'unit' => 'piece', 'list_price' => '119'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/product-sales/items', ['brand' => 'shure', 'type' => 'SM57', 'unit' => 'piece', 'list_price' => '109'])->assertSessionHasNoErrors();
        $this->assertSame(1, Brand::count());
        $shure = Brand::firstOrFail();
        $this->assertSame(['Shure', 'Shure'], SalesItem::orderBy('id')->pluck('brand')->all()); // the list's spelling
        $this->assertSame([$shure->id, $shure->id], SalesItem::orderBy('id')->pluck('brand_id')->all());
        $this->assertSame('shure', $shure->slug);
        $this->assertSame('other', $shure->kind);

        // Renaming the brand changes the text on its products; a new slug keeps the old one.
        $this->actingAs($admin)->put('/settings/product-categories/brands/'.$shure->id, ['name' => 'Shure Inc.', 'slug' => 'shure-inc', 'kind' => 'distributed', 'intro' => ['en' => 'Microphones since 1925.'], 'active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Shure Inc.', SalesItem::first()->brand);
        $shure->refresh();
        $this->assertSame(['shure'], $shure->old_slugs);
        $this->assertSame('Microphones since 1925.', $shure->introText('nl')); // English as fallback
        $this->assertSame($shure->id, Brand::findByName('shure')->id); // old slug still found

        // A brand in use can't be deleted; merging moves the products.
        $other = Brand::create(['name' => 'SHURE', 'slug' => 'shure-2', 'kind' => 'other']);
        SalesItem::first()->update(['brand' => 'SHURE']);
        $this->assertSame($other->id, SalesItem::first()->brand_id);
        $this->actingAs($admin)->delete('/settings/product-categories/brands/'.$other->id)->assertSessionHasErrors('brand');
        $this->actingAs($admin)->post('/settings/product-categories/brands/'.$other->id.'/merge', ['into' => $shure->id])->assertSessionHasNoErrors();
        $this->assertNull(Brand::find($other->id));
        $this->assertSame(2, SalesItem::where('brand_id', $shure->id)->count());
        $this->assertSame('Shure Inc.', SalesItem::first()->brand);

        $this->actingAs($admin)->get('/settings/product-categories/brands')->assertOk()->assertSee('Shure Inc.')->assertSee('We distribute');
        $this->actingAs($admin)->get('/product-sales/items?brand='.$shure->id)->assertOk()->assertSee('SM58-LCE');
    }

    public function test_brands_need_the_classify_right(): void
    {
        $this->actingAs($this->as('sales'))->get('/settings/product-categories/brands')->assertForbidden();
        $this->actingAs($this->as('sales'))->post('/settings/product-categories/brands', ['name' => 'X', 'kind' => 'own'])->assertForbidden();
    }

    public function test_search_on_manufacturer_code_ean_and_supplier_article_number(): void
    {
        $admin = $this->as('admin');
        $sup = Company::create(['name' => 'Demo Audio Distribution BV', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en', 'email' => 'orders@dad.example']);
        $mic = SalesItem::create(['number' => 'S-100001', 'brand' => 'Shure', 'type' => 'Vocal mic', 'manufacturer_code' => 'SM58-LCE', 'ean' => '0042406050250', 'unit' => 'piece', 'list_price_cents' => 11900, 'active' => true]);
        SalesItem::create(['number' => 'S-100002', 'brand' => 'Sennheiser', 'type' => 'e935', 'unit' => 'piece', 'list_price_cents' => 15900, 'active' => true]);
        $this->actingAs($admin)->post('/product-sales/items/'.$mic->id.'/suppliers', ['company_id' => $sup->id, 'discount' => '35', 'lead_days' => 3, 'preferred' => '1', 'supplier_ref' => 'DAD-778812'])->assertSessionHasNoErrors();
        // Saving the supplier again without a number keeps the one we have.
        $this->actingAs($admin)->post('/product-sales/items/'.$mic->id.'/suppliers', ['company_id' => $sup->id, 'discount' => '36', 'lead_days' => 3])->assertSessionHasNoErrors();
        $this->assertSame('DAD-778812', $mic->suppliers()->first()->pivot->supplier_ref);

        foreach (['SM58-LCE', 'sm58lce', 'sm58 lce', '0042406050250', 'DAD-778812', 'shure vocal'] as $q) {
            $page = $this->actingAs($admin)->get('/product-sales/items?q='.urlencode($q))->assertOk();
            $this->assertStringContainsString('S-100001', $page->getContent(), $q);
            $this->assertStringNotContainsString('S-100002', $page->getContent(), $q);
        }

        // The supplier gets their own article number and the manufacturer's code on the price request.
        Purchasing::send(Purchasing::create([['item' => $mic->fresh('suppliers'), 'qty' => 4]], $admin), $admin);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'orders@dad.example'
            && str_contains($n->rows[0][1], 'DAD-778812') && str_contains($n->rows[0][1], 'SM58-LCE') && str_contains($n->rows[0][1], 'S-100001'));
        $this->assertSame(1, PurchaseRequest::count());
    }

    public function test_import_reads_the_manufacturer_code_and_brand(): void
    {
        $this->actingAs($this->as('admin'));
        $report = (new SalesItemImport)->run([['Brand', 'Type', 'Manufacturer code', 'List price'], ['Shure', 'Vocal mic', 'SM58-LCE', '119'], ['Shure', 'Instrument mic', 'SM57-LCE', '109']]);
        $this->assertSame(2, $report['created']);
        $this->assertSame(['SM58-LCE', 'SM57-LCE'], SalesItem::orderBy('id')->pluck('manufacturer_code')->all());
        $this->assertSame(1, Brand::count());
    }
}
