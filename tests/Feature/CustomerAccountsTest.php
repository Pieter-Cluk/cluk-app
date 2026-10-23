<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use App\Models\ProductSales\SalesItem;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Role;
use App\Models\Sales\IncomingMessage;
use App\Models\User;
use App\Models\Web\Basket;
use App\Models\Web\CustomerAccount;
use App\Notifications\Confirmation;
use App\Services\Web\Baskets;
use App\Services\Web\CustomerAuth;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Route::middleware(['web', 'customer.auth'])->get('/_test/account', fn () => 'my account '.CustomerAuth::current()?->email);
    }

    private function staff(string $role = 'admin'): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function register(array $extra = []): CustomerAccount
    {
        return CustomerAuth::register(array_merge(['email' => 'Jo@Theatre.example', 'password' => 'secret-pass-1', 'first_name' => 'Jo', 'last_name' => 'Peeters',
            'company_name' => 'Demo Theatre vzw', 'vat_number' => 'BE0123456789', 'locale' => 'nl'], $extra));
    }

    private function verifyLink(CustomerAccount $a): string
    {
        return URL::temporarySignedRoute('customer.verify', now()->addHours(48), ['account' => $a->id, 'hash' => sha1($a->email)]);
    }

    /** Registered, email confirmed and approved by staff with a new company. */
    private function activeAccount(): CustomerAccount
    {
        $a = $this->register();
        $this->get($this->verifyLink($a))->assertRedirect();
        $this->actingAs($this->staff())->post('/companies/website-logins/'.$a->id.'/approve', ['company' => 'new'])->assertSessionHasNoErrors();
        Auth::guard('web')->logout();

        return $a->fresh();
    }

    public function test_register_confirm_approve_and_sign_in(): void
    {
        $a = $this->register();
        $this->assertSame('jo@theatre.example', $a->email);
        $this->assertTrue(Hash::check('secret-pass-1', $a->password));
        $this->assertNotSame('secret-pass-1', $a->getRawOriginal('password'));
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'jo@theatre.example' && str_contains($n->button[1], '/account/verify/'));

        $this->assertSame('unverified', CustomerAuth::attempt('jo@theatre.example', 'secret-pass-1'));
        $this->get('/account/verify/'.$a->id.'/'.sha1($a->email))->assertForbidden(); // not signed
        $this->get($this->verifyLink($a))->assertRedirect();
        $this->assertNotNull($a->fresh()->email_verified_at);
        $this->assertSame('pending', CustomerAuth::attempt('jo@theatre.example', 'secret-pass-1'));
        $this->assertSame('wrong', CustomerAuth::attempt('jo@theatre.example', 'nope'));

        // Staff see it waiting and approve it with a new company; the contact is made from the registration.
        $admin = $this->staff();
        $this->actingAs($admin)->get('/companies/website-logins')->assertOk()->assertSee('jo@theatre.example');
        $this->actingAs($admin)->post('/companies/website-logins/'.$a->id.'/approve', ['company' => 'new'])->assertSessionHasNoErrors();
        $a->refresh();
        $this->assertSame('active', $a->status);
        $this->assertSame('Demo Theatre vzw', $a->company->name);
        $this->assertSame('jo@theatre.example', $a->contact->email);
        $this->assertTrue($a->company->contacts->contains($a->contact));
        $this->assertTrue(AuditLog::where('action', 'web.account_approved')->exists());
        $this->actingAs($admin)->get('/companies/'.$a->company_id)->assertOk()->assertSee('jo@theatre.example');
        Auth::guard('web')->logout();

        // Signing in uses the "customer" session: separate from staff.
        $this->get('/_test/account')->assertRedirect();
        $this->assertNull(CustomerAuth::attempt('JO@theatre.example', 'secret-pass-1'));
        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertFalse(Auth::guard('web')->check());
        $this->get('/_test/account')->assertOk()->assertSee('my account jo@theatre.example');
        $this->get('/dashboard')->assertRedirect(); // a customer is no staff member
        $this->assertNotNull($a->fresh()->last_login_at);
    }

    public function test_blocking_signs_out_and_a_password_link_works_once(): void
    {
        $a = $this->activeAccount();
        $this->assertNull(CustomerAuth::attempt($a->email, 'secret-pass-1'));
        $this->get('/_test/account')->assertOk();

        $admin = $this->staff();
        $this->actingAs($admin)->post('/companies/website-logins/'.$a->id.'/block', ['reason' => 'Unpaid invoices'])->assertSessionHasNoErrors();
        $this->assertSame('blocked', $a->fresh()->status);
        Auth::guard('web')->logout();
        $this->get('/_test/account')->assertRedirect();
        $this->assertFalse(Auth::guard('customer')->check());
        $this->assertSame('blocked', CustomerAuth::attempt($a->email, 'secret-pass-1'));

        $this->actingAs($admin)->post('/companies/website-logins/'.$a->id.'/unblock')->assertSessionHasNoErrors();
        $this->assertSame('active', $a->fresh()->status);
        Auth::guard('web')->logout();

        CustomerAuth::sendReset($a->fresh());
        $link = null;
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, function ($n) use (&$link) {
            if (str_contains($n->button[1] ?? '', 'token=')) {
                $link = $n->button[1];
            }

            return true;
        });
        parse_str((string) parse_url($link, PHP_URL_QUERY), $q);
        $this->assertSame($a->email, $q['email']);
        $this->assertTrue(CustomerAuth::reset($a->email, $q['token'], 'new-pass-22'));
        $this->assertFalse(CustomerAuth::reset($a->email, $q['token'], 'other-pass-33')); // used once
        $this->assertNull(CustomerAuth::attempt($a->email, 'new-pass-22'));
        $this->assertFalse(CustomerAuth::changePassword($a->fresh(), 'wrong', 'x-pass-444'));
        $this->assertTrue(CustomerAuth::changePassword($a->fresh(), 'new-pass-22', 'x-pass-444'));
        $this->assertTrue(Hash::check('x-pass-444', $a->fresh()->password));
    }

    public function test_staff_never_see_the_password(): void
    {
        $a = $this->activeAccount();
        $hash = $a->getRawOriginal('password');
        $this->assertArrayNotHasKey('password', $a->toArray());
        $admin = $this->staff();
        $this->actingAs($admin)->get('/companies/website-logins?s=active')->assertOk()->assertDontSee($hash)->assertDontSee('secret-pass-1');
        $this->actingAs($admin)->get('/companies/'.$a->company_id)->assertOk()->assertDontSee($hash);
    }

    public function test_dry_hire_basket_goes_to_the_team_as_a_request(): void
    {
        $a = $this->activeAccount();
        $ks = new Item(['number' => 'KS28', 'kind' => 'item', 'name' => 'L-Acoustics KS28', 'brand' => 'L-Acoustics', 'type' => 'KS28', 'unit' => 'pcs', 'serial' => false, 'stock_qty' => 8, 'day_price_cents' => 9500, 'active' => true]);
        $ks->placeIn(Subcategory::findOrFail(self::sub('SUB', 'AUD')));
        $ks->save();
        $off = Item::create(['number' => 'OLD1', 'kind' => 'item', 'name' => 'Old', 'unit' => 'pcs', 'serial' => false, 'active' => false]);

        $this->assertSame('unknown', Baskets::set($a, 'dryhire', $off->id, 1));
        $this->assertNull(Baskets::set($a, 'dryhire', $ks->id, 2));
        Baskets::merge($a, 'dryhire', [['id' => $ks->id, 'qty' => 2], ['id' => $off->id, 'qty' => 1]]);
        $first = today()->addDays(7)->toDateString();
        $last = today()->addDays(8)->toDateString();
        Baskets::dates($a, $first, $last);
        $show = Baskets::show($a, 'dryhire');
        $this->assertCount(1, $show['lines']);
        $this->assertSame(4, $show['lines'][0]['qty']);
        $this->assertSame(38000, $show['per_day_cents']);
        $this->assertSame(2, $show['days']);

        try {
            Baskets::submitDryHire($a, ['first_day' => today()->subDay()->toDateString(), 'last_day' => $last]);
            $this->fail('A date in the past is refused.');
        } catch (ValidationException) {
        }

        $msg = Baskets::submitDryHire($a, ['message' => 'Delivery at the stage door please']);
        $this->assertSame('website', $msg->channel);
        $this->assertSame($a->id, $msg->customer_account_id);
        $this->assertSame($a->company_id, $msg->company_id);
        $this->assertSame($a->contact_id, $msg->contact_id);
        $this->assertSame(4, $msg->payload['lines'][0]['qty']);
        $this->assertSame($first, $msg->payload['first_day']);
        $this->assertSame([], Baskets::show($a, 'dryhire')['lines']); // the basket is empty again
        $this->assertSame('W-'.$msg->id, Baskets::history($a)['requests'][0]['ref']);
        $this->assertSame(1, IncomingMessage::count());
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === $a->email && str_contains($n->subject, ' – '));

        $this->expectException(ValidationException::class);
        Baskets::submitDryHire($a); // nothing in it
    }

    public function test_sales_basket_is_an_order_on_invoice(): void
    {
        $a = $this->activeAccount();
        $owner = $a->approvedBy;
        $sup = Company::create(['name' => 'Demo Audio Distribution BV', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en', 'email' => 'orders@dad.example']);
        $mic = SalesItem::create(['number' => 'S-100001', 'brand' => 'Shure', 'type' => 'SM58-LCE', 'unit' => 'piece', 'list_price_cents' => 11900, 'stock_qty' => 1, 'active' => true]);
        $mic->suppliers()->attach($sup->id, ['discount_bp' => 3500, 'lead_days' => 3, 'preferred' => true]);
        $noPrice = SalesItem::create(['number' => 'S-100002', 'type' => 'Custom case', 'unit' => 'piece', 'list_price_cents' => 0, 'active' => true]);

        $this->assertSame('unknown', Baskets::set($a, 'sales', $noPrice->id, 1)); // no price: ask for a quote instead
        $this->assertNull(Baskets::set($a, 'sales', $mic->id, 3));
        $show = Baskets::show($a, 'sales');
        $this->assertSame(35700, $show['net_cents']);
        $this->assertSame(21.0, (float) $show['vat_pct']);

        $q = Baskets::submitSales($a, ['po_number' => 'PO-77', 'message' => 'For the new season']);
        $this->assertSame('accepted', $q->status);
        $this->assertSame($a->id, $q->customer_account_id);
        $this->assertSame($a->company_id, $q->company_id);
        $this->assertSame('PO-77', $q->po_number);
        $this->assertSame('Jo Peeters', $q->answered_name);
        $this->assertSame(35700, $q->net_cents);
        $this->assertSame(7735, $q->lines->first()->cost_cents);
        $this->assertSame(1, $q->lines->first()->fresh()->from_stock); // 1 from stock, 2 asked from the supplier
        $this->assertSame(1, PurchaseRequest::count());
        $this->assertSame([], Baskets::show($a, 'sales')['lines']);
        $this->assertSame($q->number, Baskets::history($a)['orders'][0]['number']);
        $this->assertTrue(AuditLog::where('action', 'web.order_placed')->exists());
        Notification::assertSentTo($owner, Confirmation::class, fn ($n) => str_contains($n->subject, $q->number));
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === $a->email && str_contains($n->subject, $q->number));

        // The customer card shows it.
        $this->actingAs($this->staff())->get('/companies/'.$a->company_id)->assertOk()->assertSee('1 web orders');
    }

    public function test_baskets_need_an_active_login(): void
    {
        $a = $this->register();
        $mic = SalesItem::create(['number' => 'S-100001', 'type' => 'SM58', 'unit' => 'piece', 'list_price_cents' => 11900, 'active' => true]);
        Baskets::set($a, 'sales', $mic->id, 1);
        $this->expectException(HttpException::class);
        Baskets::submitSales($a);
    }

    public function test_one_login_has_two_separate_baskets(): void
    {
        $a = $this->activeAccount();
        Baskets::of($a, 'dryhire');
        Baskets::of($a, 'sales');
        Baskets::of($a, 'sales');
        $this->assertSame(2, Basket::where('account_id', $a->id)->count());
    }
}
