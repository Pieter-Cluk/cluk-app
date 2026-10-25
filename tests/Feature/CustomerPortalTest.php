<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Finance\PreInvoice;
use App\Models\Product\Brand;
use App\Models\Product\Subcategory;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuote;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\Sales\IncomingMessage;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Models\Web\CustomerAccount;
use App\Notifications\Confirmation;
use App\Services\ProductSales\CustomerPrices;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Customer conditions (dry-hire %, limit rule) and the customer's website login: own prices, basket → deal, documents, colleagues. */
class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $co;

    private Item $k2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        CustomerPrices::flush();
        $this->owner = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en', 'first_name' => 'Elke']);
        $this->co = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'BE',
            'vat_number' => 'BE0123456749', 'account_owner_id' => $this->owner->id]);
        $this->k2 = new Item(['number' => 'K2', 'kind' => 'item', 'name' => 'L-Acoustics K2', 'unit' => 'pcs', 'serial' => false, 'stock_qty' => 24, 'day_price_cents' => 10000, 'active' => true]);
        $this->k2->placeIn(Subcategory::findOrFail(self::sub('SPK', 'AUD')));
        $this->k2->save();
    }

    private function login(string $email = 'jo@theatre.example', string $role = 'main'): CustomerAccount
    {
        $contact = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => $email, 'language' => 'en']);

        return CustomerAccount::create(['email' => $email, 'password' => 'secret-pass-1', 'first_name' => 'Jo', 'last_name' => 'Peeters', 'status' => 'active', 'role' => $role,
            'email_verified_at' => now(), 'contact_id' => $contact->id, 'company_id' => $this->co->id, 'locale' => 'en']);
    }

    public function test_discounts_up_to_the_limit_for_the_account_owner(): void
    {
        $url = route('companies.rental_discount', $this->co);
        $this->actingAs($this->owner)->put($url, ['rental_discount' => 8])->assertSessionHasNoErrors();
        $this->assertSame(800, $this->co->fresh()->rental_discount_bp);
        $this->actingAs($this->owner)->put($url, ['rental_discount' => 15])->assertSessionHasErrors('discount');
        $this->assertSame(800, $this->co->fresh()->rental_discount_bp);

        $other = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
        $this->actingAs($other)->put($url, ['rental_discount' => 5])->assertForbidden();

        $boss = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id'), 'locale' => 'en']);
        $this->actingAs($boss)->put($url, ['rental_discount' => 15])->assertSessionHasNoErrors();
        $this->assertSame(1500, $this->co->fresh()->rental_discount_bp);

        // The same rule for the sales conditions per brand.
        $shure = Brand::create(['name' => 'Shure', 'slug' => 'shure', 'kind' => 'other', 'active' => true]);
        $this->actingAs($this->owner)->post(route('companies.discounts.store', $this->co), ['target' => 'b:'.$shure->id, 'discount' => 9])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('companies.discounts.store', $this->co), ['target' => 'b:'.$shure->id, 'discount' => 20])->assertSessionHasErrors('discount');
        $this->actingAs($this->owner)->get(route('companies.show', $this->co))->assertOk()->assertSee('General discount on dry-hire')->assertSee('15%')->assertSee('You can give this customer up to 10%');

        // A new rental quote line gets the customer's dry-hire %.
        $deal = Deal::create(['number' => Deal::nextNumber(), 'title' => 'Gala', 'module' => 'dryhire', 'stage' => 'qualified', 'company_id' => $this->co->id, 'owner_id' => $this->owner->id]);
        $this->actingAs($this->owner)->post(route('quotes.store', $deal));
        $q = Quote::firstOrFail();
        $this->actingAs($this->owner)->post(route('quotes.lines.add', $q), ['kind' => 'item', 'number' => 'K2', 'qty' => 2])->assertRedirect();
        $this->assertSame(1500, $q->lines()->first()->discount_bp);
    }

    public function test_own_prices_and_a_basket_becomes_a_deal_with_a_draft_quote(): void
    {
        $this->co->forceFill(['rental_discount_bp' => 1000])->save();
        $a = $this->login();
        $this->actingAs($a, 'customer')->get('/site/dry-hire')->assertOk()->assertSee('"disc":1000', false);
        $this->get('/site/dry-hire/'.$this->k2->id.'-l-acoustics-k2')->assertSee('€ 90,00');

        $this->postJson('/site/account/basket/dryhire/merge', ['lines' => [['id' => $this->k2->id, 'qty' => 4]], 'first_day' => now()->addDays(3)->toDateString(), 'last_day' => now()->addDays(3)->toDateString()])->assertOk();
        $this->postJson('/site/account/basket/dryhire/send', ['message' => 'Stage left'])->assertOk();

        $deal = Deal::firstOrFail();
        $q = Quote::firstOrFail();
        $this->assertSame(['dryhire', 'website', $this->owner->id, $this->co->id], [$deal->module, $deal->source, $deal->owner_id, $deal->company_id]);
        $this->assertSame([$deal->id, 'draft', 1000], [$q->deal_id, $q->status, $q->lines->first()->discount_bp]);
        $this->assertSame(36000, $q->net_cents); // 4 × € 100 − 10%
        $this->assertSame(['converted', $deal->id], [IncomingMessage::first()->status, IncomingMessage::first()->deal_id]);
        Notification::assertSentTo($this->owner, Confirmation::class);
    }

    public function test_my_cluk_shows_quotes_orders_and_invoices_of_the_company_only(): void
    {
        $a = $this->login();
        $other = Company::create(['name' => 'Other BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $mk = fn (Company $c, string $no, string $st) => PreInvoice::create(['number' => 'PF-'.$no, 'source_type' => 'rental', 'source_id' => 1, 'module' => 'dryhire', 'company_id' => $c->id, 'description' => 'Gala',
            'status' => $st, 'lines' => [['d' => 'K2', 'q' => '4', 'unit_cents' => 10000, 'amount_cents' => 40000]], 'vat_code' => '21', 'vat_rate_bp' => 2100, 'net_cents' => 40000, 'cost_cents' => 0,
            'vat_cents' => 8400, 'gross_cents' => 48400, 'payment_days' => 30, 'invoice_number' => '2026-'.$no, 'invoice_date' => today()->subDays(40), 'due_date' => today()->subDays(10)]);
        $mine = $mk($this->co, '0007', 'sent');
        $theirs = $mk($other, '0008', 'sent');

        $this->actingAs($a, 'customer')->get('/site/account')->assertOk()->assertSee('My invoices')->assertSee('2026-0007')->assertSee('Overdue')->assertDontSee('2026-0008');
        $this->get('/site/account/invoices/'.$mine->id)->assertOk()->assertSee('Invoice 2026-0007')->assertSee('€ 484,00');
        $this->get('/site/account/invoices/'.$theirs->id)->assertNotFound();
    }

    public function test_main_contact_invites_colleagues_and_look_only_cannot_order(): void
    {
        $main = $this->login();
        $this->actingAs($main, 'customer')->post('/site/account/colleagues', ['first_name' => 'An', 'email' => 'an@theatre.example', 'role' => 'view'])->assertRedirect();
        $an = CustomerAccount::where('email', 'an@theatre.example')->firstOrFail();
        $this->assertSame(['active', 'view', $this->co->id, $main->id], [$an->status, $an->role, $an->company_id, $an->invited_by_id]);
        Notification::assertSentOnDemand(Confirmation::class, fn ($n, $ch, $to) => $to->routes['mail'] === 'an@theatre.example' && str_contains($n->subject, 'invites you'));
        $this->get('/site/account')->assertSee('My colleagues')->assertSee('an@theatre.example');

        // "Look only" can fill a basket but not send it.
        $this->actingAs($an, 'customer')->postJson('/site/account/basket/dryhire/merge', ['lines' => [['id' => $this->k2->id, 'qty' => 1]], 'first_day' => now()->addDays(3)->toDateString(), 'last_day' => now()->addDays(3)->toDateString()])->assertOk();
        $this->postJson('/site/account/basket/dryhire/send', [])->assertStatus(422);
        $this->get('/site/account')->assertSee('can look but not order')->assertDontSee('My colleagues');
        $this->post('/site/account/colleagues', ['first_name' => 'X', 'email' => 'x@theatre.example', 'role' => 'order'])->assertForbidden();

        // The main contact lets An order, then takes the login away.
        $this->actingAs($main, 'customer')->post('/site/account/colleagues/'.$an->id, ['role' => 'order'])->assertRedirect();
        $this->assertSame('order', $an->fresh()->role);
        $this->post('/site/account/colleagues/'.$an->id, ['access' => 'off'])->assertRedirect();
        $this->assertSame('blocked', $an->fresh()->status);

        // Staff can set the role too.
        $boss = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $this->actingAs($boss, 'web')->post(route('web-accounts.role', $main), ['role' => 'order'])->assertRedirect();
        $this->assertSame('order', $main->fresh()->role);
    }

    public function test_a_website_sales_order_is_a_won_deal(): void
    {
        $a = $this->login();
        $mic = new SalesItem(['number' => 'S-300001', 'brand' => 'Shure', 'type' => 'SM58', 'unit' => 'piece', 'list_price_cents' => 11900, 'stock_qty' => 10, 'active' => true]);
        $mic->placeIn(Subcategory::whereHas('category', fn ($c) => $c->where('catalogue', 'sales')->where('code', 'AUD'))->firstOrFail());
        $mic->save();
        $this->actingAs($a, 'customer')->postJson('/site/account/basket/sales/set', ['id' => $mic->id, 'qty' => 1])->assertOk();
        $this->postJson('/site/account/basket/sales/order', ['po_number' => 'PO-9'])->assertOk();
        $q = SalesQuote::firstOrFail();
        $this->assertNotNull($q->deal_id);
        $this->assertSame(['sales', 'won', $this->owner->id], [$q->deal->module, $q->deal->stage, $q->owner_id]);
        $this->assertSame(48, strlen($q->token));
    }
}
