<?php

namespace Tests\Feature;

use App\Models\Afrit6\Guest;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\Space;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Models\WallScreen;
use App\Models\Website\Slide;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Wall screen 3: the Afrit 6 entrance TV (slideshow + welcome list + meeting room), and guests in the member portal. */
class EntranceScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function screen(): WallScreen
    {
        return WallScreen::where('view', 'entrance')->firstOrFail();
    }

    public function test_the_screen_welcomes_todays_guests_and_shows_the_room(): void
    {
        $m = Member::factory()->active()->create(['locale' => 'en', 'reg_company_name' => 'Studio Noord']);
        Guest::create(['member_id' => $m->id, 'date' => today(), 'time' => '10:30', 'name' => 'Ann Peeters', 'company' => 'Acme NV', 'on_screen' => true, 'note' => 'secret-note']);
        Guest::create(['member_id' => $m->id, 'date' => today(), 'name' => 'Hidden Person', 'on_screen' => false]);
        Guest::create(['member_id' => $m->id, 'date' => today(), 'name' => 'Cancelled Guy', 'cancelled_at' => now()]);
        Guest::create(['member_id' => $m->id, 'date' => today()->addDay(), 'name' => 'Tomorrow Tom']);
        RoomBooking::create(['member_id' => $m->id, 'space_id' => Space::where('code', 'ROOM')->value('id'), 'date' => today(), 'start_slot' => 4, 'slots' => 4, 'setup' => 'meeting', 'note' => 'room-note']);

        $this->get('/wall/entrance')->assertNotFound();
        $this->get('/wall/entrance?key=wrong')->assertNotFound();

        $html = $this->get('/wall/entrance?key='.$this->screen()->token.'&lang=en')->assertOk()
            ->assertSee('Welcome')->assertSee('Ann Peeters')->assertSee('Acme NV')->assertSee('10:30')->assertSee('visiting Studio Noord')
            ->assertSee('Welcome, dear guest')->assertSee('10:00–12:00')->assertSee('Full screen')
            ->assertDontSee('Hidden Person')->assertDontSee('Cancelled Guy')->assertDontSee('Tomorrow Tom')->assertDontSee('secret-note')->assertDontSee('room-note')
            ->getContent();
        $this->assertStringNotContainsString('http-equiv="refresh"', $html);
        $this->assertNotNull($this->screen()->last_seen_at);

        $panel = $this->get('/wall/entrance?key='.$this->screen()->token.'&part=panel&lang=en')->assertOk()->assertSee('Ann Peeters');
        $this->assertNotEmpty($panel->headers->get('X-Slides'));
        $this->assertStringNotContainsString('<html', $panel->getContent());
    }

    public function test_slides_of_the_screen_are_shown_and_a_change_changes_the_version(): void
    {
        $key = $this->screen()->token;
        $v1 = $this->get('/wall/entrance?key='.$key.'&part=panel')->headers->get('X-Slides');
        Slide::create(['place' => 'afrit6_screen', 'position' => 1, 'kind' => 'image', 'path' => 'website/abc123.jpg', 'link_type' => 'none', 'active' => true]);
        Slide::create(['place' => 'home_hero', 'position' => 1, 'kind' => 'image', 'path' => 'website/home999.jpg', 'link_type' => 'none', 'active' => true]);
        $this->get('/wall/entrance?key='.$key)->assertOk()->assertSee('abc123.jpg')->assertDontSee('home999.jpg');
        $this->assertNotSame($v1, $this->get('/wall/entrance?key='.$key.'&part=panel')->headers->get('X-Slides'));
    }

    public function test_a_member_adds_and_removes_guests(): void
    {
        $m = Member::factory()->active()->create(['locale' => 'en']);
        $this->actingAs($m, 'member')->get('/afrit6/portal/guests')->assertOk()->assertSee('Expecting visitors?');
        $this->actingAs($m, 'member')->post('/afrit6/portal/guests', ['date' => today()->toDateString(), 'time' => '14:00', 'names' => "Jan Janssens\nPiet Pieters\n", 'company' => 'Demo BV', 'on_screen' => '0'])
            ->assertRedirect('/afrit6/portal/guests');
        $this->assertSame(2, Guest::where('member_id', $m->id)->count());
        $this->assertFalse(Guest::first()->on_screen);
        $this->actingAs($m, 'member')->post('/afrit6/portal/guests', ['date' => today()->subDay()->toDateString(), 'names' => 'Late'])->assertSessionHasErrors('date');

        $other = Member::factory()->active()->create(['locale' => 'en']);
        $g = Guest::first();
        $this->actingAs($other, 'member')->post('/afrit6/portal/guests/'.$g->id.'/cancel')->assertNotFound();
        $this->actingAs($m, 'member')->post('/afrit6/portal/guests/'.$g->id.'/cancel')->assertRedirect();
        $this->assertNotNull($g->fresh()->cancelled_at);
    }

    public function test_staff_see_the_third_screen_and_the_slides_tab(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $this->actingAs($admin)->get('/wall-screens')->assertOk()->assertSee('Screen 3 · Afrit 6 entrance')->assertSee('Full screen, without the browser');
        $this->actingAs($admin)->get(route('website.index', ['tab' => 'screen']))->assertOk()->assertSee('Slideshow on the Afrit 6 entrance TV');
    }

    public function test_every_language_has_the_texts(): void
    {
        foreach (['nl', 'fr', 'es'] as $l) {
            foreach (['guests' => 'guests', 'wall.entrance' => 'wall.entrance'] as $k) {
                $this->assertSame(array_keys(trans($k, [], 'en')), array_keys(trans($k, [], $l)), $k.' '.$l);
            }
            $this->assertNotSame('wall.admin.screen_entrance', trans('wall.admin.screen_entrance', [], $l));
            $this->assertNotSame('workspace.nav.guests', trans('workspace.nav.guests', [], $l));
            $this->assertNotSame('website.places.afrit6_screen', trans('website.places.afrit6_screen', [], $l));
        }
    }
}
