<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Finance\PreInvoice;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Finance\OkiOki\OkiOki;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Invoices made by the app and mailed to OkiOki as Peppol e-invoices. */
class OkiOkiTest extends TestCase
{
    use RefreshDatabase;

    private User $boss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Storage::fake('local');
        $this->boss = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id'), 'locale' => 'en']);
        BankAccount::create(['bank' => 'KBC', 'iban' => 'BE68539007547034', 'bic' => 'KREDBEBB', 'is_main' => true, 'on_documents' => true]);
    }

    private function pf(array $company = [], int $discount = 0): PreInvoice
    {
        $co = Company::create($company + ['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en',
            'country' => 'BE', 'vat_number' => 'BE0123456749', 'street' => 'Stationsstraat 1', 'postcode' => '9870', 'city' => 'Zulte', 'email' => 'invoices@demo.be', 'customer_payment_days' => 30]);
        $co->forceFill(['vies_status' => 'valid'])->save();
        $o = Order::create(['number' => 'R-OC-2026-'.str_pad((string) (Order::count() + 1), 4, '0', STR_PAD_LEFT), 'status' => 'approved', 'company_id' => $co->id, 'title' => 'Light rig', 'language' => 'en', 'handed_by_id' => $this->boss->id,
            'out_from' => now(), 'out_until' => now()->addDays(2), 'use_from' => today(), 'use_until' => today()->addDay(), 'vat_rate_bp' => 2100,
            'subtotal_cents' => 100000, 'project_discount_bp' => $discount ? 1000 : 0, 'discount_cents' => $discount, 'net_cents' => 100000 - $discount, 'po_number' => 'PO-77']);
        $o->lines()->create(['kind' => 'item', 'name' => 'Robe Spiider', 'qty' => 3, 'unit_price_cents' => 33333, 'factor' => 1, 'amount_cents' => 100000, 'position' => 0]);
        $this->actingAs($this->boss)->post('/pre-invoicing/make/rental/'.$o->id)->assertRedirect();

        return PreInvoice::latest('id')->firstOrFail();
    }

    private function switchOn(array $extra = []): void
    {
        $this->boss->extraRights()->create(['right' => 'finance.send_to_okioki']);
        $this->actingAs($this->boss->fresh())->post('/pre-invoicing/okioki-settings', $extra + ['okioki_send' => 1, 'okioki_inbox' => 'cluk@okioki.test', 'invoice_prefix' => '{year}-', 'invoice_next' => 41])
            ->assertSessionHasNoErrors()->assertRedirect();
    }

    private function xpath(string $xml): DOMXPath
    {
        $d = new DOMDocument;
        $this->assertTrue($d->loadXML($xml));
        $x = new DOMXPath($d);
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ub'.'l:schema:xsd:CommonBasicComponents-2');
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ub'.'l:schema:xsd:CommonAggregateComponents-2');

        return $x;
    }

    public function test_approval_numbers_the_invoice_and_mails_the_e_invoice_to_okioki(): void
    {
        $this->actingAs($this->boss)->post('/pre-invoicing/okioki-settings', ['okioki_send' => 1, 'okioki_inbox' => 'x@okioki.test', 'invoice_next' => 1])->assertForbidden();
        $this->switchOn();
        $pf = $this->pf([], 10000);
        $this->actingAs($this->boss->fresh())->get('/pre-invoicing?pf='.$pf->id)->assertOk()->assertSee('This invoice gets number '.now()->year.'-0041');

        $this->actingAs($this->boss->fresh())->post(route('finance.accept', $pf))->assertSessionHasNoErrors()->assertRedirect();
        $pf->refresh();
        $this->assertSame([now()->year.'-0041', 'sent'], [$pf->invoice_number, $pf->status]);
        $this->assertNotNull($pf->okioki_sent_at);
        $this->assertSame(42, CompanyDetail::current()->invoice_next);
        Notification::assertSentOnDemand(Confirmation::class, fn ($n, $ch, $to) => $to->routes['mail'] === 'cluk@okioki.test' && $n->attachFiles[0]['mime'] === 'application/xml'
            && ($n->attachFiles[1]['mime'] ?? null) === 'application/pdf' && str_ends_with($n->attachFiles[1]['name'], '.pdf'));
        // The printable invoice: inside the e-invoice, and to download.
        $this->assertStringContainsString('<cbc:EmbeddedDocumentBinaryObject mimeCode="application/pdf"', Storage::disk('local')->get($pf->ubl_path));
        $res = $this->actingAs($this->boss->fresh())->get(route('finance.pdf', $pf))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->streamedContent());

        $x = $this->xpath(Storage::disk('local')->get($pf->ubl_path));
        $v = fn ($q) => $x->evaluate('string('.$q.')');
        $this->assertSame(now()->year.'-0041', $v('/*/cbc:ID'));
        $this->assertSame('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0', $v('/*/cbc:CustomizationID'));
        $this->assertSame(['0208', '1010948054'], [$v('//cac:AccountingSupplierParty//cbc:EndpointID/@schemeID'), $v('//cac:AccountingSupplierParty//cbc:EndpointID')]);
        $this->assertSame(['0208', '0123456749'], [$v('//cac:AccountingCustomerParty//cbc:EndpointID/@schemeID'), $v('//cac:AccountingCustomerParty//cbc:EndpointID')]);
        $this->assertSame('PO-77', $v('/*/cbc:BuyerReference'));
        // 1000 − 100 discount = 900 + 21% = 1089
        $this->assertSame(['1000.00', '100.00', '900.00', '189.00', '1089.00'], [$v('//cac:LegalMonetaryTotal/cbc:LineExtensionAmount'), $v('//cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount'), $v('//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'), $v('/*/cac:TaxTotal/cbc:TaxAmount'), $v('//cac:LegalMonetaryTotal/cbc:PayableAmount')]);
        $this->assertSame(['S', '21'], [$v('//cac:TaxSubtotal/cac:TaxCategory/cbc:ID'), $v('//cac:TaxSubtotal/cac:TaxCategory/cbc:Percent')]);
        $this->assertSame('BE68539007547034', $v('//cac:PayeeFinancialAccount/cbc:ID'));
        $this->assertSame(['3', '333.3333'], [$v('//cac:InvoiceLine/cbc:InvoicedQuantity'), $v('//cac:InvoiceLine/cac:Price/cbc:PriceAmount')]);

        // The next one follows on; the file can be downloaded.
        $pf2 = $this->pf(['name' => 'Second BV', 'vat_number' => 'BE0987654321']);
        $this->actingAs($this->boss->fresh())->post(route('finance.accept', $pf2))->assertRedirect();
        $this->assertSame(now()->year.'-0042', $pf2->fresh()->invoice_number);
        $this->actingAs($this->boss->fresh())->get(route('finance.ubl', $pf2))->assertOk()->assertDownload();
    }

    public function test_eu_business_gets_reverse_charge_and_a_new_year_restarts_the_series(): void
    {
        $this->switchOn(['invoice_next' => 77]);
        CompanyDetail::current()->forceFill(['invoice_year' => now()->year - 1])->save();
        $pf = $this->pf(['name' => 'Demo Licht GmbH', 'country' => 'DE', 'vat_number' => 'DE123456789', 'street' => 'Hauptstr. 1', 'postcode' => '10115', 'city' => 'Berlin']);
        $this->assertSame([0, '46'], [$pf->vat_rate_bp, $pf->vat_code]);
        $this->actingAs($this->boss->fresh())->post(route('finance.accept', $pf), ['override' => 1])->assertRedirect();
        $pf->refresh();
        $this->assertSame(now()->year.'-0001', $pf->invoice_number);
        $x = $this->xpath(Storage::disk('local')->get($pf->ubl_path));
        $v = fn ($q) => $x->evaluate('string('.$q.')');
        $this->assertSame(['AE', 'VATEX-EU-AE', '0.00'], [$v('//cac:TaxSubtotal/cac:TaxCategory/cbc:ID'), $v('//cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode'), $v('/*/cac:TaxTotal/cbc:TaxAmount')]);
        $this->assertSame(['9930', 'DE123456789'], [$v('//cac:AccountingCustomerParty//cbc:EndpointID/@schemeID'), $v('//cac:AccountingCustomerParty//cbc:EndpointID')]);
    }

    public function test_without_the_link_the_number_is_typed_in_as_before(): void
    {
        $pf = $this->pf();
        $this->actingAs($this->boss)->get('/pre-invoicing?pf='.$pf->id)->assertSee('OkiOki invoice number');
        $this->actingAs($this->boss)->post(route('finance.accept', $pf), ['invoice_number' => 'INV 2026/0007', 'override' => 1])->assertRedirect();
        $this->assertSame(['INV 2026/0007', null], [$pf->fresh()->invoice_number, $pf->fresh()->ubl_path]);
        Notification::assertNothingSentTo(new AnonymousNotifiable);
    }

    public function test_a_planned_change_of_the_numbering_starts_on_its_day(): void
    {
        $this->travelTo('2026-10-26 10:00');
        $this->switchOn(['invoice_prefix' => 'INV-', 'invoice_next' => 1129, 'invoice_change_on' => '2027-01-01', 'invoice_change_prefix' => '{year}/', 'invoice_change_next' => 1]);
        $this->actingAs($this->boss->fresh())->get('/pre-invoicing')->assertOk()->assertSee('Next invoice now: INV-1129 · from 01/01/2027: 2027/0001.');
        $this->assertSame('INV-1129', OkiOki::nextNumber());
        $this->assertSame('INV-1130', OkiOki::nextNumber());
        $this->travelTo('2026-12-31 23:00');
        $this->assertSame('INV-1131', OkiOki::nextNumber());
        $this->travelTo('2027-01-01 08:00');
        $this->assertSame('2027/0001', OkiOki::preview());
        $this->assertSame('2027/0001', OkiOki::nextNumber());
        $this->assertSame('2027/0002', OkiOki::nextNumber());
        $me = CompanyDetail::current()->fresh();
        $this->assertSame(['{year}/', 3, null], [$me->invoice_prefix, $me->invoice_next, $me->invoice_change_on]);
        // And the next year the {year} series restarts by itself.
        $this->travelTo('2028-01-02 08:00');
        $this->assertSame('2028/0001', OkiOki::nextNumber());
        // A change date must be in the future.
        $this->actingAs($this->boss->fresh())->post('/pre-invoicing/okioki-settings', ['okioki_send' => 1, 'okioki_inbox' => 'cluk@okioki.test', 'invoice_next' => 5, 'invoice_change_on' => '2020-01-01', 'invoice_change_prefix' => 'X'])->assertSessionHasErrors('invoice_change_on');
    }

    public function test_an_invoice_approved_before_the_link_was_on_can_be_numbered_and_sent_later(): void
    {
        $pf = $this->pf([], 10000);
        $this->actingAs($this->boss)->post(route('finance.accept', $pf))->assertSessionHasNoErrors(); // link still off
        $this->assertSame(['sent', null], [$pf->fresh()->status, $pf->fresh()->invoice_number]);
        $this->switchOn(['invoice_prefix' => 'INV-', 'invoice_next' => 1129]);
        $this->actingAs($this->boss->fresh())->get('/pre-invoicing?pf='.$pf->id)->assertSee('Give it number INV-1129 and send to OkiOki');
        $this->actingAs($this->boss->fresh())->post(route('finance.okioki.resend', $pf))->assertSessionHasNoErrors();
        $pf->refresh();
        $this->assertSame('INV-1129', $pf->invoice_number);
        $this->assertNotNull($pf->okioki_sent_at);
        $this->assertSame(1, AuditLog::where('action', 'finance.pf_numbered')->count());
    }
}
