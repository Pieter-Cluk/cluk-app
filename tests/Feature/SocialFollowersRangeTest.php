<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Social\Account;
use App\Models\Social\Daily;
use App\Models\User;
use App\Services\Social\Dashboard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The followers chart has its own period: 1 month, 3 months, 6 months, 1 year or everything. */
class SocialFollowersRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_followers_over_the_chosen_period(): void
    {
        $this->seed(RoleSeeder::class);
        $fb = Account::create(['platform' => 'facebook', 'external_id' => '1', 'name' => 'CLUK', 'token' => 'x', 'status' => 'connected', 'meta' => []]);
        Daily::create(['account_id' => $fb->id, 'date' => today()->subDays(500)->toDateString(), 'followers' => 100]);
        Daily::create(['account_id' => $fb->id, 'date' => today()->subDays(200)->toDateString(), 'followers' => 150]);
        Daily::create(['account_id' => $fb->id, 'date' => today()->subDays(10)->toDateString(), 'followers' => 180]);
        Daily::create(['account_id' => $fb->id, 'date' => today()->toDateString(), 'followers' => 190]);

        $m = Dashboard::followers('1m')['facebook'];
        $this->assertCount(30, $m);                     // per day
        $this->assertSame(150, reset($m));               // the value before the period carries on
        $this->assertSame(190, end($m));
        $y = Dashboard::followers('1y')['facebook'];
        $this->assertLessThan(60, count($y));            // per week
        $this->assertSame(today()->toDateString(), array_key_last($y));
        $all = Dashboard::followers('all')['facebook'];
        $this->assertSame(100, reset($all));
        $this->assertSame(190, end($all));

        $u = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id'), 'locale' => 'en']);
        $this->actingAs($u)->get(route('social.index'))->assertOk()->assertSee('1 year')->assertSee('chip chip-on" href="'.e(route('social.index', ['d' => 30, 'f' => '1y'])), false);
        $this->actingAs($u)->get(route('social.index', ['f' => 'all']))->assertOk()->assertSee('chip chip-on" href="'.e(route('social.index', ['d' => 30, 'f' => 'all'])), false);
    }
}
