<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
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

class OrderChangesTest extends TestCase
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

    /** 8 Spiiders: 6 own + 2 from a partner, confirmed, own stock booked, order approved. */
    private function approved(): array
    {
        $order = $this->order(8);
        $partner = $this->supplier(['language' => 'en']);
        $line = $order->lines()->where('item_id', $this->spiider->id)->first();
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/lines/'.$line->id.'/subhire', ['supplier_id' => $partner->id, 'qty' => 2]);
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/send-requests');
        $r = SubhireRequest::firstOrFail();
        $sl = $r->lines()->first();
        $this->actingAs($this->am)->put('/sub-hire/'.$r->id, ['delivery_at' => '2026-11-13T16:00', 'return_at' => '2026-11-16T10:00', 'lines' => [$sl->id => ['qty' => 2, 'price' => '55', 'factor' => '1']]]);
        $this->actingAs($this->am)->post('/sub-hire/'.$r->id.'/accept')->assertSessionHasNoErrors();
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/auto');
        $this->actingAs($this->am)->post('/order-allocation/'.$order->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', $order->fresh()->status);

        return [$order->fresh(), $r->fresh(), $partner];
    }

    private function adjustmentSent(int $times): void
    {
        $n = 0;
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, function ($m, $c, $to) use (&$n) {
            if ($to->routes['mail'] === 'hire@demolight.de' && str_starts_with($m->subject, 'ADJUSTMENT to confirmed order SHC-')) {
                $n++;
            }

            return true;
        });
        $this->assertSame($times, $n);
    }

    public function test_times_can_change_after_won_and_the_partner_gets_an_adjustment(): void
    {
        [$order, $r] = $this->approved();
        // Another job holds LT-0001 on the day before: moving earlier releases it.
        $other = Order::create(['number' => 'R-OC-2026-0099', 'status' => 'approved', 'company_id' => $order->company_id, 'title' => 'Other job', 'language' => 'en',
            'out_from' => '2026-11-12 08:00', 'out_until' => '2026-11-13 12:00', 'use_from' => '2026-11-12', 'use_until' => '2026-11-12', 'net_cents' => 1]);
        $ol = $other->lines()->create(['kind' => 'item', 'item_id' => $this->spiider->id, 'name' => 'Robe Spiider', 'qty' => 1, 'amount_cents' => 1, 'position' => 0]);
        OrderAsset::create(['order_id' => $other->id, 'order_line_id' => $ol->id, 'asset_id' => $this->spiider->assets()->where('code', 'LT-0001')->value('id')]);
        $deliveryWas = $r->delivery_at->copy();

        $this->actingAs($this->sales)->post('/order-allocation/'.$order->id.'/times', [
            'out_from' => $order->out_from->copy()->subDays(2)->format('Y-m-d\\TH:i'), 'out_until' => $order->out_until->format('Y-m-d\\TH:i'),
            'use_from' => $order->use_from->format('Y-m-d'), 'use_until' => $order->use_until->format('Y-m-d'), 'move_subhire' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'LT-0001') && str_contains($s, 'adjustment'));
        $order->refresh();
        $this->assertSame('allocation', $order->status, 'a released piece sends it back to allocation');
        $this->assertSame(5, $order->assets()->count());
        $this->assertTrue($r->fresh()->delivery_at->equalTo($deliveryWas->copy()->subDays(2)));
        $this->assertNotEmpty($r->fresh()->changes());

        $this->actingAs($this->am)->get('/sub-hire/'.$r->id)->assertSee('Changed since the partner confirmed');
        $this->actingAs($this->am)->post('/sub-hire/'.$r->id.'/adjust', ['message' => 'Sorry, the job moved two days earlier.'])->assertSessionHasNoErrors();
        $this->adjustmentSent(1);
        $this->assertSame([], $r->fresh()->changes());
        $this->assertTrue(AuditLog::where('action', 'order.times_changed')->exists());
    }

    public function test_reopen_back_to_a_new_quote_version_and_take_the_order_back(): void
    {
        [$order, $r] = $this->approved();
        $quote = $order->quote;
        $this->actingAs($this->sales)->post('/order-allocation/'.$order->id.'/reopen', ['reason' => 'Customer adds 2 cables'])->assertRedirect();
        $order->refresh();
        $this->assertSame('reopened', $order->status);
        $this->assertSame('replaced', $quote->fresh()->status);
        $v2 = Quote::where('number', $quote->number)->where('version', 2)->firstOrFail();
        $this->assertSame('draft', $v2->status);
        $this->assertSame('negotiation', $order->deal->fresh()->stage);
        $this->assertSame('sent', $r->fresh()->status, 'confirmed sub-hire goes back to asked');
        $this->assertSame(6, $order->assets()->count(), 'stock stays held');
        $this->assertSame(0, (new Availability($order->out_from, $order->out_until))->free($this->spiider));

        // Resending to the partner now says it is an adjustment.
        $this->actingAs($this->am)->get('/sub-hire/'.$r->id)->assertSee('already confirmed this as SHC-');
        $this->actingAs($this->am)->post('/sub-hire/'.$r->id.'/resend')->assertSessionHasNoErrors();
        $this->adjustmentSent(1);

        // The customer accepts the new version with 12 cables: the same order continues.
        $cables = $v2->lines()->where('item_id', $this->cable->id)->first();
        $cables->update(['qty' => 12]);
        $v2->recalculate();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$v2->id.'/send', ['to' => 'sarah@demo.be'])->assertSessionHasNoErrors();
        $this->assertSame(0, (new Availability($order->out_from, $order->out_until))->free($this->spiider), 'not counted twice');
        auth('web')->logout();
        $this->post('/q/'.$v2->fresh()->token.'/accept', ['name' => 'Sarah Peeters', 'po_number' => 'PO 2', 'terms' => '1'])->assertRedirect();
        $order->refresh();
        $this->assertSame(1, Order::count());
        $this->assertSame('allocation', $order->status);
        $this->assertSame($v2->id, $order->quote_id);
        $this->assertSame(12, (int) $order->lines()->where('item_id', $this->cable->id)->value('qty'));
        $this->assertSame(6, $order->assets()->count(), 'the Spiider line kept its pieces');
        $this->assertSame('won', $order->deal->fresh()->stage);

        // Confirm the partner again: same SHC number, sent as an adjustment.
        $this->actingAs($this->am)->post('/sub-hire/'.$r->id.'/accept')->assertSessionHasNoErrors();
        $this->assertSame($r->confirm_number, $r->fresh()->confirm_number);
        $this->adjustmentSent(2);
    }

    public function test_a_reopened_order_can_be_cancelled_and_the_partner_is_told(): void
    {
        [$order] = $this->approved();
        $this->actingAs($this->sales)->post('/order-allocation/'.$order->id.'/reopen', ['reason' => 'Customer cancels'])->assertRedirect();
        $this->actingAs($this->sales)->post('/order-allocation/'.$order->id.'/cancel', ['reason' => 'Event cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $order->assets()->count());
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($m, $c, $to) => str_starts_with($m->subject, 'CANCELLED: order SHC-'));
        $this->assertSame('cancelled', SubhireRequest::first()->status);
    }
}
