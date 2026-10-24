<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Finance\PreInvoice;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Transport on rental quotes and orders: chosen on the quote or later on the order, until pre-invoicing. */
class TransportTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $this->sales = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en', 'first_name' => 'Elke']);
        Item::create(['number' => 'AC-PT1-03', 'kind' => 'item', 'name' => 'powerCON cable 3 m', 'serial' => false, 'stock_qty' => 20, 'day_price_cents' => 1000, 'price_unit' => 'day']);
    }

    /** A draft quote with 10 cables (€ 100 for one day). */
    private function quote(): Quote
    {
        $company = Company::create(['name' => 'Demo Agency BV', 'country' => 'BE', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en',
            'vat_number' => 'BE0123456749', 'street' => 'Stationsstraat 1', 'postcode' => '9870', 'city' => 'Zulte']);
        $contact = Contact::create(['first_name' => 'Sarah', 'last_name' => 'Peeters', 'email' => 'sarah@demo.be']);
        $company->contacts()->attach($contact->id);
        $deal = Deal::create(['number' => Deal::nextNumber(), 'title' => 'Company evening', 'module' => 'dryhire', 'stage' => 'proposal', 'company_id' => $company->id, 'contact_id' => $contact->id, 'owner_id' => $this->sales->id, 'event_start' => '2026-11-14', 'event_end' => '2026-11-14']);
        $this->actingAs($this->sales)->post('/sales/deals/'.$deal->id.'/quotes');
        $quote = Quote::latest('id')->first();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'AC-PT1-03', 'qty' => 10]);

        return $quote->fresh();
    }

    private function save(Quote $quote, array $extra): void
    {
        $line = $quote->lines()->first();
        $this->actingAs($this->sales)->put('/sales/quotes/'.$quote->id, $extra + [
            'title' => 'Company evening', 'language' => 'en', 'out_from' => '2026-11-13T14:00', 'out_until' => '2026-11-15T10:00',
            'use_from' => '2026-11-14', 'use_until' => '2026-11-14',
            'lines' => [$line->id => ['name' => $line->name, 'qty' => 10, 'factor' => '', 'price' => '10.00']],
        ])->assertSessionHasNoErrors();
    }

    private function accept(Quote $quote): Order
    {
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/send', ['to' => 'sarah@demo.be']);
        auth('web')->logout();
        $this->post('/q/'.$quote->fresh()->token.'/accept', ['name' => 'Sarah Peeters', 'po_number' => 'PO 1', 'terms' => '1'])->assertRedirect();

        return Order::where('quote_id', $quote->id)->firstOrFail();
    }

    public function test_transport_on_the_quote_counts_without_project_discount(): void
    {
        $quote = $this->quote();
        $this->save($quote, ['project_discount' => '10', 'logistics' => 'delivery', 'transport' => '150', 'transport_note' => 'Ghent, tail lift']);
        $quote->refresh();
        // 100 − 10% = 90 + 150 transport = 240
        $this->assertSame([10000, 1000, 24000], [$quote->subtotal_cents, $quote->discount_cents, $quote->net_cents]);

        $this->actingAs($this->sales)->get('/sales/quotes/'.$quote->id.'/print')->assertOk()
            ->assertSee('Transport (delivery and collection)')->assertSee('Delivery and collection by CLUK · Ghent, tail lift');

        // The customer picks up after all: no transport in the total.
        $this->save($quote, ['logistics' => 'pickup', 'transport' => '150']);
        $this->assertSame(10000, $quote->fresh()->net_cents);
        $this->actingAs($this->sales)->get('/sales/quotes/'.$quote->id.'/print')->assertSee('Pick-up and return by the customer at our warehouse')->assertDontSee('Transport (delivery and collection)');
    }

    public function test_transport_added_after_the_win_until_pre_invoicing(): void
    {
        $quote = $this->quote();
        $this->save($quote, ['logistics' => 'delivery', 'transport' => '']); // we deliver, price not known yet
        $this->actingAs($this->sales)->get('/sales/quotes/'.$quote->id.'/print')->assertSee('price follows');
        $order = $this->accept($quote);
        $this->assertSame(['delivery', 0, 10000], [$order->logistics, $order->transport_cents, $order->net_cents]);

        // After the win: the price becomes known.
        $page = $this->actingAs($this->sales)->get(route('orders.show', $order))->assertOk()->assertSee('Save transport');
        if ($out = env('DUMP_DIR')) {
            file_put_contents($out.'/order.html', $page->getContent());
            file_put_contents($out.'/quote.html', $this->actingAs($this->sales)->get(route('quotes.show', $quote))->getContent());
        }
        $this->actingAs($this->sales)->post(route('orders.transport', $order), ['logistics' => 'delivery', 'transport' => '220', 'transport_note' => '2 trips'])->assertRedirect();
        $order->refresh();
        $this->assertSame([22000, 32000, 6720, 38720], [$order->transport_cents, $order->net_cents, $order->vat_cents, $order->gross_cents]);
        $this->assertTrue(AuditLog::where('action', 'order.transport')->exists());
        $this->actingAs($this->sales)->get(route('orders.print', $order))->assertSee('Transport (delivery and collection)')->assertSee('€ 220');

        // To pre-invoicing: the transport is a line, and the check knows it was added later.
        $this->actingAs($this->sales)->post('/pre-invoicing/make/rental/'.$order->id)->assertRedirect();
        $pf = PreInvoice::firstOrFail();
        $this->assertSame(32000, $pf->net_cents);
        $this->assertContains('Transport (delivery and collection) · 2 trips', collect($pf->lines)->pluck('d')->all());
        $this->assertContains('matches_quote_transport', collect($pf->checks)->pluck('key')->all());

        // Now it is locked…
        $this->actingAs($this->sales)->get(route('orders.show', $order))->assertOk()->assertDontSee('Save transport')->assertSee('Locked: pre-invoice');
        $this->actingAs($this->sales)->post(route('orders.transport', $order), ['logistics' => 'delivery', 'transport' => '300'])->assertForbidden();

        // …until the accountant sends it back.
        $pf->update(['status' => 'back']);
        $this->actingAs($this->sales)->post(route('orders.transport', $order), ['logistics' => 'delivery', 'transport' => '300'])->assertRedirect();
        $this->assertSame(40000, $order->fresh()->net_cents);
    }

    public function test_a_reopened_order_keeps_the_transport_in_the_new_quote_version(): void
    {
        $quote = $this->quote();
        $order = $this->accept($quote);
        $this->actingAs($this->sales)->post(route('orders.transport', $order), ['logistics' => 'delivery', 'transport' => '180'])->assertRedirect();
        $this->actingAs($this->sales)->post(route('orders.reopen', $order), ['reason' => 'Two more cables'])->assertRedirect();
        $v2 = Quote::where('number', $quote->number)->where('version', 2)->firstOrFail();
        $this->assertSame(['delivery', 18000, 28000], [$v2->logistics, $v2->transport_cents, $v2->net_cents]);
    }
}
