<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product\Brand;
use App\Models\Product\Subcategory;
use App\Models\ProductSales\CustomerDiscount;
use App\Models\ProductSales\SalesItem;
use App\Models\Sales\IncomingMessage;
use App\Models\Web\CustomerAccount;
use App\Notifications\Confirmation;
use App\Services\ProductSales\CustomerPrices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Website › Sales: categories, brands, search with suggestions, filters, customer prices, quote requests. */
class WebsiteSalesTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $no, string $brand, string $type, int $price, int $stock = 5, string $cat = 'AUD', array $extra = []): SalesItem
    {
        $i = new SalesItem(array_merge(['number' => $no, 'brand' => $brand, 'type' => $type, 'unit' => 'piece', 'list_price_cents' => $price, 'stock_qty' => $stock, 'active' => true], $extra));
        $sub = Subcategory::whereHas('category', fn ($c) => $c->where('catalogue', 'sales')->where('code', $cat))->firstOrFail();
        $i->placeIn($sub);
        $i->save();

        return $i;
    }

    protected function setUp(): void
    {
        parent::setUp();
        CustomerPrices::flush();
    }

    public function test_home_category_brand_and_product_pages(): void
    {
        $sm58 = $this->product('S-200001', 'Shure', 'SM58', 11900, 5, 'AUD', ['manufacturer_code' => 'SM58-LCE', 'description' => 'Dynamic vocal microphone']);
        $this->product('S-200002', 'Le Mark', 'Gaffa tape black', 1190, 200, 'CSM');
        $this->product('S-200003', 'Shure', 'Old mic', 5000, 0, 'AUD', ['active' => false]);

        $this->get('/site/sales')->assertOk()->assertSee('Audio')->assertSee('Consumables')->assertSee('Shure');
        $this->get('/site/sales/audio')->assertOk()->assertSee('Shure SM58')->assertSee('S-200001')->assertSee('SM58-LCE')->assertSee('€ 119,00')->assertDontSee('Old mic')->assertDontSee('Gaffa tape black');
        $this->get('/site/sales/brands')->assertOk()->assertSee('Shure')->assertSee('Le Mark');
        $this->get('/site/sales/brands/'.Brand::where('name', 'Shure')->value('slug'))->assertOk()->assertSee('Shure SM58');
        $slug = $sm58->id.'-shure-sm58';
        $this->get('/site/sales/product/'.$slug)->assertOk()->assertSee('Dynamic vocal microphone')->assertSee('Manufacturer code')->assertSee('"@type":"Product"', false);
        $this->get('/site/sales/product/'.$sm58->id)->assertRedirect('/site/sales/product/'.$slug);
        $this->get('/site/sales/nope')->assertNotFound();
        $this->get('/site/sales/audio?lang=nl')->assertOk()->assertSee('Bestelcode')->assertSee('Nu op voorraad');
    }

    public function test_search_suggestions_and_filters(): void
    {
        $this->product('S-200001', 'Shure', 'SM58', 11900, 5, 'AUD', ['manufacturer_code' => 'SM58-LCE']);
        $this->product('S-200004', 'Shure', 'Beta 58A', 16500, 0);
        $this->product('S-200005', 'Sennheiser', 'e935', 14900, 3);

        $this->get('/site/sales/search?q=sm58')->assertOk()->assertSee('Shure SM58')->assertDontSee('Sennheiser e935');
        $this->get('/site/sales/search?q=SM58-LCE')->assertOk()->assertSee('Shure SM58');
        $this->getJson('/site/sales/suggest?q=shu')->assertOk()->assertJsonCount(2, 'products')->assertJsonPath('brands.0.name', 'Shure')->assertJsonPath('total', 2);

        $shure = Brand::where('name', 'Shure')->value('id');
        $this->get('/site/sales/audio?brands[]='.$shure)->assertOk()->assertSee('Beta 58A')->assertDontSee('Sennheiser e935');
        $this->get('/site/sales/audio?stock=1')->assertOk()->assertSee('Shure SM58')->assertDontSee('Beta 58A');
        $this->get('/site/sales/audio?min=150&sort=pd')->assertOk()->assertSee('Beta 58A')->assertDontSee('Shure SM58');
    }

    public function test_signed_in_customers_see_their_own_price_next_to_the_list_price(): void
    {
        $sm58 = $this->product('S-200001', 'Shure', 'SM58', 10000);
        $company = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        CustomerDiscount::create(['company_id' => $company->id, 'brand_id' => Brand::where('name', 'Shure')->value('id'), 'discount_bp' => 2000]);
        $a = CustomerAccount::create(['email' => 'jo@theatre.example', 'password' => 'secret-pass-1', 'first_name' => 'Jo', 'last_name' => 'Peeters', 'status' => 'active',
            'email_verified_at' => now(), 'company_id' => $company->id, 'locale' => 'en']);

        $this->get('/site/sales/audio')->assertOk()->assertSee('€ 100,00')->assertDontSee('€ 80,00');
        $this->actingAs($a, 'customer')->get('/site/sales/audio')->assertOk()->assertSee('€ 80,00')->assertSee('<s>€ 100,00</s>', false)->assertSee('Your price');
        $this->actingAs($a, 'customer')->get('/site/sales/product/'.$sm58->id.'-shure-sm58')->assertOk()->assertSee('€ 80,00');
    }

    public function test_a_guest_asks_a_quote_that_lands_in_incoming(): void
    {
        Notification::fake();
        $sm58 = $this->product('S-200001', 'Shure', 'SM58', 11900);
        $off = $this->product('S-200003', 'Shure', 'Old mic', 5000, 0, 'AUD', ['active' => false]);

        $this->postJson('/site/sales/request', ['name' => 'Lotte Peeters', 'email' => 'lotte@example.be', 'delivery' => 'ship',
            'lines' => [['id' => $sm58->id, 'qty' => 4], ['id' => $off->id, 'qty' => 1]]])->assertOk();
        $m = IncomingMessage::firstOrFail();
        $this->assertSame(['website', 'sales'], [$m->channel, $m->module]);
        $this->assertCount(1, $m->payload['lines']);
        $this->assertSame(47600, $m->payload['total_cents']);
        $this->assertStringContainsString('4 × Shure SM58 (S-200001)', $m->body);
        Notification::assertSentOnDemand(Confirmation::class);

        $this->postJson('/site/sales/request', ['name' => 'X', 'email' => 'x@example.be', 'lines' => []])->assertStatus(422);
    }
}
