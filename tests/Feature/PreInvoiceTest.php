<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Finance\PreInvoice;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PreInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    private function as(string $role, array $extra = []): User
    {
        return User::factory()->create($extra + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    /** A Belgian customer that wants a PO number, and an approved order of € 1,000. */
    private function order(User $by): Order
    {
        $co = Company::create(['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en',
            'country' => 'BE', 'vat_number' => 'BE0123456749', 'street' => 'Stationsstraat 1', 'postcode' => '9870', 'city' => 'Zulte',
            'customer_payment_days' => 30, 'po_required' => true]);
        $co->forceFill(['vies_status' => 'valid'])->save();
        $o = Order::create(['number' => 'R-OC-2026-0102', 'status' => 'approved', 'company_id' => $co->id, 'title' => 'Light rig festival', 'language' => 'en', 'handed_by_id' => $by->id,
            'out_from' => now(), 'out_until' => now()->addDays(2), 'use_from' => today(), 'use_until' => today()->addDay(), 'vat_rate_bp' => 2100, 'net_cents' => 100000]);
        $o->lines()->create(['kind' => 'item', 'name' => 'Robe Spiider', 'qty' => 4, 'unit_price_cents' => 25000, 'amount_cents' => 100000, 'position' => 0]);

        return $o;
    }

    public function test_proforma_is_checked_sent_back_fixed_and_invoiced(): void
    {
        $sales = $this->as('sales', ['first_name' => 'Elke']);
        $boss = $this->as('manager');
        $o = $this->order($sales);

        $this->actingAs($sales)->post('/pre-invoicing/make/rental/'.$o->id)->assertSessionHasNoErrors()->assertRedirect();
        $pf = PreInvoice::firstOrFail();
        $this->assertSame('check', $pf->status);
        $this->assertSame('R-PF-2026-0102', $pf->source_code);
        $this->assertSame(100000, $pf->net_cents);
        $this->assertSame(21000, $pf->vat_cents);
        $this->assertSame(1, $pf->reds()); // the PO number is missing

        // Sales can follow it but cannot approve.
        $this->actingAs($sales)->post('/pre-invoicing/'.$pf->id.'/accept')->assertForbidden();
        $this->actingAs($this->as('warehouse'))->get('/pre-invoicing')->assertForbidden();

        $this->actingAs($boss)->get('/pre-invoicing?pf='.$pf->id)->assertOk()
            ->assertSee($pf->number)->assertSee('This customer needs a PO number on every invoice')->assertSee('VAT number valid in VIES');

        // A red flag blocks, unless overridden; here it goes back instead.
        $this->actingAs($boss)->post('/pre-invoicing/'.$pf->id.'/accept')->assertSessionHasErrors('override');
        $this->actingAs($boss)->post('/pre-invoicing/'.$pf->id.'/back', ['reason' => 'po', 'note' => 'Ask the customer for the PO'])->assertRedirect();
        $this->assertSame('back', $pf->fresh()->status);
        Notification::assertSentTo($sales, Confirmation::class);
        $this->actingAs($sales)->get('/dashboard')->assertSee('Proformas sent back');

        $o->update(['po_number' => 'PO-7781']);
        $this->actingAs($sales)->post('/pre-invoicing/'.$pf->id.'/resend', ['reply' => 'PO added'])->assertRedirect();
        $pf->refresh();
        $this->assertSame('check', $pf->status);
        $this->assertSame(0, $pf->reds());
        $this->assertSame('PO-7781', $pf->po_number);
        $this->actingAs($boss)->get('/dashboard')->assertSee('Pre-invoices to check');

        $this->actingAs($boss)->post('/pre-invoicing/'.$pf->id.'/accept', ['invoice_number' => 'INV 2026/0412'])->assertSessionHasNoErrors();
        $pf->refresh();
        $this->assertSame('sent', $pf->status);
        $this->assertSame(today()->addDays(30)->toDateString(), $pf->due_date->toDateString());
        $this->assertSame(1, PreInvoice::count()); // the same proforma, not a new one

        // Overdue: the late fee card, then paid.
        $this->travel(40)->days();
        $this->assertTrue($pf->fresh()->isOverdue());
        $this->actingAs($boss)->get('/pre-invoicing?f=late')->assertOk()->assertSee('10 days late');
        $this->actingAs($boss)->post('/pre-invoicing/'.$pf->id.'/late', ['decision' => 'skip'])->assertSessionHasErrors('reason');
        $this->actingAs($boss)->post('/pre-invoicing/'.$pf->id.'/late', ['decision' => 'on_payment'])->assertSessionHasNoErrors();
        $this->assertSame('on_payment', $pf->fresh()->reminder->decision);
        $this->actingAs($boss)->post('/pre-invoicing/'.$pf->id.'/paid')->assertRedirect();
        $this->assertSame('paid', $pf->fresh()->status);

        foreach (['finance.pf_made', 'finance.pf_back', 'finance.pf_resent', 'finance.pf_sent', 'finance.late_on_payment', 'finance.pf_paid'] as $a) {
            $this->assertTrue(AuditLog::where('action', $a)->exists(), $a);
        }
        $this->actingAs($boss)->get('/pre-invoicing/'.$pf->id.'/print')->assertOk()->assertSee('PO-7781');
    }

    public function test_override_sends_with_red_flags_and_the_page_speaks_four_languages(): void
    {
        $sales = $this->as('sales');
        $o = $this->order($sales);
        $o->company->forceFill(['vies_status' => 'invalid'])->save();
        $this->actingAs($sales)->post('/pre-invoicing/make/rental/'.$o->id);
        $pf = PreInvoice::firstOrFail();
        $this->assertSame(2, $pf->reds());

        foreach (['en' => 'Pre-invoicing', 'nl' => 'Voorfacturatie', 'fr' => 'Préfacturation', 'es' => 'Prefacturación'] as $loc => $word) {
            $u = $this->as('admin', ['locale' => $loc]);
            $this->actingAs($u)->get('/pre-invoicing')->assertOk()->assertSee($pf->number);
        }
        $this->actingAs($u)->post('/pre-invoicing/'.$pf->id.'/accept', ['override' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($u->id, $pf->fresh()->override_by_id);
        $this->actingAs($sales)->get('/order-allocation/'.$o->id)->assertSee($pf->number);
    }

    public function test_approved_orders_wait_in_ready_for_pre_invoicing(): void
    {
        $sales = $this->as('sales', ['first_name' => 'Elke']);
        $boss = $this->as('manager');
        $o = $this->order($sales);

        // Approved, no pre-invoice yet: on the order as step 4, on the dashboard and on the pre-invoicing page.
        $this->actingAs($sales)->get(route('orders.show', $o))->assertOk()->assertSee('Next step: send it to pre-invoicing below')->assertSee('Send to pre-invoicing');
        $this->actingAs($boss)->get('/dashboard')->assertSee('Orders to pre-invoice');
        $this->actingAs($boss)->get('/pre-invoicing')->assertOk()->assertSee('Ready for pre-invoicing')->assertSee('R-OC-2026-0102');

        $this->actingAs($boss)->post('/pre-invoicing/make/rental/'.$o->id)->assertRedirect();
        $this->actingAs($boss)->get('/pre-invoicing')->assertDontSee('Ready for pre-invoicing');
        $this->actingAs($sales)->get(route('orders.show', $o))->assertDontSee('Next step: send it to pre-invoicing below');
    }
}
