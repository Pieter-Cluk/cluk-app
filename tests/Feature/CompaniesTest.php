<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use App\Services\Vies;
use App\Support\VatNumber;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompaniesTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    /** What the fake VIES answers: true, false, or 'down'. */
    private bool|string $vies = true;

    private string $viesName = 'DEMO AGENCY';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->sales = $this->person('sales', ['first_name' => 'Elke', 'is_commercial' => true]);
        Http::preventStrayRequests();
        Http::fake([Vies::URL => fn () => $this->vies === 'down'
            ? Http::response(['errorWrappers' => [['error' => 'MS_UNAVAILABLE']]], 500)
            : Http::response([
                'countryCode' => 'BE', 'vatNumber' => '0000000000', 'requestDate' => now()->toIso8601String(),
                'valid' => $this->vies, 'name' => $this->viesName, 'address' => 'Demostraat 12, 9870 Zulte',
            ])]);
    }

    private function person(string $role, array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function viesAnswers(bool|string $valid, string $name = 'DEMO AGENCY'): void
    {
        $this->vies = $valid;
        $this->viesName = $name;
    }

    private function form(array $override = []): array
    {
        return $override + [
            'name' => 'Demo Agency', 'legal_form' => 'BV', 'is_customer' => '1', 'country' => 'BE',
            'vat_number' => 'BE 1010.948.054', 'city' => 'Zulte', 'language' => 'nl',
        ];
    }

    // ------------------------------------------------------------- access

    public function test_view_edit_and_archive_follow_the_module_level(): void
    {
        $company = Company::factory()->create();
        $readOnly = $this->person('read-only');     // companies level 1
        $crew = $this->person('workspace-host');    // companies level 1
        $manager = $this->person('manager');        // companies level 2

        $this->actingAs($readOnly)->get('/companies')->assertOk();
        $this->actingAs($readOnly)->get('/companies/'.$company->id)->assertOk()->assertDontSee(route('companies.edit', $company));
        $this->actingAs($readOnly)->get('/companies/new')->assertForbidden();
        $this->actingAs($crew)->post('/companies', $this->form())->assertForbidden();

        $this->actingAs($manager)->get('/companies/'.$company->id.'/edit')->assertOk();
        $this->actingAs($manager)->post('/companies/'.$company->id.'/archive')->assertForbidden();

        $admin = $this->person('admin');
        $this->actingAs($admin)->post('/companies/'.$company->id.'/archive')->assertRedirect('/companies');
        $this->assertNotNull($company->fresh()->archived_at);

        $noAccess = User::factory()->create(['role_id' => Role::where('slug', 'asset-manager')->value('id')]);
        $noAccess->setModuleOverride('companies', 0);
        $this->actingAs($noAccess->fresh())->get('/companies')->assertForbidden();
    }

    // ------------------------------------------------------- VAT numbers

    public function test_vat_numbers_are_cleaned_checked_and_printed(): void
    {
        $this->assertSame('BE1010948054', VatNumber::normalize('be 1010.948.054'));
        $this->assertSame('BE1010948054', VatNumber::normalize('1010948054', 'BE'));
        $this->assertSame('NL123456789B01', VatNumber::normalize('NL 1234.56.789.B01'));
        $this->assertSame('EL123456789', VatNumber::normalize('123456789', 'GR'));
        $this->assertTrue(VatNumber::looksValid('BE1010948054'));
        $this->assertFalse(VatNumber::looksValid('BE1010948055'), 'Belgian check digits');
        $this->assertSame('BE 1010.948.054', VatNumber::format('BE1010948054'));
        $this->assertNull(VatNumber::normalize('  '));
    }

    public function test_a_new_company_is_checked_in_vies_and_gets_its_vat_treatment(): void
    {
        $this->viesAnswers(true);

        $response = $this->actingAs($this->sales)->post('/companies', $this->form());

        $company = Company::firstOrFail();
        $response->assertRedirect('/companies/'.$company->id);
        $this->assertSame('BE1010948054', $company->vat_number);
        $this->assertSame('valid', $company->vies_status);
        $this->assertSame('DEMO AGENCY', $company->vies_name);
        $this->assertNotNull($company->vies_checked_at);
        $this->assertSame('be', $company->vatSituation());
        $this->assertSame('21', $company->vatCode());
        $this->assertSame(2100, $company->vatRate()->rate_bp);
        $this->assertSame(1, AuditLog::where('action', 'crm.company_created')->count());

        Http::assertSent(fn (HttpRequest $r) => $r->url() === Vies::URL && $r['countryCode'] === 'BE' && $r['vatNumber'] === '1010948054');
    }

    public function test_eu_business_eu_private_and_outside_eu_get_the_right_code(): void
    {
        $this->viesAnswers(true, 'DEMO STAGING BV');
        $this->actingAs($this->sales)->post('/companies', $this->form(['name' => 'Demo Staging', 'country' => 'NL', 'vat_number' => 'NL123456789B01']));
        $nl = Company::where('name', 'Demo Staging')->firstOrFail();
        $this->assertSame('eu_business', $nl->vatSituation());
        $this->assertSame('46', $nl->vatCode());
        $this->assertSame(0, $nl->vatRate()->rate_bp);

        $this->viesAnswers(false);
        $this->actingAs($this->sales)->post('/companies', $this->form(['name' => 'Demo Events', 'country' => 'ES', 'vat_number' => 'ESB12345678']));
        $es = Company::where('name', 'Demo Events')->firstOrFail();
        $this->assertSame('invalid', $es->vies_status);
        $this->assertSame('eu_private', $es->vatSituation());
        $this->assertSame('21', $es->vatCode());

        $this->actingAs($this->sales)->post('/companies', $this->form(['name' => 'Demo AV', 'country' => 'GB', 'vat_number' => 'GB123456789']));
        $gb = Company::where('name', 'Demo AV')->firstOrFail();
        $this->assertNull($gb->vies_status, 'no VIES check outside the EU');
        $this->assertSame('47', $gb->vatCode());

        // The defaults come from Settings › VAT rates.
        CompanyDetail::current()->update(['vat_default_eu_private' => '46']);
        $this->assertSame('46', $es->fresh()->vatCode());
    }

    public function test_vies_being_down_never_blocks_saving(): void
    {
        $this->viesAnswers('down');

        $this->actingAs($this->sales)->post('/companies', $this->form())->assertSessionHasNoErrors();

        $company = Company::firstOrFail();
        $this->assertSame('unavailable', $company->vies_status);
        $this->assertSame('be', $company->vatSituation());

        // Check again later.
        $this->viesAnswers(true);
        $this->actingAs($this->sales)->post('/companies/'.$company->id.'/vies')->assertSessionHas('status');
        $this->assertSame('valid', $company->fresh()->vies_status);
    }

    public function test_a_wrong_or_duplicate_vat_number_is_refused(): void
    {
        $this->actingAs($this->sales)->post('/companies', $this->form(['vat_number' => 'BE 1010.948.055']))
            ->assertSessionHasErrors('vat_number');

        Company::factory()->create(['name' => 'First one', 'vat_number' => 'BE1010948054']);
        $this->actingAs($this->sales)->post('/companies', $this->form())
            ->assertSessionHasErrors('vat_number');
        $this->assertSame(1, Company::count());
    }

    public function test_customer_or_supplier_must_be_ticked(): void
    {
        $this->actingAs($this->sales)->post('/companies', $this->form(['is_customer' => '0', 'vat_number' => '']))
            ->assertSessionHasErrors('is_customer');
    }

    public function test_changing_the_vat_number_checks_vies_again(): void
    {
        $this->viesAnswers(true);
        $company = Company::factory()->create(['vat_number' => null]);

        $this->actingAs($this->sales)->put('/companies/'.$company->id, $this->form(['name' => $company->name]))->assertRedirect();

        $this->assertSame('valid', $company->fresh()->vies_status);
        Http::assertSentCount(1);
        $log = AuditLog::where('action', 'crm.company_updated')->firstOrFail();
        $this->assertContains('vat_number', $log->data['fields']);
    }

    // --------------------------------------------------------- VAT treatment

    public function test_only_finance_and_admin_change_the_vat_treatment_and_it_is_logged(): void
    {
        $company = Company::factory()->create(['country' => 'BE']);

        $this->actingAs($this->sales)->put('/companies/'.$company->id.'/vat', ['vat_regime' => '06'])->assertForbidden();

        $finance = $this->person('finance');
        $this->actingAs($finance)->get('/companies/'.$company->id)->assertOk()->assertSee('name="vat_regime"', false);
        $this->actingAs($finance)->put('/companies/'.$company->id.'/vat', ['vat_regime' => '99'])->assertSessionHasErrors('vat_regime');
        $this->actingAs($finance)->put('/companies/'.$company->id.'/vat', ['vat_regime' => '06'])->assertSessionHasNoErrors();

        $this->assertSame('06', $company->fresh()->vatCode());
        $log = AuditLog::where('action', 'crm.vat_regime_changed')->firstOrFail();
        $this->assertSame(['from' => 'auto', 'to' => '06', 'code' => '06'], $log->data);

        $this->actingAs($finance)->put('/companies/'.$company->id.'/vat', ['vat_regime' => 'auto']);
        $this->assertSame('21', $company->fresh()->vatCode());
    }

    // ------------------------------------------------------------ contacts

    public function test_contacts_are_added_linked_across_companies_and_removed(): void
    {
        $agency = Company::factory()->create(['name' => 'Demo Agency']);
        $production = Company::factory()->create(['name' => 'Demo Production']);

        $this->actingAs($this->sales)->post("/companies/{$agency->id}/contacts", [
            'first_name' => 'Sarah', 'last_name' => 'Peeters', 'email' => 'Sarah@Demo-Agency.example',
            'job_title' => 'Event manager', 'is_main' => '1', 'newsletter' => '1',
        ])->assertRedirect("/companies/{$agency->id}");

        $sarah = Contact::firstOrFail();
        $this->assertSame('sarah@demo-agency.example', $sarah->email);
        $this->assertTrue($sarah->newsletter);
        $this->assertNotNull($sarah->newsletter_changed_at);
        $this->assertTrue((bool) $agency->contacts()->first()->pivot->is_main);

        // Same email at another company: linked, not copied.
        $this->actingAs($this->sales)->post("/companies/{$production->id}/contacts", [
            'first_name' => 'Sarah', 'email' => 'sarah@demo-agency.example', 'job_title' => 'Freelance producer',
        ]);
        $this->assertSame(1, Contact::count());
        $this->assertSame(['Demo Agency', 'Demo Production'], $sarah->companies()->orderBy('name')->pluck('name')->all());
        $this->actingAs($this->sales)->get("/companies/{$production->id}")->assertSee('Also at Demo Agency');

        // A second main contact takes over "main".
        $this->actingAs($this->sales)->post("/companies/{$agency->id}/contacts", ['first_name' => 'Tom', 'is_main' => '1']);
        $this->assertSame(['Tom'], $agency->contacts()->wherePivot('is_main', true)->pluck('first_name')->all());

        // Removing from one company keeps the person; from the last one deletes them.
        $this->actingAs($this->sales)->delete("/companies/{$agency->id}/contacts/{$sarah->id}");
        $this->assertNotNull($sarah->fresh());
        $this->actingAs($this->sales)->delete("/companies/{$production->id}/contacts/{$sarah->id}");
        $this->assertNull($sarah->fresh());
    }

    public function test_a_contact_is_edited_on_the_company_card(): void
    {
        $agency = Company::factory()->create();
        $contact = Contact::factory()->create(['first_name' => 'Lien']);
        $agency->contacts()->attach($contact->id, ['job_title' => 'Finance']);

        $this->actingAs($this->sales)->put("/companies/{$agency->id}/contacts/{$contact->id}", [
            'first_name' => 'Lien', 'last_name' => 'Vermeulen', 'email' => $contact->email, 'job_title' => 'Finance · invoices', 'is_billing' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Vermeulen', $contact->fresh()->last_name);
        $pivot = $agency->contacts()->first()->pivot;
        $this->assertSame('Finance · invoices', $pivot->job_title);
        $this->assertTrue((bool) $pivot->is_billing);

        $other = Company::factory()->create();
        $this->actingAs($this->sales)->put("/companies/{$other->id}/contacts/{$contact->id}", ['first_name' => 'X'])->assertNotFound();
    }

    public function test_delivery_addresses(): void
    {
        $agency = Company::factory()->create();

        $this->actingAs($this->sales)->post("/companies/{$agency->id}/addresses", [
            'label' => 'Warehouse', 'street' => 'Industrieweg 4', 'postcode' => '9000', 'city' => 'Gent', 'country' => 'be',
        ])->assertSessionHasNoErrors();
        $address = $agency->addresses()->firstOrFail();
        $this->assertSame('Industrieweg 4, 9000 Gent', $address->line());

        $this->actingAs($this->sales)->delete("/companies/{$agency->id}/addresses/{$address->id}");
        $this->assertSame(0, $agency->addresses()->count());
    }

    // ---------------------------------------------------------------- list

    public function test_the_list_has_tabs_counts_search_and_filters(): void
    {
        Company::factory()->create(['name' => 'Demo Agency', 'city' => 'Zulte', 'vat_number' => 'BE1010948054']);
        Company::factory()->create(['name' => 'Demo Rental', 'is_supplier' => true, 'city' => 'Gent']);
        Company::factory()->supplier()->create(['name' => 'Demo Light Hire', 'country' => 'DE', 'supplies' => ['subhire']]);
        Company::factory()->supplier()->create(['name' => 'Demo Logistics', 'supplies' => ['transport']]);
        Company::factory()->create(['name' => 'Old Customer', 'archived_at' => now()]);

        $this->actingAs($this->sales)->get('/companies')->assertOk()
            ->assertSee('Demo Agency')->assertSee('Demo Light Hire')->assertDontSee('Old Customer');

        $this->actingAs($this->sales)->get('/companies?view=customers')->assertSee('Demo Rental')->assertDontSee('Demo Logistics');
        $this->actingAs($this->sales)->get('/companies?view=suppliers')->assertSee('Demo Logistics')->assertDontSee('Demo Agency');
        $this->actingAs($this->sales)->get('/companies?view=both')->assertSee('Demo Rental')->assertDontSee('Demo Agency');
        $this->actingAs($this->sales)->get('/companies?view=suppliers&supplies=transport')->assertSee('Demo Logistics')->assertDontSee('Demo Light Hire');
        $this->actingAs($this->sales)->get('/companies?country=DE')->assertSee('Demo Light Hire')->assertDontSee('Demo Agency');
        $this->actingAs($this->sales)->get('/companies?rel=supplier')->assertSee('Demo Logistics')->assertDontSee('Demo Rental');

        // VAT number search ignores dots and spaces; city search works too.
        $this->actingAs($this->sales)->get('/companies?q='.urlencode('1010.948'))->assertSee('Demo Agency')->assertDontSee('Demo Rental');
        $this->actingAs($this->sales)->get('/companies?q=gent')->assertSee('Demo Rental')->assertDontSee('Demo Agency');
    }

    public function test_the_contacts_tab_searches_people_and_their_company(): void
    {
        $agency = Company::factory()->create(['name' => 'Demo Agency']);
        $agency->contacts()->attach(Contact::factory()->create(['first_name' => 'Sarah', 'last_name' => 'Peeters'])->id);
        Contact::factory()->create(['first_name' => 'Ben', 'last_name' => 'Walker']);

        $this->actingAs($this->sales)->get('/companies?view=contacts')->assertSee('Sarah Peeters')->assertSee('Ben Walker');
        $this->actingAs($this->sales)->get('/companies?view=contacts&q=agency')->assertSee('Sarah Peeters')->assertDontSee('Ben Walker');
    }

    public function test_the_screens_render_in_every_language(): void
    {
        $this->viesAnswers(true);
        $company = Company::factory()->create(['is_supplier' => true, 'supplies' => ['rigging'], 'vat_number' => 'BE1010948054', 'vies_status' => 'valid', 'vies_checked_at' => now(), 'notes' => 'Test']);
        $company->contacts()->attach(Contact::factory()->create()->id, ['is_main' => true]);
        $company->addresses()->create(['label' => 'Warehouse', 'street' => 'Industrieweg 4', 'city' => 'Gent', 'country' => 'BE']);
        $admin = $this->person('admin');

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/companies', '/companies?view=suppliers', '/companies?view=contacts', '/companies/new', "/companies/{$company->id}", "/companies/{$company->id}/edit"] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(companies|settings|users|cluk|nav)\.[a-z_]+\.[a-z_]+/', strip_tags($html), "untranslated text on {$url} ({$locale})");
            }
        }
    }
}
