<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CompanyDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
    }

    private function companyForm(array $override = []): array
    {
        $c = CompanyDetail::current();

        return $override + [
            'legal_name' => $c->legal_name, 'legal_form' => $c->legal_form, 'vat_number' => $c->vat_number,
            'street' => $c->street, 'postcode' => $c->postcode, 'city' => $c->city, 'country' => $c->country,
            'warehouse_address' => $c->warehouse_address, 'website' => $c->website,
            'payment_term_days' => 0, 'quote_valid_days' => 30, 'option_days' => 3, 'footer_line' => $c->footer_line,
        ];
    }

    // ---------------------------------------------------------- access

    public function test_only_people_with_settings_edit_can_open_these_screens(): void
    {
        $manager = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id')]);

        foreach (['/settings/company', '/settings/vat-rates'] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->actingAs($manager)->put('/settings/company', $this->companyForm(['legal_name' => 'Hacked']))->assertForbidden();
        $this->assertSame('CLUK', CompanyDetail::current()->legal_name);
    }

    // ---------------------------------------------------- company details

    public function test_cluks_details_are_there_from_the_start(): void
    {
        $c = CompanyDetail::current();

        $this->assertSame('CLUK', $c->legal_name);
        $this->assertSame('BV', $c->legal_form);
        $this->assertSame('Karreweg 17B', $c->street);
        $this->assertSame('9870', $c->postcode);
        $this->assertSame('Zulte', $c->city);
        $this->assertSame('1010.948.054', $c->enterpriseNumber());
        $this->assertSame('0208:1010948054', $c->peppolId());

        $this->actingAs($this->admin)->get('/settings/company')->assertOk()
            ->assertSee('Karreweg 17B')->assertSee('1010.948.054');
    }

    public function test_details_are_saved_cleaned_and_logged(): void
    {
        $this->actingAs($this->admin)->put('/settings/company', $this->companyForm([
            'vat_number' => 'be 1010.948.054', 'phone' => '+32 9 000 00 00', 'payment_term_days' => 21,
        ]))->assertRedirect('/settings/company');

        $c = CompanyDetail::current();
        $this->assertSame('BE1010948054', $c->vat_number);
        $this->assertSame(21, $c->payment_term_days);

        $log = AuditLog::where('action', 'company.updated')->firstOrFail();
        $this->assertEqualsCanonicalizing(['phone', 'payment_term_days'], array_keys($log->data['changes']));
    }

    public function test_bank_accounts_are_added_checked_made_main_and_removed(): void
    {
        $this->actingAs($this->admin)->put('/settings/company', $this->companyForm([
            'banks' => ['new' => ['bank' => 'Test bank', 'iban' => 'BE68 5390 0754 7035', 'on_documents' => '1']],
        ]))->assertSessionHasErrors('banks.new.iban');
        $this->assertSame(0, BankAccount::count());

        $this->actingAs($this->admin)->put('/settings/company', $this->companyForm([
            'banks' => ['new' => ['bank' => 'Test bank', 'iban' => 'be68 5390 0754 7034', 'bic' => 'tstbbebb', 'on_documents' => '1']],
            'main_bank' => 'new',
        ]))->assertSessionHasNoErrors();

        $account = BankAccount::firstOrFail();
        $this->assertSame('BE68539007547034', $account->iban);
        $this->assertSame('BE68 5390 0754 7034', $account->formattedIban());
        $this->assertSame('TSTBBEBB', $account->bic);
        $this->assertTrue($account->is_main);
        $this->assertSame(1, AuditLog::where('action', 'company.bank_added')->count());

        // Emptying the IBAN removes it.
        $this->actingAs($this->admin)->put('/settings/company', $this->companyForm([
            'banks' => [$account->id => ['bank' => 'Test bank', 'iban' => '']],
        ]));
        $this->assertSame(0, BankAccount::count());
        $this->assertSame(1, AuditLog::where('action', 'company.bank_removed')->count());
    }

    public function test_iban_check(): void
    {
        $this->assertTrue(BankAccount::validIban('BE68539007547034'));
        $this->assertTrue(BankAccount::validIban('NL91 ABNA 0417 1643 00'));
        $this->assertFalse(BankAccount::validIban('BE68539007547035'));
        $this->assertFalse(BankAccount::validIban('hello'));
    }

    // -------------------------------------------------------- VAT rates

    public function test_the_belgian_rates_are_there_and_found_by_code(): void
    {
        $this->assertSame(2100, VatRate::forCode('21')->rate_bp);
        $this->assertSame(600, VatRate::forCode('06')->rate_bp);
        $this->assertSame(0, VatRate::forCode('46')->rate_bp);
        $this->assertSame('21%', VatRate::forCode('21')->percent());
        $this->assertSame(['21', '12', '06', '46', '47'], VatRate::activeChoices()->pluck('code')->all());
    }

    public function test_a_planned_change_applies_from_its_date_and_keeps_the_history(): void
    {
        $standard = VatRate::forCode('21');
        $from = today()->addYear()->startOfYear();

        $this->actingAs($this->admin)->post("/settings/vat-rates/{$standard->id}/schedule", ['new_rate' => '22', 'from' => $from->toDateString()])
            ->assertRedirect('/settings/vat-rates');

        $this->assertSame(2100, VatRate::forCode('21')->rate_bp, 'today nothing changes');
        $this->assertSame(2100, VatRate::forCode('21', $from->copy()->subDay())->rate_bp);
        $this->assertSame(2200, VatRate::forCode('21', $from)->rate_bp);
        $this->assertSame('22% standard rate', VatRate::forCode('21', $from)->name);
        $this->assertSame('planned', VatRate::forCode('21', $from)->status());
        $this->assertSame(1, AuditLog::where('action', 'vat.scheduled')->count());

        // A rate that was in force cannot be deleted…
        $this->actingAs($this->admin)->delete("/settings/vat-rates/{$standard->id}")->assertSessionHasErrors('rate');

        // …the planned one can, and the current rate runs on.
        $planned = VatRate::forCode('21', $from);
        $this->actingAs($this->admin)->delete("/settings/vat-rates/{$planned->id}")->assertSessionHasNoErrors();
        $this->assertNull($standard->fresh()->valid_until);
        $this->assertSame(2100, VatRate::forCode('21', $from)->rate_bp);
    }

    public function test_a_change_cannot_be_planned_in_the_past(): void
    {
        $standard = VatRate::forCode('21');

        $this->actingAs($this->admin)->post("/settings/vat-rates/{$standard->id}/schedule", ['new_rate' => '22', 'from' => today()->subDay()->toDateString()])
            ->assertSessionHasErrors('from');
        $this->assertNull($standard->fresh()->valid_until);
    }

    public function test_two_rates_with_the_same_code_cannot_overlap(): void
    {
        $this->actingAs($this->admin)->post('/settings/vat-rates', [
            'name' => 'Another 21', 'rate' => '21', 'code' => '21', 'zone' => 'be', 'valid_from' => today()->toDateString(),
        ])->assertSessionHasErrors('valid_from');

        $this->actingAs($this->admin)->post('/settings/vat-rates', [
            'name' => '0% · cocontractant', 'rate' => '0', 'code' => '45', 'zone' => 'be', 'valid_from' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, VatRate::forCode('45')->rate_bp);
        $this->assertSame(1, AuditLog::where('action', 'vat.created')->count());
    }

    public function test_a_rate_can_be_edited_and_ended(): void
    {
        $reduced = VatRate::forCode('12');

        $this->actingAs($this->admin)->put("/settings/vat-rates/{$reduced->id}", [
            'name' => '12% reduced rate (catering)', 'rate' => '12', 'code' => '12', 'zone' => 'be', 'valid_from' => '1996-01-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame('12% reduced rate (catering)', $reduced->fresh()->name);
        $this->assertSame(['name'], array_keys(AuditLog::where('action', 'vat.updated')->firstOrFail()->data['changes']));

        $this->actingAs($this->admin)->post("/settings/vat-rates/{$reduced->id}/end");
        $this->assertNotNull(VatRate::forCode('12'), 'still valid until the end of today');
        $this->assertNull(VatRate::forCode('12', today()->addDay()));
    }

    public function test_default_vat_per_kind_of_customer(): void
    {
        $this->actingAs($this->admin)->put('/settings/vat-rates/defaults', [
            'vat_default_be' => '21', 'vat_default_eu_business' => '46', 'vat_default_eu_private' => '21', 'vat_default_outside_eu' => '99',
        ])->assertSessionHasErrors('vat_default_outside_eu');

        $this->actingAs($this->admin)->put('/settings/vat-rates/defaults', [
            'vat_default_be' => '21', 'vat_default_eu_business' => '46', 'vat_default_eu_private' => '21', 'vat_default_outside_eu' => '21',
        ])->assertSessionHasNoErrors();

        $this->assertSame('21', CompanyDetail::current()->vat_default_outside_eu);
        $this->assertSame(1, AuditLog::where('action', 'vat.defaults_changed')->count());
    }

    public function test_the_screens_render_in_every_language(): void
    {
        $standard = VatRate::forCode('21');
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $this->admin->forceFill(['locale' => $locale])->save();
            foreach (['/settings/company', '/settings/vat-rates', '/settings/vat-rates?edit=new', "/settings/vat-rates?edit={$standard->id}&schedule=1"] as $url) {
                $html = $this->actingAs($this->admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(settings|users|cluk|nav)\.[a-z_]+\.[a-z_]+/', strip_tags($html), "untranslated text on {$url} ({$locale})");
            }
        }
    }
}
