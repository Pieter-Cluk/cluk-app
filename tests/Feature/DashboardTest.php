<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function data(): void
    {
        $c = Company::create(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        Order::create(['number' => 'R-OC-2026-0001', 'status' => 'approved', 'company_id' => $c->id, 'title' => 'Company evening', 'out_from' => today()->setTime(16, 30),
            'out_until' => today()->addDays(2), 'use_from' => today(), 'use_until' => today()->addDay(), 'net_cents' => 425000]);
        Deal::create(['number' => 'D-2026-0001', 'title' => 'Gala', 'module' => 'dryhire', 'stage' => 'proposal', 'value_cents' => 120000, 'company_id' => $c->id, 'check_date' => false, 'check_budget' => false, 'check_decider' => false]);
    }

    public function test_money_only_for_who_may_see_it(): void
    {
        $this->data();
        $this->actingAs($this->as('manager'))->get('/dashboard')->assertOk()->assertSee('€ 4.250,00')->assertSee('Company evening')->assertSee('16:30')->assertSee('€ 1.200,00');
        $this->actingAs($this->as('warehouse'))->get('/dashboard')->assertOk()->assertDontSee('€ 4.250,00')->assertSee('Company evening')->assertDontSee('Sales pipeline');
    }

    public function test_filters_and_every_language(): void
    {
        $this->data();
        $admin = $this->as('admin');
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/dashboard', '/dashboard?tab=out', '/dashboard?type=factory&who=all', '/dashboard?tab=booking'] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(dashboard|nav|wall)\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html)), "{$url} ({$locale})");
            }
        }
        $this->actingAs($admin)->get('/dashboard?type=factory')->assertDontSee('Company evening</a>', false);
    }

    public function test_mine_and_everyone_switch_rows_open_their_job_and_stock_card(): void
    {
        $this->data();
        $m = $this->as('manager');
        $html = $this->actingAs($m)->get('/dashboard')->assertOk()->assertDontSee('Your modules')->assertSee('Stock needs attention')->getContent();
        $this->assertStringContainsString('who=all', $html, 'the Everyone chip keeps its filter');
        $this->assertStringContainsString('class="db-row-link" href="'.route('orders.show', Order::first()), $html);
        $this->actingAs($m)->get('/dashboard?who=all')->assertOk()->assertSee('chip chip-on" href="'.route('dashboard', ['who' => 'all']), false);
    }
}
