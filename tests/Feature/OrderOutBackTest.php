<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An approved dry-hire order shows Out and Back; the dashboard and the Out tab show what is due back. */
class OrderOutBackTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_orders_are_listed_and_due_back_shows_on_the_dashboard(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $co = Company::create(['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'country' => 'BE']);
        $o = Order::create(['number' => 'R-OC-2026-0201', 'status' => 'approved', 'company_id' => $co->id, 'title' => 'Festival', 'language' => 'en',
            'out_from' => now()->subDays(5), 'out_until' => now()->subDays(2), 'use_from' => today()->subDays(4), 'use_until' => today()->subDays(3), 'vat_rate_bp' => 2100]);

        $this->actingAs($admin, 'web')->get(route('orders.show', $o))->assertOk()->assertSee('Pick in the warehouse app')->assertSee('Back');
        $o->update(['loaded_at' => now()->subDays(5)]);
        $this->actingAs($admin, 'web')->get(route('orders.show', $o))->assertOk()->assertSee('Start the return');
        $this->actingAs($admin, 'web')->get(route('orders.index', ['tab' => 'out']))->assertOk()->assertSee('R-OC-2026-0201')->assertSee('2 days late');
        $this->actingAs($admin, 'web')->get(route('orders.index', ['tab' => 'done']))->assertOk()->assertDontSee('R-OC-2026-0201');
        $this->actingAs($admin, 'web')->get('/dashboard')->assertOk()->assertSee('Dry-hire due back');

        $o->update(['returned_at' => now()]);
        $this->actingAs($admin, 'web')->get(route('orders.index', ['tab' => 'out']))->assertOk()->assertDontSee('R-OC-2026-0201');
        $this->actingAs($admin, 'web')->get(route('orders.index', ['tab' => 'done']))->assertOk()->assertSee('R-OC-2026-0201');
        $this->actingAs($admin, 'web')->get('/dashboard')->assertOk()->assertDontSee('Dry-hire due back');
    }
}
