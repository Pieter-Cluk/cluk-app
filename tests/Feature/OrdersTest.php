<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireLine;
use App\Models\Rental\SubhireRequest;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Rental\Availability;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private User $am;

    private Item $spiider;

    private Item $cable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $this->sales = $this->as('sales', ['first_name' => 'Elke']);
        $this->am = $this->as('asset-manager', ['first_name' => 'Tom', 'title' => 'Asset manager']);
        $this->spiider = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'serial' => true, 'day_price_cents' => 6500, 'price_unit' => 'day', 'catalog_price_cents' => 985000]);
        foreach (range(1, 6) as $n) {
            $this->spiider->assets()->create(['code' => 'LT-000'.$n, 'status' => 'available']);
        }
        $this->cable = Item::create(['number' => 'AC-PT1-03', 'kind' => 'item', 'name' => 'powerCON cable 3 m', 'serial' => false, 'stock_qty' => 20, 'day_price_cents' => 200, 'price_unit' => 'day']);
    }

    private function as(string $role, array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    /** A sent quote for 8 Spiiders and 10 cables, accepted online by the customer → an order. */
    private function order(int $spiiders = 8, string $from = '2026-11-14', string $until = '2026-11-15'): Order
    {
        $company = Company::create(['name' => 'Demo Agency BV', 'country' => 'BE', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $contact = Contact::create(['first_name' => 'Sarah', 'last_name' => 'Peeters', 'email' => 'sarah@demo.be']);
        $company->contacts()->attach($contact->id);
        $deal = Deal::create(['number' => Deal::nextNumber(), 'title' => 'Company evening', 'module' => 'dryhire', 'stage' => 'proposal', 'company_id' => $company->id, 'contact_id' => $contact->id, 'owner_id' => $this->sales->id, 'event_start' => $from, 'event_end' => $until]);
        $this->actingAs($this->sales)->post('/sales/deals/'.$deal->id.'/quotes');
        $quote = Quote::latest('id')->first();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => $spiiders]);
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'AC-PT1-03', 'qty' => 10]);
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'charge', 'name' => 'Delivery', 'qty' => 1, 'price' => 95]);
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/send', ['to' => 'sarah@demo.be']);
        auth('web')->logout();
        $this->post('/q/'.$quote->fresh()->token.'/accept', ['name' => 'Sarah Peeters', 'po_number' => 'PO 1', 'terms' => '1'])->assertRedirect();

        return Order::where('quote_id', $quote->id)->firstOrFail();
    }

    private function supplier(array $attrs = []): Company
    {
        return Company::create($attrs + ['name' => 'Demo Light Hire GmbH', 'country' => 'DE', 'email' => 'hire@demolight.de', 'is_customer' => false, 'is_supplier' => true, 'supplies' => ['subhire'], 'vat_regime' => 'auto', 'language' => 'de']);
    }

    public function test_an_accepted_quote_becomes_an_order_in_allocation(): void
    {
        $order = $this->order();
        $this->assertSame('R-OC-'.now()->year.'-0001', $order->number);
        $this->assertSame('allocation', $order->status);
        $this->assertSame(3, $order->lines()->count());
        $this->assertSame('PO 1', $order->po_number);
        $this->assertTrue($order->confirmed_online);
        $this->assertSame($this->sales->id, $order->handed_by_id);
        $this->assertTrue(AuditLog::where('action', 'order.created')->exists());
        // 8 Spiiders, only 6 owned: 2 short and no partner yet.
        $this->assertSame('subhire', $order->state());

        $this->actingAs($this->am)->get('/order-allocation')->assertOk()->assertSee('Company evening');
        $this->actingAs($this->am)->get('/dashboard')->assertSee('Orders to allocate');
    }

    public function test_the_order_holds_own_stock_instead_of_the_quote(): void
    {
        $order = $this->order(4);
        $avail = new Availability($order->out_from, $order->out_until);
        $this->assertSame(2, $avail->free($this->spiider));   // 6 − 4, the quote is not counted twice
        $this->assertSame(10, $avail->free($this->cable));

        $later = new Availability($order->out_from->copy()->addMonth(), $order->out_until->copy()->addMonth());
        $this->assertSame(6, $later->free($this->spiider));
    }

    public function test_auto_allocate_books_free_serial_pieces_and_counted_stock(): void
    {
        $first = $this->order(4);
        $this->actingAs($this->am)->post('/order-allocation/'.$first->id.'/auto')->assertRedirect();
        $this->assertSame(['LT-0001', 'LT-0002', 'LT-0003', 'LT-0004'], $first->assets()->with('asset')->get()->pluck('asset.code')->sort()->values()->all());
        $this->assertSame(10, $first->lines()->where('item_id', $this->cable->id)->value('own_qty'));
        $this->assertSame('ready', $first->state());
        $this->assertSame($this->am->id, $first->fresh()->assignee_id); // taken on the first action

        // A second order on the same dates gets the other two pieces only.
        $second = $this->order(2);
        $this->actingAs($this->am)->post('/order-allocation/'.$second->id.'/auto');
        $this->assertSame(['LT-0005', 'LT-0006'], $second->assets()->with('asset')->get()->pluck('asset.code')->sort()->values()->all());

        // A piece by code that is booked elsewhere is refused.
        $this->actingAs($this->am)->post('/order-allocation/'.$second->id.'/lines/'.$second->lines()->where('item_id', $this->spiider->id)->value('id').'/clear');
        $line = $second->lines()->where('item_id', $this->spiider->id)->value('id');
        $this->actingAs($this->am)->post('/order-allocation/'.$second->id.'/lines/'.$line.'/allocate', ['code' => 'lt-0001'])->assertSessionHasErrors('code');
        $this->actingAs($this->am)->post('/order-allocation/'.$second->id.'/lines/'.$line.'/allocate', ['code' => 'lt-0006'])->assertSessionHasNoErrors();
    }

    public function test_sub_hire_from_request_to_confirmation(): void
    {
        $order = $this->order(8);
        $partner = $this->supplier();
        $line = $order->lines()->where('item_id', $this->spiider->id)->first();

        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/lines/'.$line->id.'/subhire', ['supplier_id' => $partner->id, 'qty' => 2])->assertSessionHasNoErrors();
        $request = SubhireRequest::firstOrFail();
        $this->assertSame('draft', $request->status);
        $this->assertSame('SHQ-'.now()->year.'-0001', $request->number);
        $this->assertSame('subhire', $order->state()); // drafts still to send

        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/send-requests')->assertSessionHasNoErrors();
        $this->assertSame('sent', $request->fresh()->status);
        $this->assertSame('waiting', $order->state());
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'hire@demolight.de' && $n->locale === 'en');

        // Accepting needs prices.
        $this->actingAs($this->am)->post('/sub-hire/'.$request->id.'/accept')->assertSessionHasErrors('price');
        $sl = $request->lines()->first();
        $this->actingAs($this->am)->put('/sub-hire/'.$request->id, [
            'their_ref' => 'Q-55871', 'delivery_at' => '2026-11-12T16:00', 'return_at' => '2026-11-17T10:00', 'extra' => '40',
            'lines' => [$sl->id => ['qty' => 2, 'price' => '55', 'factor' => '2', 'discount' => '']],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2 * 5500 * 2 + 4000, $request->fresh()->total_cents);
        $this->actingAs($this->am)->post('/sub-hire/'.$request->id.'/accept')->assertSessionHasNoErrors();
        $request->refresh();
        $this->assertSame('accepted', $request->status);
        $this->assertSame('SHC-'.now()->year.'-0001', $request->confirm_number);
        $this->assertSame('allocate', $order->state());

        // Own stock for the other 6, then ready.
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto');
        $this->assertSame(6, $order->assets()->count());
        $this->assertSame('ready', $order->state());

        // Next time this partner's price shows as "our last price".
        $this->assertSame(5500, SubhireLine::lastPrice($partner->id, $this->spiider->id));
    }

    public function test_not_available_opens_the_pieces_again_and_no_email_is_reported(): void
    {
        $order = $this->order(8);
        $line = $order->lines()->where('item_id', $this->spiider->id)->first();
        $mute = $this->supplier(['name' => 'No Mail BV', 'email' => null]);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/lines/'.$line->id.'/subhire', ['supplier_id' => $mute->id, 'qty' => 2]);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/send-requests')->assertSessionHasErrors('supplier');

        $partner = $this->supplier();
        SubhireRequest::first()->delete();
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/lines/'.$line->id.'/subhire', ['supplier_id' => $partner->id, 'qty' => 2]);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/send-requests');
        $request = SubhireRequest::where('supplier_id', $partner->id)->first();
        $this->actingAs($this->am)->post('/sub-hire/'.$request->id.'/not-available')->assertRedirect('/order-allocation/'.$order->id);
        $this->assertSame('subhire', $order->state());
    }

    public function test_approve_needs_the_right_and_full_cover_and_sends_the_confirmation(): void
    {
        $order = $this->order(4);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/approve')->assertSessionHasErrors('order');

        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto');
        $warehouse = $this->as('warehouse'); // dry-hire level 2, no approval right
        $order->update(['assignee_id' => null]);
        $this->actingAs($warehouse)->post('/order-allocation/'.$order->id.'/approve')->assertForbidden();

        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/approve')->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertSame($this->am->id, $order->approved_by_id);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'sarah@demo.be' && str_starts_with($n->subject, 'Order confirmation'));
        Notification::assertSentTo($this->sales, Confirmation::class, fn ($n) => str_starts_with($n->subject, 'Order approved'));
        $this->assertStringContainsString($order->number, $order->deal->activities()->latest('id')->value('body'));

        // The customer can open the order confirmation through their quote link.
        $this->get('/q/'.$order->quote->token.'/order')->assertOk()->assertSee($order->number)->assertSee('ORDER CONFIRMATION');
        // Approved orders are locked.
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto')->assertForbidden();
    }

    public function test_handing_over_follows_the_reassignment_rule(): void
    {
        $order = $this->order(4);
        $colleague = $this->as('asset-manager', ['first_name' => 'Kobe', 'title' => 'Asset manager']);
        $other = $this->as('asset-manager', ['first_name' => 'Jens', 'title' => 'Warehouse lead']);
        $order->update(['assignee_id' => $this->am->id]);

        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/assign', ['user_id' => $other->id])->assertSessionHasErrors('user_id');
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/assign', ['user_id' => $colleague->id])->assertSessionHasNoErrors();
        $this->assertSame($colleague->id, $order->fresh()->assignee_id);
        // Someone at dry-hire level 2 cannot work on an order that is with someone else…
        $this->actingAs($this->as('warehouse'))->post('/order-allocation/'.$order->id.'/auto')->assertForbidden();
        // …asset managers (level 3) can.
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto')->assertRedirect();
    }

    public function test_cancel_releases_the_stock(): void
    {
        $order = $this->order(4);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto');
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/cancel', ['reason' => 'Event cancelled'])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $order->assets()->count());
        $this->assertSame(6, (new Availability($order->out_from, $order->out_until))->free($this->spiider));
    }

    public function test_margin_only_with_the_right(): void
    {
        $order = $this->order(8);
        $partner = $this->supplier();
        $line = $order->lines()->where('item_id', $this->spiider->id)->first();
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/lines/'.$line->id.'/subhire', ['supplier_id' => $partner->id, 'qty' => 2]);
        $request = SubhireRequest::first();
        $this->actingAs($this->am)->get('/sub-hire/'.$request->id)->assertOk()->assertSee('Does it still pay off?');
        $this->actingAs($this->as('warehouse'))->get('/sub-hire/'.$request->id)->assertOk()->assertDontSee('Does it still pay off?');
    }

    public function test_pages_work_in_every_language_without_missing_texts(): void
    {
        $order = $this->order(8);
        $partner = $this->supplier();
        $line = $order->lines()->where('item_id', $this->spiider->id)->first();
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/lines/'.$line->id.'/subhire', ['supplier_id' => $partner->id, 'qty' => 2]);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto');
        $request = SubhireRequest::first();
        $admin = $this->as('admin');
        $pattern = '/\b(orders|quotes|mails|nav)\.[a-z_]+(\.[a-z_]+)*\b/';

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/order-allocation', '/order-allocation?tab=done', '/order-allocation?state=subhire', '/order-allocation/'.$order->id, '/order-allocation/'.$order->id.'/print', '/sub-hire', '/sub-hire?tab=shc', '/sub-hire/'.$request->id, '/dashboard'] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
        }
    }

    public function test_documents_of_an_approved_order(): void
    {
        $order = $this->order(4);
        $this->spiider->assets()->where('code', 'LT-0001')->update(['location' => 'A-03-2']);
        BankAccount::create(['bank' => 'KBC', 'iban' => 'BE68539007547034', 'bic' => 'KREDBEBB', 'is_main' => true, 'on_documents' => true]);
        $this->actingAs($this->am)->get('/order-allocation/'.$order->id.'/documents/pick')->assertNotFound(); // not approved yet

        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto');
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/approve');
        $n = substr($order->number, 5);

        $this->actingAs($this->am)->get('/order-allocation/'.$order->id.'/documents/pick')->assertOk()
            ->assertSee('R-PICK-'.$n)->assertSee('LT-0001 · A-03-2')->assertDontSee('€');
        $this->actingAs($this->am)->get('/order-allocation/'.$order->id.'/documents/dn')->assertOk()->assertSee('R-DN-'.$n)->assertDontSee('€');
        $this->actingAs($this->am)->get('/order-allocation/'.$order->id.'/documents/ret')->assertOk()->assertSee('R-RET-'.$n)->assertDontSee('€');
        $this->actingAs($this->am)->get('/order-allocation/'.$order->id.'/documents/pf')->assertOk()->assertSee('R-PF-'.$n)->assertSee('BE68 5390 0754 7034')->assertSee('not a tax invoice');

        // Customer documents follow the customer's language.
        $order->update(['language' => 'nl']);
        $this->actingAs($this->am)->get('/order-allocation/'.$order->id.'/documents/dn')->assertSee('LEVERBON');

        Notification::fake();
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/proforma')->assertSessionHasNoErrors();
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'sarah@demo.be' && str_starts_with($n->subject, 'Proforma'));
        $this->actingAs($this->as('warehouse'))->post('/order-allocation/'.$order->id.'/proforma')->assertForbidden();
        auth('web')->logout();
        $this->get('/q/'.$order->quote->token.'/proforma')->assertOk()->assertSee('R-PF-'.$n);
    }

    public function test_documents_in_every_language_without_missing_texts(): void
    {
        $order = $this->order(4);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto');
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/approve');
        $admin = $this->as('admin');
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            $order->update(['language' => $locale]);
            foreach (['pick', 'dn', 'ret', 'pf'] as $t) {
                $html = $this->actingAs($admin->fresh())->get('/order-allocation/'.$order->id.'/documents/'.$t)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\\b(docs|orders|quotes)\\.[a-z_]+(\\.[a-z_]+)*\\b/', strip_tags($html), "{$t} ({$locale})");
            }
            $html = $this->actingAs($admin->fresh())->get('/order-allocation/'.$order->id)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\\bdocs\\.[a-z_]+/', strip_tags($html));
        }
    }
}
