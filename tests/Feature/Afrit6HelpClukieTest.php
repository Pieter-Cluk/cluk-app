<?php

namespace Tests\Feature;

use App\Models\Afrit6\MemberChat;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\Space;
use App\Models\Member;
use App\Services\Afrit6\MemberClukie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Afrit 6 members: Help with instruction videos, and Clukie (Afrit 6 only, prepares forms, never books). */
class Afrit6HelpClukieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00:00')); // a Wednesday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $locale = 'nl'): Member
    {
        return Member::factory()->active()->create(['locale' => $locale]);
    }

    public function test_help_page_with_videos_in_the_members_language(): void
    {
        $nl = $this->member('nl');
        $this->actingAs($nl, 'member')->get('/afrit6/portal')->assertSee('/afrit6/portal/help', false);
        $this->actingAs($nl, 'member')->get('/afrit6/portal/help')->assertOk()->assertSee('De vergaderzaal reserveren')->assertSee('media/afrit6-help/nl/room.jpg', false);
        $this->actingAs($nl, 'member')->get('/afrit6/portal/help/room')->assertOk()->assertSee('media/afrit6-help/nl/room.mp4', false)->assertSee('Klik op Reserveren.');
        $this->actingAs($nl, 'member')->get('/afrit6/portal/help/nope')->assertNotFound();

        // French members: steps in French, the English video.
        $fr = $this->member('fr');
        $this->actingAs($fr, 'member')->get('/afrit6/portal/help/room')->assertOk()->assertSee('Cliquez sur Réserver.')->assertSee('media/afrit6-help/en/room.mp4', false)->assertSee('Les vidéos sont en anglais');

        // Every topic has a step text for every step in all four languages.
        foreach (['en', 'nl', 'fr', 'es'] as $l) {
            foreach (config('afrit6-help.topics') as $key => $t) {
                $this->assertCount(count($t['steps']), (array) __("a6help.topics.{$key}.steps", [], $l), "{$l} {$key}");
            }
        }
        auth('member')->logout();
        $this->get('/afrit6/portal/help')->assertRedirect();
    }

    public function test_a_clukie_link_opens_the_form_filled_in(): void
    {
        $m = $this->member('nl');
        $this->actingAs($m, 'member')->get('/afrit6/portal/guests?fill[date]=2026-11-06&fill[time]=10:30&fill[names]=Emma%20Janssens&fill[company]=Atelier%20Noord&fill[evil]=x')
            ->assertOk()->assertSee('Clukie heeft dit voor je ingevuld')->assertSee('value="2026-11-06"', false)->assertSee('Emma Janssens')->assertSee('value="Atelier Noord"', false);
        $this->actingAs($m, 'member')->get('/afrit6/portal/services?new=receive&fill[kind]=receive&fill[colis]=3&fill[from_text]=DHL')
            ->assertOk()->assertSee('value="3"', false)->assertSee('value="DHL"', false);
        // Nothing is saved by opening the link.
        $this->assertSame(0, \App\Models\Afrit6\Guest::count());
        $this->actingAs($m, 'member')->get('/afrit6/portal/guests')->assertDontSee('Clukie heeft dit voor je ingevuld');
    }

    public function test_clukie_answers_with_a_prepared_room_booking_and_books_nothing(): void
    {
        config(['services.anthropic.key' => 'test-key', 'clukie.enabled' => true]);
        $m = $this->member('nl');
        $room = Space::room();
        RoomBooking::create(['member_id' => Member::factory()->active()->create()->id, 'space_id' => $room->id, 'date' => '2026-11-05', 'start_slot' => 2, 'slots' => 4, 'setup' => 'meeting']);
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'room_availability', 'input' => ['date' => '2026-11-05']]]])
            ->push(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 't2', 'name' => 'prepare', 'input' => ['what' => 'room', 'date' => '2026-11-05', 'start' => '13:00', 'minutes' => 90, 'setup' => 'meeting', 'note' => 'Klant']]]])
            ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Morgen om 13:00 is vrij. [Open het ingevulde formulier](https://x)']]]),
        ]);

        $this->actingAs($m, 'member')->get('/afrit6/portal')->assertSee('clukie-cfg', false)->assertSee('Vraag het Clukie');
        $r = $this->actingAs($m, 'member')->postJson('/afrit6/portal/clukie', ['text' => 'Kan ik morgen om 13u de zaal hebben voor anderhalf uur?', 'page' => '/afrit6/portal'])->assertOk();
        $this->assertStringContainsString('13:00 is vrij', collect($r->json('messages'))->firstWhere('role', 'clukie')['body']);
        $this->assertSame(1, RoomBooking::count()); // Clukie never books

        // What the tools gave back: the free slots (09:00–11:00 taken) and a link with the form filled in.
        $sent = Http::recorded()->map(fn ($x) => $x[0])->values();
        $tool = fn (int $i) => json_decode(collect($sent[$i]->data()['messages'])->last()['content'][0]['content'], true);
        $this->assertNotContains('09:30', $tool(1)['free_half_hours_starting_at']);
        $this->assertContains('13:00', $tool(1)['free_half_hours_starting_at']);
        $link = $tool(2)['link'];
        $this->assertStringContainsString('/afrit6/portal/room?day=2026-11-05', urldecode($link));
        $this->assertStringContainsString('fill[start]=10', urldecode($link));
        $this->assertStringContainsString('fill[slots]=3', urldecode($link));
        // The system prompt keeps Clukie to Afrit 6, in Flemish.
        $this->assertStringContainsString('Only Afrit 6 matters here', $sent[0]->data()['system']);
        $this->assertStringContainsString('Dutch as spoken in Flanders', $sent[0]->data()['system']);

        // The link opens the room page with start and duration chosen.
        $this->actingAs($m, 'member')->get(str_replace(url('/'), '', $link))->assertOk()->assertSee('<option value="10" selected>', false);

        // Another member cannot read this chat.
        $token = $r->json('token');
        $this->actingAs($this->member(), 'member')->getJson('/afrit6/portal/clukie/poll?token='.$token)->assertNotFound();
        $this->assertSame(1, MemberChat::count());
    }

    public function test_clukie_tools_only_see_the_members_own_data(): void
    {
        $m = $this->member('en');
        $other = $this->member('en');
        RoomBooking::create(['member_id' => $other->id, 'space_id' => Space::room()->id, 'date' => '2026-11-05', 'start_slot' => 2, 'slots' => 2, 'setup' => 'meeting', 'note' => 'Secret']);
        $chat = MemberChat::create(['token' => 'x', 'member_id' => $m->id, 'locale' => 'en']);
        $c = new MemberClukie($chat, $m);
        $this->assertSame([], $c->tool('my_overview', [])['room_bookings']);
        $this->assertStringNotContainsString('Secret', json_encode($c->tool('room_availability', ['date' => '2026-11-05'])));
        $this->assertSame('weekend', $c->tool('desk_availability', ['date' => '2026-11-07', 'part' => 'day'])['closed']);
        $this->assertNotEmpty($c->tool('desk_availability', ['date' => '2026-11-05', 'part' => 'day'])['free_desks']);
        $this->assertCount(count(config('afrit6-help.topics')), $c->tool('help_videos', [])['videos']);
        $this->assertSame('which day?', $c->tool('prepare', ['what' => 'room'])['error']);
    }
}
