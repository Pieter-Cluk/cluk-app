<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Finance\PreInvoice;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(10, 0));
    }

    private function as(string $role, array $extra = []): User
    {
        return User::factory()->create($extra + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function invoice(Company $co, string $module, int $net, int $cost, string $date, ?User $owner, string $status = 'sent'): PreInvoice
    {
        static $n = 0;
        $n++;

        return PreInvoice::create(['number' => sprintf('PF-2026-%04d', $n), 'source_type' => 'rental', 'source_id' => $n, 'source_code' => 'X'.$n, 'description' => 'Job '.$n, 'module' => $module,
            'company_id' => $co->id, 'owner_id' => $owner?->id, 'status' => $status, 'lines' => [], 'net_cents' => $net, 'cost_cents' => $cost, 'vat_cents' => 0, 'gross_cents' => $net,
            'payment_days' => 30, 'invoice_date' => $date, 'due_date' => Carbon::parse($date)->addDays(30)]);
    }

    public function test_revenue_margin_salespeople_and_customers(): void
    {
        $elke = $this->as('sales', ['first_name' => 'Elke', 'last_name' => 'Martens', 'is_commercial' => true, 'title' => 'Business developer']);
        $ruben = $this->as('sales', ['first_name' => 'Ruben', 'last_name' => 'De Wilde', 'is_commercial' => true]);
        $agency = Company::create(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $rental = Company::create(['name' => 'Demo Rental', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $agency->update(['account_owner_id' => $elke->id]);
        $rental->update(['account_owner_id' => $elke->id]);

        $this->invoice($agency, 'dryhire', 1000000, 400000, '2026-03-10', $elke, 'paid');   // € 10,000
        $this->invoice($agency, 'factory', 500000, 0, '2026-09-02', $elke);                   // € 5,000
        $this->invoice($rental, 'dryhire', 300000, 200000, '2026-10-05', $ruben);              // € 3,000
        $this->invoice($rental, 'afrit6', 100000, 0, '2026-10-06', null);                      // counter
        $this->invoice($agency, 'dryhire', 800000, 0, '2025-04-01', $elke, 'paid');            // last year
        $this->invoice($agency, 'dryhire', 999900, 0, '2026-10-01', $elke, 'check');           // not invoiced yet
        Deal::create(['number' => 'D-1', 'title' => 'Festival', 'module' => 'dryhire', 'stage' => 'proposal', 'owner_id' => $elke->id, 'value_cents' => 2000000]);
        Deal::create(['number' => 'D-2', 'title' => 'Gala', 'module' => 'factory', 'stage' => 'won', 'owner_id' => $elke->id, 'value_cents' => 500000, 'closed_at' => '2026-09-01']);

        $boss = $this->as('admin');
        $r = $this->actingAs($boss)->get('/management')->assertOk();
        // Companies each person owns, linked to the company list filtered on them.
        $r->assertSee('Companies')->assertSee('owner='.$elke->id.'">2</a>', false)->assertSee('owner='.$ruben->id.'">0</a>', false);
        $r->assertSee('€ 19k')                    // 10 + 5 + 3 + 1 thousand, year to date
            ->assertSee('vs € 8k last year')
            ->assertSee('▲ 137,5%')
            ->assertSee('68,4%')                  // (19,000 − 6,000) / 19,000
            ->assertSee('Elke Martens')->assertSee('Ruben De Wilde')->assertSee('Counter &amp; Online', false)
            ->assertSee('Demo Agency BV')->assertSee('€ 20k')  // open quote
            ->assertSee('€ 10k weighted');       // 50% of 20k

        // This month only: Ruben's € 3,000 and the counter's € 1,000.
        $this->actingAs($boss)->get('/management?p=month')->assertOk()->assertSee('€ 4k');

        $this->actingAs($this->as('sales'))->get('/management')->assertForbidden();
        $this->actingAs($this->as('admin', ['locale' => 'nl']))->get('/management')->assertOk()->assertSee('Omzet per verkoper');
    }

    public function test_empty_state_in_four_languages(): void
    {
        foreach (['en' => 'Revenue per month', 'nl' => 'Omzet per maand', 'fr' => 'Chiffre d’affaires par mois', 'es' => 'Facturación por mes'] as $loc => $text) {
            $this->actingAs($this->as('admin', ['locale' => $loc]))->get('/management?m=11')->assertOk()->assertSee('<html lang="'.$loc.'"', false)->assertSee($text, false);
        }
    }
}
