<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\User;
use App\Models\Website\Chat;
use App\Notifications\Confirmation;
use App\Services\Website\Clukie;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Sales › Chats: the team takes over Clukie chats; and Clukie for the team in the platform. */
class TeamChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['services.anthropic.key' => null, 'clukie.enabled' => true]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function chat(): Chat
    {
        $c = Chat::create(['token' => str_repeat('t', 40), 'locale' => 'nl', 'status' => 'bot', 'name' => 'Jo Peeters', 'email' => 'jo@demo.example', 'page' => '/sales/product/12-x']);
        $c->say('visitor', 'Hebben jullie 20 Fusion 400 op stock?');
        $c->say('clukie', 'Ik geef je door aan het team!', ['cards' => [['code' => 'S-1', 'name' => 'Fusion 400', 'price' => 49900, 'url' => 'https://cluk.live/sales/product/1']]]);

        return $c;
    }

    public function test_a_handed_over_chat_is_answered_by_the_team_and_closed(): void
    {
        Notification::fake();
        $sales = $this->user('sales');
        $chat = $this->chat();
        $msg = Clukie::toTeam($chat, 'Wants 20 Fusion 400');

        // The team hears about it, the dashboard and Incoming lead to the chat.
        Notification::assertSentTo($sales, Confirmation::class, fn ($n) => str_contains($n->subject, 'Jo Peeters'));
        $this->actingAs($sales, 'web')->get('/dashboard')->assertOk()->assertSee('Chats waiting')->assertSee('db-urgent', false);
        $this->actingAs($sales, 'web')->get(route('incoming.index', ['open' => $msg->id]))->assertOk()->assertSee(route('chats.show', $chat));
        $this->actingAs($sales, 'web')->get(route('chats.index'))->assertOk()->assertSee('Jo Peeters')->assertSee('Waiting for an answer');
        $this->actingAs($sales, 'web')->get(route('chats.show', $chat))->assertOk()
            ->assertSee('Hebben jullie 20 Fusion 400 op stock?')->assertSee('Fusion 400')->assertSee('jo@demo.example')->assertSee('Handed to the team')->assertSee('Nederlands');

        // The visitor is still there: the answer only shows in the chat.
        $this->actingAs($sales, 'web')->post(route('chats.reply', $chat), ['text' => 'Ja, 24 stuks op stock!'])->assertRedirect();
        $chat->refresh();
        $this->assertSame(['staff', $sales->id], [$chat->status, $chat->staff_user_id]);
        Notification::assertNothingSentTo(new AnonymousNotifiable);
        $this->getJson('/site/sales/clukie/poll?token='.$chat->getRawOriginal('token').'&after=0')->assertOk()->assertJsonFragment(['role' => 'staff', 'body' => 'Ja, 24 stuks op stock!']);
        $this->assertTrue(AuditLog::where('action', 'chat.replied')->exists());

        // The visitor writes again: the team member on it is told.
        $chat->say('visitor', 'Top, en de prijs?');
        $this->actingAs($sales, 'web')->getJson(route('chats.poll', $chat).'?after=0')->assertOk()->assertJsonFragment(['status' => 'staff']);

        // The visitor left: the answer also goes by email, in Dutch.
        $this->travel(15)->minutes();
        $this->actingAs($sales, 'web')->post(route('chats.reply', $chat), ['text' => '€ 499 per stuk.'])->assertRedirect();
        Notification::assertSentOnDemand(Confirmation::class, fn ($n, $ch, $to) => $to->routes['mail'] === 'jo@demo.example' && str_contains($n->subject, 'CLUK-team'));

        $this->actingAs($sales, 'web')->post(route('chats.close', $chat))->assertRedirect(route('chats.index'));
        $this->assertSame('closed', $chat->fresh()->status);
    }

    public function test_the_team_chooses_the_company_when_the_email_belongs_to_several(): void
    {
        Notification::fake();
        $sales = $this->user('sales');
        $chat = $this->chat();
        $msg = Clukie::toTeam($chat, 'Question');
        $a = \App\Models\Company::create(['name' => 'Agency A BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'country' => 'BE']);
        $b = \App\Models\Company::create(['name' => 'Festival B VZW', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'country' => 'BE']);
        $jo = \App\Models\Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'JO@demo.example']);
        $a->contacts()->attach($jo->id);
        $b->contacts()->attach($jo->id);

        $this->assertSame(['Agency A BV', 'Festival B VZW'], \App\Services\Website\TeamChats::candidates($chat)->pluck('name')->all());
        $this->actingAs($sales, 'web')->get(route('chats.show', $chat))->assertOk()->assertSee('2 companies use this email address');
        $this->assertNull($chat->fresh()->company_id); // never by itself

        $this->actingAs($sales, 'web')->post(route('chats.link', $chat), ['company_id' => $b->id])->assertRedirect();
        $chat->refresh();
        $this->assertSame([$b->id, $jo->id], [$chat->company_id, $chat->contact_id]);
        $this->assertSame($b->id, $msg->fresh()->company_id);
        $this->assertTrue(AuditLog::where('action', 'chat.linked')->exists());
        $this->actingAs($sales, 'web')->get(route('companies.show', $b))->assertOk()->assertSee('Website chats')->assertSee(route('chats.show', $chat));
        $this->actingAs($sales, 'web')->get(route('chats.index'))->assertOk()->assertSee('Festival B VZW');

        $this->actingAs($sales, 'web')->post(route('chats.link', $chat), ['company_id' => ''])->assertRedirect();
        $this->assertNull($chat->fresh()->company_id);
    }

    public function test_only_sales_can_see_chats_and_level_one_cannot_answer(): void
    {
        $chat = $this->chat();
        $this->actingAs($this->user('warehouse'), 'web')->get(route('chats.index'))->assertForbidden();
        $reader = $this->user('sales');
        $reader->moduleOverrides()->create(['module' => 'sales', 'level' => 1]);
        $this->actingAs($reader->fresh(), 'web')->get(route('chats.show', $chat))->assertOk()->assertDontSee('name="text"', false);
        $this->actingAs($reader->fresh(), 'web')->post(route('chats.reply', $chat), ['text' => 'x'])->assertForbidden();
    }

    public function test_clukie_for_the_team_answers_with_tools_within_the_users_rights(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $item = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'serial' => true]);
        $item->assets()->create(['code' => 'LT-0001', 'status' => 'available', 'location' => 'GA1B1']);
        $calls = 0;
        Http::fake(['api.anthropic.com/*' => function ($req) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return Http::response(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 'tu1', 'name' => 'find_equipment', 'input' => ['query' => 'spiider']]]]);
            }
            $result = json_decode($req['messages'][2]['content'][0]['content'], true);

            return Http::response(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Free on '.implode(',', array_keys($result['items'][0]['where_free']))]]]);
        }]);

        $crew = $this->user('warehouse');
        $crew->moduleOverrides()->create(['module' => 'companies', 'level' => 0]);
        $crew = $crew->fresh();
        $this->actingAs($crew, 'web')->get('/dashboard')->assertOk()->assertSee('id="clukie-cfg"', false)->assertSee('clukie-staff-'.$crew->id);
        $r = $this->actingAs($crew, 'web')->postJson(route('assistant.message'), ['text' => 'Waar liggen de Spiiders?'])->assertOk();
        $this->assertSame('Free on GA1B1', $r->json('messages.1.body'));
        $this->assertSame('visitor', $r->json('messages.0.role'));
        Http::assertSent(fn ($req) => collect($req['tools'] ?? [])->pluck('name')->contains('find_equipment') && ! collect($req['tools'] ?? [])->pluck('name')->contains('find_customers'));

        // Someone else's chat stays private; a tool outside the rights says so.
        $other = $this->user('sales');
        $this->actingAs($other, 'web')->getJson(route('assistant.poll', ['token' => $r->json('token')]))->assertNotFound();
        $clukie = new \App\Services\Assistant\StaffClukie(\App\Models\Assistant\AssistantChat::firstOrFail(), $crew);
        $this->assertSame(['error' => 'no access'], $clukie->tool('find_customers', ['query' => 'demo']));
    }
}
