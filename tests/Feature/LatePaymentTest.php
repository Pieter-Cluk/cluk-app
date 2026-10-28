<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Finance\PreInvoice;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\OkiOki\UblInvoice;
use App\Services\Finance\PreInvoices;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Overdue invoices: a reminder tile for the accountant, and the interest proforma from the decision. */
class LatePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(int $daysLate): PreInvoice
    {
        $co = Company::create(['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl', 'country' => 'BE',
            'vat_number' => 'BE0123456749', 'street' => 'Stationsstraat 1', 'postcode' => '9870', 'city' => 'Zulte']);

        return PreInvoice::create(['number' => 'R-PF-2026-'.random_int(1000, 9999), 'source_type' => 'rental', 'source_id' => 1, 'module' => 'dryhire', 'company_id' => $co->id, 'description' => 'Gala',
            'lines' => [], 'status' => 'sent', 'invoice_number' => 'INV-1140', 'net_cents' => 100000, 'vat_cents' => 21000, 'gross_cents' => 121000, 'vat_code' => '21', 'vat_rate_bp' => 2100,
            'invoice_date' => today()->subDays(30 + $daysLate), 'due_date' => today()->subDays($daysLate)]);
    }

    public function test_an_overdue_invoice_waits_on_the_dashboard_and_invoice_now_makes_the_interest_proforma(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $boss = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $pf = $this->invoice(73);

        $this->actingAs($boss, 'web')->get('/dashboard')->assertOk()->assertSee('Late payments to decide');
        $this->actingAs($boss, 'web')->post('/pre-invoicing/'.$pf->id.'/late', ['decision' => 'now'])->assertSessionHasNoErrors();

        $lp = PreInvoice::where('source_type', 'interest')->firstOrFail();
        $expected = (int) round(121000 * (float) config('afrit6.payment.statutory_rate') / 100 * 73 / 365);
        $this->assertSame($expected + (int) config('afrit6.payment.fixed_compensation'), $lp->net_cents);
        $this->assertSame([0, 'NS', 'check'], [$lp->vat_cents, $lp->vat_code, $lp->status]);
        $this->assertSame('LP-INV-1140', $lp->source_code);
        $this->assertStringContainsString('Verwijlinterest op factuur INV-1140 (73 dagen', $lp->lines[0]['d']);
        $this->assertSame($lp->id, $pf->fresh()->reminder->pre_invoice_made_id);
        $this->assertSame('E', UblInvoice::category($lp)[0]);
        $this->assertContains('vat_fits', array_column($lp->checks, 'key'));
        $this->actingAs($boss, 'web')->get('/dashboard')->assertOk()->assertDontSee('Late payments to decide');
        $this->actingAs($boss, 'web')->get(route('finance.index', ['pf' => $pf->id]))->assertOk()->assertSee('Interest proforma');

        // Deciding again does not make a second one.
        $this->actingAs($boss, 'web')->post('/pre-invoicing/'.$pf->id.'/late', ['decision' => 'now'])->assertSessionHasNoErrors();
        $this->assertSame(1, PreInvoice::where('source_type', 'interest')->count());
    }

    public function test_when_they_pay_the_interest_runs_until_the_payment(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $boss = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $pf = $this->invoice(10);
        PreInvoices::decide($pf, 'on_payment', null, $boss);
        $this->assertSame(0, PreInvoice::where('source_type', 'interest')->count());

        $this->travel(20)->days();
        PreInvoices::paid($pf->fresh(), $boss);
        $lp = PreInvoice::where('source_type', 'interest')->firstOrFail();
        $this->assertSame(30, $pf->fresh()->reminder->days);
        $this->assertSame((int) round(121000 * (float) config('afrit6.payment.statutory_rate') / 100 * 30 / 365) + (int) config('afrit6.payment.fixed_compensation'), $lp->net_cents);

        // Skip: nothing is made.
        $other = $this->invoice(5);
        PreInvoices::decide($other, 'skip', 'Good customer', $boss);
        $this->assertSame(1, PreInvoice::where('source_type', 'interest')->count());
    }
}
