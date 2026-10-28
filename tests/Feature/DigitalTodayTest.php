<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Social\Account;
use App\Models\Social\Daily;
use App\Models\User;
use App\Services\Website\DigitalToday;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigitalTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->travelTo(today()->setTime(15, 20));
        $v = DB::table('web_visitors')->insertGetId(['uuid' => (string) Str::uuid(), 'device' => 'desktop', 'first_seen_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['view', 9], ['view', 9], ['search', 10], ['request', 14], ['view', 14], ['view', 1, -1]] as $e) {
            DB::table('web_events')->insert(['visitor_id' => $v, 'type' => $e[0], 'section' => 'dryhire', 'created_at' => today()->addDays($e[2] ?? 0)->setTime($e[1], 5)]);
        }
        $fb = Account::create(['platform' => 'facebook', 'external_id' => '1', 'name' => 'CLUK', 'token' => 'x', 'status' => 'connected', 'meta' => []]);
        $off = Account::create(['platform' => 'instagram', 'external_id' => '2', 'name' => 'Old', 'token' => 'x', 'status' => 'off', 'meta' => []]);
        Daily::create(['account_id' => $fb->id, 'date' => today()->toDateString(), 'reach' => 300, 'engagements' => 7, 'clicks' => 3]);
        Daily::create(['account_id' => $fb->id, 'date' => today()->subDay()->toDateString(), 'reach' => 900, 'engagements' => 50]);
        Daily::create(['account_id' => $off->id, 'date' => today()->toDateString(), 'reach' => 999, 'engagements' => 99]);
    }

    public function test_totals_count_only_today_and_connected_accounts(): void
    {
        $d = DigitalToday::get();

        $this->assertSame(5, $d['web']['events']);
        $this->assertSame(1, $d['web']['visitors']);
        $this->assertSame(3, $d['web']['views']);
        $this->assertSame(2, $d['web']['actions']);
        $this->assertSame(2, $d['hours'][9]);
        $this->assertSame(2, $d['hours'][14]);
        $this->assertSame(0, $d['hours'][1]);
        $this->assertSame(10, $d['social']['interactions']);
        $this->assertSame(300, $d['social']['reach']);
        $this->assertSame(15, $d['total']);
        $this->assertSame(['facebook', 'website'], $d['sources']->pluck('key')->all());
    }

    public function test_dashboard_shows_the_card_only_to_sales_users(): void
    {
        $sales = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id'), 'locale' => 'en']);
        $this->actingAs($sales)->get('/dashboard')->assertOk()->assertSee('Digital activity today')->assertSee('Website activity per hour')
            ->assertSee('09:00–10:00 · 2 events')->assertSee('class="dg-bar"', false);

        $warehouse = User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
        if (! $warehouse->canUseModule('sales')) {
            $this->actingAs($warehouse)->get('/dashboard')->assertOk()->assertDontSee('Digital activity today');
        }
    }

    public function test_every_language_has_the_texts(): void
    {
        foreach (['nl', 'fr', 'es'] as $l) {
            $this->assertNotSame('dashboard.digital.title', trans('dashboard.digital.title', [], $l));
            $this->assertSame(array_keys(trans('dashboard.digital', [], 'en')), array_keys(trans('dashboard.digital', [], $l)));
        }
    }
}
