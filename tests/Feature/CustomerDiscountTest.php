<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\ProductSales\CustomerDiscount;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuote;
use App\Models\Role;
use App\Models\User;
use App\Models\Web\CustomerAccount;
use App\Services\ProductSales\CustomerPrices;
use App\Services\Web\Baskets;
use App\Services\Web\Prices;
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

    public function test_conditions_and_their_priority(): void
    {
        $admin = $this->as('admin');
        $shure = Brand::where('name', 'Shure')->firstOrFail();
        $audio = Category::of('sales')->where('code', 'AUD')->firstOrFail();
        $this->shure->update(['category_id' => $audio->id]);
        $this->other->update(['category_id' => $audio->id]);
        $url = '/companies/'.$this->customer->id.'/discounts';
        $this->actingAs($this->as('sales'))->post($url, ['target' => 'default', 'discount' => '5'])->assertForbidden(); // needs "approve low margin"

        // Default only.
        $this->actingAs($admin)->post($url, ['target' => 'default', 'discount' => '5', 'note' => 'Season contract'])->assertSessionHasNoErrors();
        $this->assertSame(9500, CustomerPrices::priceCents($this->customer, $this->shure));
        // Category beats default.
        $this->actingAs($admin)->post($url, ['target' => 'c:'.$audio->id, 'discount' => '8'])->assertSessionHasNoErrors();
        $this->assertSame(9200, CustomerPrices::priceCents($this->customer, $this->shure));
        $this->assertSame(1840, CustomerPrices::priceCents($this->customer, $this->other));
        // Brand beats category; setting it again changes it.
        $this->actingAs($admin)->post($url, ['target' => 'b:'.$shure->id, 'discount' => '15'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($url, ['target' => 'b:'.$shure->id, 'discount' => '12.5'])->assertSessionHasNoErrors();
        $this->assertSame(8750, CustomerPrices::priceCents($this->customer, $this->shure));
        $this->assertSame(1840, CustomerPrices::priceCents($this->customer, $this->other)); // Le Mark: still the category
        // A net price beats everything.
        $this->actingAs($admin)->post('/companies/'.$this->customer->id.'/net-prices', ['item' => 'S-100001 · Shure SM58', 'net_price' => '79.90'])->assertSessionHasNoErrors();
        $this->assertSame(7990, CustomerPrices::priceCents($this->customer, $this->shure));
        $this->assertSame(4, CustomerDiscount::where('company_id', $this->customer->id)->count());
        $this->actingAs($admin)->post('/companies/'.$this->customer->id.'/net-prices', ['item' => 'S-999999', 'net_price' => '1'])->assertSessionHasErrors('item');
        $this->assertSame(10000, CustomerPrices::priceCents(null, $this->shure));
        $this->assertSame(5, AuditLog::where('action', 'sales.customer_discount')->count());
        $this->assertStringContainsString('15% → 12.5%', json_encode(AuditLog::where('action', 'sales.customer_discount')->orderBy('id')->get()[3]->data, JSON_UNESCAPED_UNICODE));

        $this->actingAs($admin)->get('/companies/'.$this->customer->id)->assertOk()->assertSee('Standard sales conditions')->assertSee('12,5%')->assertSee('Default discount (all products)')->assertSee('Net prices');

        // New quote lines for this customer get the same prices (net price as the line price).
        $q = SalesQuote::create(['number' => SalesQuote::nextNumber(), 'status' => 'draft', 'company_id' => $this->customer->id, 'title' => 'Mics', 'language' => 'en']);
        $this->actingAs($admin)->post('/sales/sales-quotes/'.$q->id.'/lines', ['kind' => 'item', 'item_id' => $this->shure->id, 'qty' => 2])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/sales/sales-quotes/'.$q->id.'/lines', ['kind' => 'item', 'item_id' => $this->other->id, 'qty' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2 * 7990 + 1840, $q->fresh()->net_cents);

        // Removing the net price falls back to the brand %.
        $net = CustomerDiscount::whereNotNull('sales_item_id')->firstOrFail();
        $this->actingAs($admin)->delete('/companies/'.$this->customer->id.'/discounts/'.$net->id)->assertSessionHasNoErrors();
        $this->assertSame(8750, CustomerPrices::priceCents($this->customer, $this->shure));
    }

    public function test_the_website_shows_and_orders_at_the_customer_price(): void
    {
        $brand = Brand::where('name', 'Shure')->firstOrFail();
        $this->actingAs($this->as('admin'))->post('/companies/'.$this->customer->id.'/discounts', ['target' => 'b:'.$brand->id, 'discount' => '20'])->assertSessionHasNoErrors();
        $this->actingAs($this->as('admin'))->post('/companies/'.$this->customer->id.'/net-prices', ['item' => 'S-100002', 'net_price' => '15'])->assertSessionHasNoErrors();
        $contact = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@theatre.example', 'language' => 'en']);
        $a = CustomerAccount::create(['email' => 'jo@theatre.example', 'password' => 'secret-pass-1', 'first_name' => 'Jo', 'last_name' => 'Peeters', 'status' => 'active',
            'email_verified_at' => now(), 'contact_id' => $contact->id, 'company_id' => $this->customer->id, 'locale' => 'en']);

        Baskets::set($a, 'sales', $this->shure->id, 3);
        Baskets::set($a, 'sales', $this->other->id, 1);
        $show = Baskets::show($a, 'sales');
        $line = collect($show['lines'])->firstWhere('id', $this->shure->id);
        $this->assertSame(8000, $line['price_cents']);
        $this->assertSame(10000, $line['list_price_cents']);
        $this->assertSame(20.0, $line['discount_pct']);
        $this->assertSame(24000 + 1500, $show['net_cents']);

        // One call for the catalogue: the same prices; guests and not-yet-approved logins get the list price.
        $p = Prices::for($a, SalesItem::whereIn('id', [$this->shure->id, $this->other->id])->get());
        $this->assertSame(['list_cents' => 10000, 'price_cents' => 8000, 'discount_pct' => 20.0], $p[$this->shure->id]);
        $this->assertSame(['list_cents' => 2000, 'price_cents' => 1500, 'discount_pct' => 25.0], $p[$this->other->id]);
        $this->assertSame(['list_cents' => 10000, 'price_cents' => 10000, 'discount_pct' => null], Prices::for(null, $this->shure));
        $pending = CustomerAccount::create(['email' => 'new@theatre.example', 'password' => 'secret-pass-1', 'first_name' => 'An', 'status' => 'pending', 'company_id' => $this->customer->id]);
        $this->assertSame(10000, Prices::for($pending, $this->shure)['price_cents']);

        $q = Baskets::submitSales($a);
        $this->assertSame(25500, $q->net_cents); // exactly what the customer saw
        $this->assertSame(2000, $q->lines->firstWhere('sales_item_id', $this->shure->id)->discount_bp);
        $this->assertSame(1500, $q->lines->firstWhere('sales_item_id', $this->other->id)->unit_cents);
    }
}
