<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use App\Models\ProductSales\SalesItem;
use App\Models\Sales\IncomingMessage;
use App\Models\Web\CustomerAccount;
use App\Services\ProductSales\CustomerPrices;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Website › customer accounts: register, log in, My CLUK, and the two baskets on the server. */
class WebsiteAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        CustomerPrices::flush();
    }

    private function account(string $status = 'active'): CustomerAccount
    {
        $company = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $contact = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@theatre.example', 'language' => 'en']);

        return CustomerAccount::create(['email' => 'jo@theatre.example', 'password' => 'secret-pass-1', 'first_name' => 'Jo', 'last_name' => 'Peeters', 'status' => $status,
            'email_verified_at' => now(), 'contact_id' => $contact->id, 'company_id' => $company->id, 'locale' => 'en']);
    }

    public function test_register_then_wait_for_approval(): void
    {
        $this->get('/site/account/register')->assertOk()->assertSee('Register');
        $this->post('/site/account/register', ['first_name' => 'Lotte', 'last_name' => 'Peeters', 'email' => 'lotte@example.be', 'company_name' => 'Peeters Events',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1', 'terms' => 1])->assertRedirect('/site/account/login');
        $a = CustomerAccount::where('email', 'lotte@example.be')->firstOrFail();
        $this->assertSame('pending', $a->status);

        $a->forceFill(['email_verified_at' => now()])->save();
        $this->post('/site/account/login', ['email' => 'lotte@example.be', 'password' => 'long-enough-1'])->assertSessionHasErrors('email');
        $this->assertGuest('customer');
        $this->post('/site/account/register', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'lotte@example.be', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors(['email', 'password', 'terms']);
    }

    public function test_log_in_my_cluk_and_log_out(): void
    {
        $this->account();
        $this->get('/site/account')->assertRedirect('/site/account/login');
        $this->post('/site/account/login', ['email' => 'jo@theatre.example', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post('/site/account/login', ['email' => 'jo@theatre.example', 'password' => 'secret-pass-1'])->assertRedirect('/site/account');
        $this->assertAuthenticated('customer');
        $this->get('/site/account')->assertOk()->assertSee('Hi Jo')->assertSee('Demo Theatre vzw')->assertSee('My orders');
        $this->get('/site/dry-hire')->assertOk()->assertSee('My CLUK');
        $this->post('/site/account/profile', ['first_name' => 'Joke', 'last_name' => 'Peeters', 'locale' => 'nl'])->assertRedirect();
        $this->assertSame(['Joke', 'nl'], [CustomerAccount::first()->first_name, CustomerAccount::first()->locale]);
        $this->post('/site/account/logout')->assertRedirect('/site');
        $this->assertGuest('customer');
        $this->post('/site/account/forgot', ['email' => 'nobody@example.be'])->assertSessionHas('notice');
    }

    public function test_signed_in_dry_hire_request_from_the_server_basket(): void
    {
        $a = $this->account();
        $k2 = new Item(['number' => 'K2', 'kind' => 'item', 'name' => 'L-Acoustics K2', 'unit' => 'pcs', 'serial' => false, 'stock_qty' => 24, 'day_price_cents' => 14500, 'active' => true]);
        $k2->placeIn(Subcategory::findOrFail(self::sub('SPK', 'AUD')));
        $k2->save();

        $this->actingAs($a, 'customer');
        $this->postJson('/site/account/basket/dryhire/merge', ['lines' => [['id' => $k2->id, 'qty' => 8]], 'first_day' => now()->addDays(2)->toDateString(), 'last_day' => now()->addDays(4)->toDateString()])
            ->assertOk()->assertJsonPath('lines.0.qty', 8)->assertJsonPath('days', 3);
        $this->postJson('/site/account/basket/dryhire/set', ['id' => $k2->id, 'qty' => 12])->assertOk()->assertJsonPath('lines.0.qty', 12);
        $this->postJson('/site/account/basket/dryhire/send', ['message' => 'Gent'])->assertOk()->assertJsonPath('ok', true);
        $m = IncomingMessage::firstOrFail();
        $this->assertSame([12, 'dryhire'], [$m->payload['lines'][0]['qty'], $m->module]);
        $this->getJson('/site/account/basket/dryhire')->assertOk()->assertJsonCount(0, 'lines');
    }

    public function test_signed_in_sales_order_on_invoice(): void
    {
        $this->seed(RoleSeeder::class);
        $a = $this->account();
        $mic = new SalesItem(['number' => 'S-300001', 'brand' => 'Shure', 'type' => 'SM58', 'unit' => 'piece', 'list_price_cents' => 11900, 'stock_qty' => 10, 'active' => true]);
        $mic->placeIn(Subcategory::whereHas('category', fn ($c) => $c->where('catalogue', 'sales')->where('code', 'AUD'))->firstOrFail());
        $mic->save();

        $this->actingAs($a, 'customer')->get('/site/sales')->assertOk()->assertSee('"customer":{"name":"Jo Peeters"', false);
        $this->postJson('/site/account/basket/sales/set', ['id' => $mic->id, 'qty' => 2])->assertOk()->assertJsonPath('net_cents', 23800);
        $this->postJson('/site/account/basket/sales/order', ['po_number' => 'PO-77'])->assertOk()->assertJsonPath('ok', true);
        $this->getJson('/site/account/basket/sales')->assertOk()->assertJsonCount(0, 'lines');
        $this->get('/site/account')->assertOk()->assertSee('PO-77');
    }

    public function test_guests_cannot_use_the_account_baskets(): void
    {
        $this->getJson('/site/account/basket/sales')->assertStatus(401);
        $this->postJson('/site/account/basket/sales/order')->assertStatus(401);
    }
}
