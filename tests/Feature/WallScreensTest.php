<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Equipment\Item;
use App\Models\Factory\Booking;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\WallScreen;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallScreensTest extends TestCase
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

    /** An order going out today and back tomorrow, with 4 Spiiders from our stock. */
    private function data(): Order
    {
        $item = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'category' => 'Light', 'serial' => false, 'stock_qty' => 8, 'day_price_cents' => 6500, 'price_unit' => 'day']);
        $company = Company::create(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $order = Order::create(['number' => 'R-OC-2026-0001', 'status' => 'approved', 'company_id' => $company->id, 'title' => 'Company evening',
            'out_from' => today()->setTime(16, 30), 'out_until' => today()->addDay()->setTime(10, 0), 'use_from' => today(), 'use_until' => today(), 'net_cents' => 123456]);
        $order->lines()->create(['kind' => 'item', 'item_id' => $item->id, 'name' => 'Robe Spiider', 'qty' => 4, 'own_qty' => 4, 'amount_cents' => 123456]);
        Booking::create(['number' => 'F-2026-0001', 'status' => 'confirmed', 'title' => 'Product launch', 'event_type' => 'launch', 'start_date' => today()->addDays(5), 'days' => 1, 'size' => 'L', 'level' => 'basic', 'guests' => 200]);

        return $order;
    }

    public function test_a_screen_shows_with_its_secret_link_only(): void
    {
        $screen = WallScreen::where('view', 'today')->firstOrFail();
        $this->get('/wall/today')->assertNotFound();
        $this->get('/wall/today?key=wrong')->assertNotFound();
        $this->get('/wall/today?key='.$screen->token)->assertOk();
        $this->assertTrue($screen->fresh()->isOnline());
        // Signed-in staff can look without the key.
        $this->actingAs($this->as('warehouse'))->get('/wall/planning')->assertOk();
    }

    public function test_today_shows_what_goes_out_and_comes_in_without_money(): void
    {
        $this->data();
        $key = WallScreen::where('view', 'today')->value('token');
        $html = $this->get('/wall/today?lang=en&key='.$key)->assertOk()->assertSee('Company evening')->assertSee('16:30')->assertSee('Demo Agency BV')->assertSee('4 pcs')->getContent();
        $this->assertStringNotContainsString('€', $html);
        $this->assertStringNotContainsString('1.234', $html);
        $this->get('/wall/today?lang=en&day=tomorrow&key='.$key)->assertSee('Return · Company evening')->assertSee('10:00');
        $this->get('/wall/today?lang=en&day=week&key='.$key)->assertOk()->assertSee('Company evening');
        // Fleet out now: the order runs from 16:30, so nothing yet unless it started.
        $this->get('/wall/today?lang=en&day=month&key='.$key)->assertOk();
    }

    public function test_planning_shows_heat_and_job_bars(): void
    {
        $this->data();
        $key = WallScreen::where('view', 'planning')->value('token');
        $this->get('/wall/planning?lang=en&key='.$key)->assertOk()->assertSee('Company evening')->assertSee('Product launch')->assertSee('Light')->assertSee('50%');
        $this->get('/wall/planning?lang=en&off=2&key='.$key)->assertOk();
    }

    public function test_new_link_stops_the_old_one_and_needs_warehouse_level_2(): void
    {
        $screen = WallScreen::where('view', 'today')->firstOrFail();
        $old = $screen->token;
        $this->actingAs($this->as('read-only'))->post('/wall-screens/'.$screen->id.'/renew')->assertForbidden();
        $crew = $this->as('warehouse');
        $this->actingAs($crew)->post('/wall-screens/'.$screen->id.'/renew')->assertForbidden(); // no "Wall screens" right
        $crew->extraRights()->create(['right' => 'wall.screens']);
        $this->actingAs($crew->fresh())->post('/wall-screens/'.$screen->id.'/renew')->assertRedirect();
        auth('web')->logout();
        $this->get('/wall/today?key='.$old)->assertNotFound();
        $this->get('/wall/today?key='.$screen->fresh()->token)->assertOk();
    }

    public function test_pages_in_every_language_without_missing_texts(): void
    {
        $this->data();
        $admin = $this->as('admin');
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            $html = $this->actingAs($admin->fresh())->get('/wall-screens')->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\bwall\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags($html), $locale);
            foreach (['today', 'today&day=week', 'today&day=month', 'planning'] as $v) {
                $html = $this->get('/wall/'.str_replace('&', '?', $v).(str_contains($v, '&') ? '&' : '?').'lang='.$locale)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(wall|workspace)\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags($html), "{$v} ({$locale})");
            }
        }
    }
}
