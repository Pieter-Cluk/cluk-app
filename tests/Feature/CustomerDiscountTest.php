<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product\Brand;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuote;
use App\Models\Role;
use App\Models\User;
use App\Models\Web\CustomerAccount;
use App\Services\ProductSales\CustomerPrices;
use App\Services\Web\Baskets;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Customer card › sales discounts per brand: the website shows the customer's own price, new quote lines get it too. */
class CustomerDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Company $customer;

    private SalesItem $shure;

    private SalesItem $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        CustomerPrices::flush();
        $this->customer = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $sup = Company::create(['name' => 'Demo Audio Distribution BV', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en', 'email' => 'orders@dad.example']);
        $this->shure = SalesItem::create(['number' => 'S-100001', 'brand' => 'Shure', 'type' => 'SM58', 'unit' => 'piece', 'list_price_cents' => 10000, 'stock_qty' => 10, 'active' => true]);
        $this->shure->suppliers()->attach($sup->id, ['discount_bp' => 3500, 'lead_days' => 3, 'preferred' => true]);
        $this->other = SalesItem::create(['number' => 'S-100002', 'brand' => 'Le Mark', 'type' => 'Gaffa tape', 'unit' => 'roll', 'list_price_cents' => 2000, 'stock_qty' => 10, 'active' => true]);
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    public function test_discounts_per_brand_and_for_every_other_brand(): void
    {
        $admin = $this->as('admin');
        $brand = Brand::where('name', 'Shure')->firstOrFail();
        $url = '/companies/'.$this->customer->id.'/discounts';
        $this->actingAs($this->as('sales'))->post($url, ['brand_id' => $brand->id, 'discount' => '15'])->assertForbidden(); // needs "approve low margin"

        $this->actingAs($admin)->post($url, ['brand_id' => $brand->id, 'discount' => '15'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($url, ['discount' => '5', 'note' => 'Season contract'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($url, ['brand_id' => $brand->id, 'discount' => '12.5'])->assertSessionHasNoErrors(); // changes, no second row
        $this->assertSame(1250, CustomerPrices::discountBp($this->customer, $this->shure));
        $this->assertSame(8750, CustomerPrices::priceCents($this->customer, $this->shure));
        $this->assertSame(1900, CustomerPrices::priceCents($this->customer, $this->other)); // "all other brands"
        $this->assertSame(10000, CustomerPrices::priceCents(null, $this->shure));
        $this->assertSame(3, AuditLog::where('action', 'sales.customer_discount')->count());
        $this->assertStringContainsString('15% → 12.5%', json_encode(AuditLog::where('action', 'sales.customer_discount')->latest('id')->first()->data, JSON_UNESCAPED_UNICODE));

        $this->actingAs($admin)->get('/companies/'.$this->customer->id)->assertOk()->assertSee('Sales discounts per brand')->assertSee('12,5%')->assertSee('All other brands');

        // A new line on a quote for this customer gets the discount.
        $q = SalesQuote::create(['number' => SalesQuote::nextNumber(), 'status' => 'draft', 'company_id' => $this->customer->id, 'title' => 'Mics', 'language' => 'en']);
        $this->actingAs($admin)->post('/product-sales/quotes/'.$q->id.'/lines', ['kind' => 'item', 'item_id' => $this->shure->id, 'qty' => 2])->assertSessionHasNoErrors();
        $this->assertSame(1250, $q->lines()->first()->discount_bp);
        $this->assertSame(17500, $q->fresh()->net_cents);
    }

    public function test_the_website_shows_and_orders_at_the_customer_price(): void
    {
        $brand = Brand::where('name', 'Shure')->firstOrFail();
        $this->actingAs($this->as('admin'))->post('/companies/'.$this->customer->id.'/discounts', ['brand_id' => $brand->id, 'discount' => '20'])->assertSessionHasNoErrors();
        $contact = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@theatre.example', 'language' => 'en']);
        $a = CustomerAccount::create(['email' => 'jo@theatre.example', 'password' => 'secret-pass-1', 'first_name' => 'Jo', 'last_name' => 'Peeters', 'status' => 'active',
            'email_verified_at' => now(), 'contact_id' => $contact->id, 'company_id' => $this->customer->id, 'locale' => 'en']);

        Baskets::set($a, 'sales', $this->shure->id, 3);
        Baskets::set($a, 'sales', $this->other->id, 1);
        $show = Baskets::show($a, 'sales');
        $line = collect($show['lines'])->firstWhere('id', $this->shure->id);
        $this->assertSame(8000, $line['price_cents']);
        $this->assertSame(10000, $line['list_price_cents']);
        $this->assertEquals(20, $line['discount_pct']);
        $this->assertSame(24000 + 2000, $show['net_cents']);

        $q = Baskets::submitSales($a);
        $this->assertSame(26000, $q->net_cents);
        $this->assertSame(2000, $q->lines->firstWhere('sales_item_id', $this->shure->id)->discount_bp);
    }
}
