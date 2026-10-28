<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Website\DigitalToday;
use App\Services\Website\WebActivity;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The team's own website visits (browsers signed in to the platform) are left out of the website statistics. */
class TeamVisitsTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM = '11111111-1111-4111-8111-111111111111';

    private const GUEST = '22222222-2222-4222-8222-222222222222';

    public function test_a_signed_in_browser_gets_the_team_mark(): void
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['role_id' => Role::where('slug', 'read-only')->value('id'), 'locale' => 'en']);
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertCookie('cluk_staff', '1', false);
        auth('web')->logout();
        $this->get('/site')->assertCookieMissing('cluk_staff');
    }

    public function test_team_visits_are_not_counted_and_earlier_ones_drop_out(): void
    {
        $team = ['cluk_consent' => 'all', 'cluk_vid' => self::TEAM];
        $guest = ['cluk_consent' => 'all', 'cluk_vid' => self::GUEST];
        // Before the mark: counted like anybody.
        $this->withCredentials()->withUnencryptedCookies($team)->postJson('/site/t', ['type' => 'view', 'path' => '/site'])->assertNoContent();
        $this->withCredentials()->withUnencryptedCookies($guest)->postJson('/site/t', ['type' => 'view', 'path' => '/site'])->assertNoContent();
        $this->withCredentials()->withUnencryptedCookies($guest)->postJson('/site/t', ['type' => 'search', 'label' => 'k2'])->assertNoContent();
        $this->assertSame(3, DigitalToday::get()['web']['events']);

        // The same browser after signing in to the platform: nothing new recorded, earlier visits left out.
        $this->withCredentials()->withUnencryptedCookies($team + ['cluk_staff' => '1'])->postJson('/site/t', ['type' => 'view', 'path' => '/site/sales'])->assertNoContent();
        $this->assertSame(3, DB::table('web_events')->count());
        $this->assertTrue((bool) DB::table('web_visitors')->where('uuid', self::TEAM)->value('internal'));

        $d = DigitalToday::get();
        $this->assertSame([2, 1, 1], [$d['web']['events'], $d['web']['visitors'], $d['web']['actions']]);
        $o = WebActivity::overview(7);
        $this->assertSame([1, 1], [$o['visitors'], $o['views']]);
    }
}
