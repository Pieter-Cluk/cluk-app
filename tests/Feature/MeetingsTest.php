<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Work\Meeting;
use App\Models\Work\Todo;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Meeting notes: paste, the app proposes the tasks, a person checks them, they land with the right people. */
class MeetingsTest extends TestCase
{
    use RefreshDatabase;

    private User $pieter;

    private User $elke;

    private User $tom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        config(['services.anthropic.key' => null]);
        $this->pieter = $this->as('manager', ['first_name' => 'Pieter', 'last_name' => 'Van de Velde']);
        $this->elke = $this->as('sales', ['first_name' => 'Elke', 'last_name' => 'Peeters']);
        $this->tom = $this->as('warehouse', ['first_name' => 'Tom', 'last_name' => 'Claes']);
    }

    private function as(string $role, array $attrs): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    public function test_notes_become_tasks_after_a_check_without_claude(): void
    {
        $this->actingAs($this->pieter)->get('/meetings/new')->assertOk()->assertSee('Paste the text of the meeting here');
        $this->actingAs($this->pieter)->post('/meetings', [
            'title' => 'Weekly team meeting', 'held_on' => '2026-10-26', 'kind' => 'internal', 'attendees' => [$this->pieter->id, $this->elke->id, $this->tom->id],
            'notes' => "We talked about the festival season.\nActie: offerte sturen naar Demo Agency (Elke) 31/10\nTom: Sol blinders labelen\nTODO catering bellen\nNice weather.",
        ])->assertRedirect();
        $m = Meeting::firstOrFail();
        $this->assertSame('rules', $m->analysed_by);
        $this->assertCount(3, $m->proposals);
        $this->assertSame([$this->elke->id, $this->tom->id, $this->pieter->id], array_column($m->proposals, 'user_id'));
        $this->assertSame('offerte sturen naar Demo Agency 31/10', $m->proposals[0]['title']);
        $this->assertSame('2026-10-31', $m->proposals[0]['due']);

        $this->actingAs($this->pieter)->get(route('meetings.show', $m))->assertOk()->assertSee('Check the tasks')->assertSee('Found with simple rules');

        // Untick the catering, add one by hand.
        $this->actingAs($this->pieter)->post(route('meetings.tasks', $m), ['tasks' => [
            ['take' => 1, 'title' => 'Offerte sturen naar Demo Agency', 'user_id' => $this->elke->id, 'due' => '2026-10-31'],
            ['take' => 1, 'title' => 'Sol blinders labelen', 'user_id' => $this->tom->id],
            ['take' => 0, 'title' => 'catering bellen', 'user_id' => $this->pieter->id],
            ['take' => 1, 'title' => 'Book the van', 'user_id' => $this->pieter->id],
        ]])->assertRedirect();
        $this->assertSame(3, Todo::count());
        $this->assertNull($m->fresh()->proposals);
        Notification::assertSentTo($this->elke, Confirmation::class);
        Notification::assertSentTo($this->tom, Confirmation::class);
        Notification::assertNotSentTo($this->pieter, Confirmation::class); // his own task

        // Elke sees her task on the dashboard and in her list, and ticks it off.
        $this->actingAs($this->elke)->get('/dashboard')->assertOk()->assertSee('My tasks');
        $this->actingAs($this->elke)->get('/tasks')->assertOk()->assertSee('Offerte sturen naar Demo Agency')->assertSee('Weekly team meeting');
        $t = Todo::where('assignee_id', $this->elke->id)->firstOrFail();
        $this->actingAs($this->tom)->post(route('todos.done', $t))->assertForbidden();
        $this->actingAs($this->elke)->post(route('todos.done', $t))->assertRedirect();
        $this->assertSame('done', $t->fresh()->status);

        // Tom was there: he sees the meeting. Someone who was not there does not.
        $this->actingAs($this->tom)->get(route('meetings.show', $m))->assertOk();
        $other = $this->as('sales', ['first_name' => 'Kevin']);
        $this->actingAs($other)->get(route('meetings.show', $m))->assertForbidden();
        $other->extraRights()->create(['right' => 'meetings.see_all']);
        $this->actingAs($other->fresh())->get(route('meetings.show', $m))->assertOk();
        $this->actingAs($other->fresh())->get('/tasks?v=all')->assertOk()->assertSee('Sol blinders labelen');

        // Dashboard "Everyone": without the right it says it only shows your own; with it, everyone's.
        $this->actingAs($this->tom)->get('/dashboard?who=all')->assertOk()->assertSee('Only yours: seeing colleagues’ tasks needs the right', false);
        $this->actingAs($other->fresh())->get('/dashboard?who=all')->assertOk()->assertSee('Tasks of everyone')->assertSee('<b>2</b><span>Tasks of everyone', false);
    }

    public function test_claude_reads_free_text_and_the_customer_task_goes_to_the_account_owner(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $co = Company::create(['name' => 'Demo Agency BV', 'country' => 'BE', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl', 'account_owner_id' => $this->elke->id]);
        Http::fake(function (Request $r) {
            $this->assertSame('test-key', $r->header('x-api-key')[0] ?? null);
            $this->assertStringContainsString('"name":"Tom Claes"', $r['messages'][0]['content']);

            return Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                'summary' => 'Festival in juni, 40 blinders.', 'decisions' => ['We leveren zelf.'],
                'tasks' => [
                    ['title' => 'Lichtplan maken', 'details' => '40 Sol blinders', 'who' => 'Tom', 'user_id' => $this->tom->id, 'for_customer' => false, 'due' => '2026-11-06'],
                    ['title' => 'Grondplan doorsturen', 'details' => '', 'who' => 'Sarah (klant)', 'user_id' => null, 'for_customer' => true, 'due' => null],
                    ['title' => 'Iets voor een onbekende', 'who' => 'Jan', 'user_id' => 999, 'due' => 'not a date'],
                ],
            ])]]]);
        });
        $this->actingAs($this->pieter)->post('/meetings', [
            'title' => 'Kick-off festival', 'held_on' => '2026-10-27', 'kind' => 'customer', 'company_id' => $co->id, 'attendees' => [$this->pieter->id, $this->tom->id],
            'notes' => 'Sarah wil 40 blinders. Tom maakt het lichtplan tegen volgende vrijdag. Sarah stuurt het grondplan.',
        ])->assertRedirect();
        $m = Meeting::firstOrFail();
        $this->assertSame(['claude', 'Festival in juni, 40 blinders.', ['We leveren zelf.']], [$m->analysed_by, $m->summary, $m->decisions]);
        [$a, $b, $c] = $m->proposals;
        $this->assertSame([$this->tom->id, false, '2026-11-06'], [$a['user_id'], $a['guessed'], $a['due']]);
        $this->assertSame([$this->elke->id, true, true], [$b['user_id'], $b['for_customer'], $b['guessed']]); // the account owner follows it up
        $this->assertSame([$this->elke->id, null], [$c['user_id'], $c['due']]);
        $this->actingAs($this->pieter)->get(route('meetings.show', $m))->assertOk()->assertSee('Read by Claude')->assertSee('Customer’s task: CLUK follows it up', false)->assertSee('Festival in juni');
    }

    public function test_tasks_by_hand_and_given_to_someone_else(): void
    {
        $this->actingAs($this->pieter)->post('/tasks', ['title' => 'Call the insurer', 'assignee_id' => $this->elke->id, 'due_on' => today()->subDay()->toDateString()])->assertRedirect();
        $t = Todo::firstOrFail();
        Notification::assertSentTo($this->elke, Confirmation::class);
        $this->actingAs($this->elke)->get('/dashboard')->assertSee('1 late');
        $this->actingAs($this->pieter)->get('/tasks?v=given')->assertOk()->assertSee('Call the insurer');
        $this->actingAs($this->elke)->put(route('todos.update', $t), ['assignee_id' => $this->tom->id, 'due_on' => ''])->assertRedirect();
        $this->assertSame($this->tom->id, $t->fresh()->assignee_id);
        Notification::assertSentTo($this->tom, Confirmation::class);
    }
}
