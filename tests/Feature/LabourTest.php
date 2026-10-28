<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\Sales\LabourType;
use App\Models\Sales\Quote;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Labour types with a rate (Settings › Labour rates), added to a rental or Cluk Factory quote as people × hours. */
class LabourTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function quote(User $sales, string $language = 'nl'): Quote
    {
        $c = Company::create(['name' => 'Demo Agency BV', 'country' => 'BE', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => $language]);
        $p = Contact::create(['first_name' => 'Sarah', 'last_name' => 'Peeters', 'email' => 'sarah@demo.be', 'language' => $language]);
        $c->contacts()->attach($p->id);
        $deal = Deal::create(['number' => Deal::nextNumber(), 'title' => 'Company evening', 'module' => 'dryhire', 'stage' => 'qualified', 'company_id' => $c->id, 'contact_id' => $p->id, 'owner_id' => $sales->id, 'event_start' => '2026-11-14', 'event_end' => '2026-11-15', 'check_date' => true, 'check_budget' => true, 'check_decider' => true]);
        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/quotes')->assertRedirect();

        return Quote::firstOrFail();
    }

    public function test_rates_are_set_in_settings_and_logged(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->as('admin');
        $this->assertSame(4, LabourType::count());
        $this->assertSame(0, LabourType::offered()->count());
        $tech = LabourType::orderBy('position')->get()[2];

        $this->actingAs($admin)->get(route('settings.labour.index'))->assertOk()->assertSee('Technician / operator')->assertSee('No rate yet');
        $rows = LabourType::all()->mapWithKeys(fn ($t) => [$t->id => ['names' => $t->names, 'unit' => 'hour', 'price' => $t->is($tech) ? '55' : '', 'active' => '1']])->all();
        $this->actingAs($admin)->put(route('settings.labour.save'), ['t' => $rows, 'new' => ['names' => ['en' => 'Rigger', 'nl' => 'Rigger'], 'unit' => 'day', 'price' => '480']])->assertRedirect();
        $this->assertSame(5500, $tech->fresh()->price_cents);
        $this->assertSame(['Technician / operator', 'Rigger'], LabourType::offered()->get()->map->name('en')->all());
        $this->assertTrue(AuditLog::where('action', 'labour.rates_changed')->exists());

        $this->actingAs($this->as('sales'))->get(route('settings.labour.index'))->assertForbidden();
    }

    public function test_labour_goes_on_the_quote_in_its_language(): void
    {
        $this->seed(RoleSeeder::class);
        $sales = $this->as('sales');
        $tech = LabourType::orderBy('position')->get()[2];
        $tech->update(['price_cents' => 5500]);
        $quote = $this->quote($sales, 'nl');

        $this->actingAs($sales)->get(route('quotes.show', $quote))->assertOk()->assertSee('Add labour')->assertSee('Technician / operator · € 55,00/h', false);
        $this->actingAs($sales)->post(route('quotes.labour.add', $quote), ['labour_type_id' => $tech->id, 'people' => 2, 'amount' => 8, 'when' => 'opbouw 13/11'])->assertSessionHasNoErrors();
        $line = $quote->lines()->where('labour_type_id', $tech->id)->firstOrFail();
        $this->assertSame('Technieker / operator · 2 personen × 8 u · opbouw 13/11', $line->name);
        $this->assertSame([16.0, 5500, 'charge', 88000], [(float) $line->qty, $line->unit_price_cents, $line->kind, $line->fresh()->amount_cents]);

        // A type without a rate can't be used.
        $inst = LabourType::orderBy('position')->first();
        $this->actingAs($sales)->post(route('quotes.labour.add', $quote), ['labour_type_id' => $inst->id, 'people' => 1, 'amount' => 2])->assertSessionHasErrors('labour_type_id');
    }

    public function test_every_language_has_the_texts(): void
    {
        foreach (['nl', 'fr', 'es'] as $l) {
            foreach (['settings.labour', 'quotes.labour'] as $k) {
                $this->assertSame(array_keys(trans($k, [], 'en')), array_keys(trans($k, [], $l)), $k.' '.$l);
            }
            $this->assertNotSame('nav.items.labour', trans('nav.items.labour', [], $l));
        }
    }
}
