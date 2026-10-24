<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Credit\CreditCheck;
use App\Services\Credit\NbbAccounts;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Customer card › Credit check › Annual accounts from the National Bank (NBB), with the chart. */
class NbbAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        config(['services.nbb.key' => 'test-key', 'services.nbb.url' => 'https://nbb.test/authentic']);
    }

    private function company(): Company
    {
        return Company::create(['name' => 'Demo Rental BV', 'vat_number' => 'BE0123456789', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'BE', 'account_owner_id' => $this->owner->id]);
    }

    private function rubrics(array $n, array $nm1 = []): array
    {
        $r = [['Code' => '70', 'Period' => 'N', 'Value' => '999', 'Dimensions' => [['Code' => 'x']]]]; // split figure: ignored
        foreach ($n as $code => $v) {
            $r[] = ['Code' => $code, 'Period' => 'N', 'Value' => (string) $v];
        }
        foreach ($nm1 as $code => $v) {
            $r[] = ['Code' => $code, 'Period' => 'NM1', 'Value' => (string) $v];
        }

        return ['ReferenceNumber' => 'x', 'Rubrics' => $r];
    }

    private function fakeNbb(array $equity2025 = ['10/15' => 300000]): void
    {
        $refs = [
            ['ReferenceNumber' => '2026-00001', 'DepositDate' => '2026-07-20', 'ExerciseDates' => ['startDate' => '2025-01-01', 'endDate' => '2025-12-31'], 'ModelType' => 'm02-f', 'Currency' => 'EUR'],
            ['ReferenceNumber' => '2025-00001', 'DepositDate' => '2025-07-10', 'ExerciseDates' => ['startDate' => '2024-01-01', 'endDate' => '2024-12-31'], 'ModelType' => 'm02-f', 'Currency' => 'EUR'],
            ['ReferenceNumber' => '2025-00099', 'DepositDate' => '2025-09-01', 'ExerciseDates' => ['startDate' => '2024-01-01', 'endDate' => '2024-12-31'], 'ModelType' => 'm02-f', 'Currency' => 'EUR'], // correction: wins
            ['ReferenceNumber' => '2021-00001', 'DepositDate' => '2021-07-10', 'ExerciseDates' => ['startDate' => '2020-01-01', 'endDate' => '2020-12-31'], 'ModelType' => 'm02-f', 'Currency' => 'EUR'], // PDF only
        ];
        Http::fake(function (Request $req) use ($refs, $equity2025) {
            $this->assertSame('test-key', $req->header('NBB-CBSO-Subscription-Key')[0] ?? null);
            $url = $req->url();

            return match (true) {
                str_ends_with($url, '/legalEntity/0123456789/references') => Http::response($refs),
                str_contains($url, '/deposit/2026-00001/accountingData') => Http::response($this->rubrics(['70' => 1250000.40, '9904' => 85000, '9087' => 7.5] + $equity2025, ['70' => 1000000, '9904' => -12000])),
                str_contains($url, '/deposit/2025-00099/accountingData') => Http::response($this->rubrics(['70' => 1000000, '9904' => -12000, '10/15' => 215000], ['9900' => 400000, '70' => 800000, '9904' => 30000, '10/15' => 227000])),
                default => Http::response('', 404),
            };
        });
    }

    public function test_it_reads_the_years_with_corrections_and_last_years_column(): void
    {
        $c = $this->company();
        $this->fakeNbb();

        $this->assertSame(3, NbbAccounts::refresh($c));
        $rows = CompanyAccount::where('company_id', $c->id)->orderBy('year')->get()->keyBy('year');
        $this->assertSame([2023, 2024, 2025], $rows->keys()->all());
        $this->assertSame(125000040, $rows[2025]->turnover);
        $this->assertSame(7.5, $rows[2025]->employees);
        $this->assertSame('2025-00099', $rows[2024]->reference); // the correction
        $this->assertSame(-1200000, $rows[2024]->profit);
        $this->assertSame(['NM1', 80000000, 40000000], [$rows[2023]->period, $rows[2023]->turnover, $rows[2023]->gross_margin]);
        $this->assertNotNull($c->fresh()->accounts_checked_at);

        // A second run does not fetch the known filings again (only the PDF-only one is asked again).
        NbbAccounts::refresh($c->fresh());
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), '/deposit/2026-00001/')));
    }

    public function test_the_customer_card_shows_the_chart_and_the_growth(): void
    {
        $c = $this->company();
        $this->fakeNbb();
        NbbAccounts::refresh($c);

        $r = $this->actingAs($this->owner)->get(route('companies.show', $c))->assertOk();
        $r->assertSee('Annual accounts (National Bank)')->assertSee('class="fa-chart"', false)
            ->assertSee('€ 1,3M')->assertSee('+25%')->assertSee('−€ 12k')->assertSee('20/07/2026')
            ->assertDontSee('as soon as the National Bank connection is active');
        $this->assertNotContains('orange', collect(CreditCheck::for($c->fresh())['reasons'])->pluck('level')->all());
        if ($out = env('DUMP_CARD')) {
            file_put_contents($out, $r->getContent());
        }
    }

    public function test_negative_equity_turns_the_check_orange(): void
    {
        $c = $this->company();
        $this->fakeNbb(['10/15' => -50000]);
        NbbAccounts::refresh($c);

        $check = CreditCheck::for($c->fresh());
        $this->assertSame('orange', $check['level']);
        $this->assertStringContainsString('Negative equity in the 2025 annual accounts', collect($check['reasons'])->pluck('text')->implode(' '));
    }

    public function test_check_now_and_no_key(): void
    {
        $c = $this->company();
        $this->fakeNbb();
        $this->actingAs($this->owner)->post(route('companies.credit.refresh', $c))->assertRedirect();
        $this->assertStringContainsString('3 years of annual accounts', session('status'));

        config(['services.nbb.key' => null]);
        $this->assertNull(NbbAccounts::refresh($c));
        $this->actingAs($this->owner)->get(route('companies.show', $c))->assertOk()
            ->assertSee('as soon as the National Bank connection is active')->assertDontSee('class="fa-chart"', false);
    }

    public function test_check_now_when_the_bank_is_down(): void
    {
        $c = $this->company();
        Http::fake(['*' => Http::response('down', 503)]);
        $this->actingAs($this->owner)->post(route('companies.credit.refresh', $c))->assertRedirect();
        $this->assertStringContainsString(__('credit.flash.nbb_not_reached'), session('status'));
        $this->assertNull($c->fresh()->accounts_checked_at);
    }

    public function test_background_run_looks_up_belgian_companies_once_a_month(): void
    {
        $c = $this->company();
        $nl = Company::create(['name' => 'Dutch BV', 'vat_number' => 'NL123456789B01', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'NL']);
        $this->fakeNbb();

        $this->assertSame(1, NbbAccounts::refreshStale(5));
        $this->assertNotNull($c->fresh()->accounts_checked_at);
        $this->assertNull($nl->fresh()->accounts_checked_at);
        $this->assertSame(0, NbbAccounts::refreshStale(5)); // fresh: nothing to do
    }
}
