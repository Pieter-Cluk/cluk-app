<?php

namespace Tests\Feature;

use App\Models\Product\Subcategory;
use App\Models\ProductSales\SalesItem;
use App\Models\Sales\IncomingMessage;
use App\Models\Website\Chat;
use App\Services\ProductSales\CustomerPrices;
use App\Services\Website\Clukie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Clukie, the chat helper on the sales pages: office hours, answers from the catalogue, handover to the team. */
class WebsiteClukieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CustomerPrices::flush();
        config(['services.anthropic.key' => null, 'clukie.enabled' => true]);
    }

    private function product(string $no, string $brand, string $type, int $price, int $stock = 5): SalesItem
    {
        $i = new SalesItem(['number' => $no, 'brand' => $brand, 'type' => $type, 'unit' => 'piece', 'list_price_cents' => $price, 'stock_qty' => $stock, 'active' => true]);
        $i->placeIn(Subcategory::whereHas('category', fn ($c) => $c->where('catalogue', 'sales')->where('code', 'LIG'))->firstOrFail());
        $i->save();

        return $i;
    }

    public function test_clukie_is_only_on_the_sales_pages(): void
    {
        $this->get('/site/sales')->assertOk()->assertSee('id="clukie-cfg"', false)->assertSee('clukie.js');
        $this->get('/site/sales')->assertSee('images\\/clukie\\/idle.mp4', false)->assertSee('images\\/clukie\\/wave.mp4', false);
        $this->get('/site')->assertOk()->assertDontSee('clukie-cfg');
        $this->get('/site/dry-hire')->assertOk()->assertDontSee('clukie-cfg');
        config(['clukie.enabled' => false]);
        $this->get('/site/sales')->assertOk()->assertDontSee('clukie-cfg');
    }

    public function test_office_hours(): void
    {
        $bxl = fn (string $t) => Carbon::parse($t, 'Europe/Brussels');
        $this->assertTrue(Clukie::open($bxl('2026-10-28 09:00')));   // Wednesday
        $this->assertTrue(Clukie::open($bxl('2026-10-28 18:29')));
        $this->assertFalse(Clukie::open($bxl('2026-10-28 18:30')));
        $this->assertFalse(Clukie::open($bxl('2026-10-31 11:00')));  // Saturday
        $this->assertSame('2026-11-02 09:00', Clukie::nextOpen($bxl('2026-10-30 19:00'))->format('Y-m-d H:i')); // Friday evening → Monday
        $this->assertSame('2026-10-29 09:00', Clukie::nextOpen($bxl('2026-10-28 22:00'))->format('Y-m-d H:i'));
        $this->assertSame('2026-10-28 09:00', Clukie::nextOpen($bxl('2026-10-28 07:15'))->format('Y-m-d H:i'));
        $this->assertSame('Monday at 9:00', Clukie::nextOpenText('en', $bxl('2026-10-31 11:00')));
        $this->assertSame('morgen om 9:00', Clukie::nextOpenText('nl', $bxl('2026-10-28 20:00')));
    }

    public function test_without_ai_clukie_offers_the_team_and_the_team_gets_the_chat(): void
    {
        $r = $this->postJson('/site/sales/clukie', ['text' => 'Do you have silent disco headphones?', 'page' => '/site/sales'])->assertOk();
        $token = $r->json('token');
        $this->assertSame('bot', $r->json('status'));
        $this->assertSame(['visitor', 'clukie'], collect($r->json('messages'))->pluck('role')->all());
        $this->assertTrue($r->json('messages.1.offer'));

        $this->postJson('/site/sales/clukie/team', ['token' => $token, 'question' => 'Price for 200 headsets?'])->assertStatus(422); // name and email needed
        $t = $this->postJson('/site/sales/clukie/team', ['token' => $token, 'name' => 'Lotte Peeters', 'email' => 'lotte@example.be', 'question' => 'Price for 200 headsets?'])->assertOk();
        $this->assertSame('waiting', $t->json('status'));
        $this->assertContains('note', collect($t->json('messages'))->pluck('role')->all());

        $msg = IncomingMessage::where('channel', 'website')->latest('id')->first();
        $this->assertSame('chat', $msg->payload['type']);
        $this->assertSame('lotte@example.be', $msg->from_email);
        $this->assertStringContainsString('Price for 200 headsets?', $msg->body);
        $this->assertStringContainsString('Do you have silent disco headphones?', $msg->body);

        // The team answers in the chat; the visitor's browser picks it up. Clukie stays quiet now.
        $chat = Chat::where('token', $token)->first();
        $chat->forceFill(['status' => 'staff'])->save();
        $chat->say('staff', 'Hi Lotte, 200 is no problem. I will send you a quote.');
        $p = $this->getJson('/site/sales/clukie/poll?token='.$token.'&after=0')->assertOk();
        $this->assertSame('staff', $p->json('status'));
        $this->assertSame('Hi Lotte, 200 is no problem. I will send you a quote.', collect($p->json('messages'))->where('role', 'staff')->first()['body']);
        $this->postJson('/site/sales/clukie', ['token' => $token, 'text' => 'Thanks!'])->assertOk();
        $this->assertSame(0, $chat->messages()->where('role', 'clukie')->where('id', '>', $chat->messages()->where('role', 'staff')->max('id'))->count());
        $this->getJson('/site/sales/clukie/poll?token=nope')->assertNotFound();
    }

    public function test_clukie_answers_from_the_catalogue_and_fills_the_basket(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $fusion = $this->product('S-104561', 'Equinox', 'Fusion 400 Hybrid', 161459, 2);
        $this->product('S-104562', 'Equinox', 'Fusion 400 Hybrid flight case', 51875, 0);

        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'search_products', 'input' => ['query' => 'fusion 400']]]])
            ->push(['stop_reason' => 'tool_use', 'content' => [
                ['type' => 'tool_use', 'id' => 't2', 'name' => 'show_products', 'input' => ['codes' => ['S-104561']]],
                ['type' => 'tool_use', 'id' => 't3', 'name' => 'add_to_basket', 'input' => ['code' => 'S-104561', 'qty' => 2]],
            ]])
            ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Found it! The Fusion 400 Hybrid is € 1.614,59 excl. VAT and 2 are in stock. I put two in your basket.']]]),
        ]);

        $r = $this->postJson('/site/sales/clukie', ['text' => 'Fusion 400, two please', 'page' => '/site/sales'])->assertOk();
        $bot = collect($r->json('messages'))->firstWhere('role', 'clukie');
        $this->assertStringContainsString('Fusion 400 Hybrid is € 1.614,59', $bot['body']);
        $this->assertSame([$fusion->id], collect($bot['cards'])->pluck('id')->all());
        $this->assertSame(161459, $bot['cards'][0]['price']);
        $this->assertSame([['type' => 'add', 'id' => $fusion->id, 'name' => 'Equinox Fusion 400 Hybrid', 'code' => 'S-104561', 'price' => 161459, 'qty' => 2]], $r->json('actions'));

        // what the search tool gave Claude: our products with prices excl. VAT and stock
        $second = Http::recorded()[1][0]->data();
        $result = json_decode($second['messages'][count($second['messages']) - 1]['content'][0]['content'], true);
        $this->assertSame(['S-104561', 'S-104562'], collect($result['products'])->pluck('code')->sort()->values()->all());
        $this->assertSame(1614.59, collect($result['products'])->firstWhere('code', 'S-104561')['price_eur']);
        $this->assertStringContainsString('Monday to Friday 9:00–18:30', $second['system']);
        $this->assertSame(5, count(Clukie::tools()));
    }

    public function test_clukie_switches_to_a_new_topic_and_always_answers(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $this->product('S-200001', 'Clever Acoustics', 'ACT 35 Powered Speakers', 39900);
        $this->product('S-200002', 'TCMFX', 'Confettigun', 96000);

        // 1. speakers: Clukie keeps searching; the last round may not use tools, so it still answers
        $search = ['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 's', 'name' => 'search_products', 'input' => ['query' => 'speaker']]]];
        $seq = Http::sequence();
        for ($i = 0; $i < 5; $i++) {
            $seq->push($search);
        }
        $seq->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'We have powered speakers, what is it for?']]]);
        // 2. confetti: a new topic
        $seq->push(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 'c1', 'name' => 'show_products', 'input' => ['codes' => ['S-200002']]]]]);
        $seq->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Confetti time! 🎉']]]);
        Http::fake(['api.anthropic.com/*' => $seq]);

        $a = $this->postJson('/site/sales/clukie', ['text' => 'Do you sell speakers?'])->assertOk();
        $this->assertSame('We have powered speakers, what is it for?', collect($a->json('messages'))->where('role', 'clukie')->last()['body']);
        $calls = Http::recorded();
        $this->assertArrayNotHasKey('tool_choice', $calls[0][0]->data());
        $this->assertSame(['type' => 'none'], $calls[5][0]->data()['tool_choice']);

        $b = $this->postJson('/site/sales/clukie', ['token' => $a->json('token'), 'text' => 'And confetti?'])->assertOk();
        $bot = collect($b->json('messages'))->where('role', 'clukie')->last();
        $this->assertSame(['S-200002'], collect($bot['cards'])->pluck('code')->all());
        $req = Http::recorded()[6][0]->data();
        $this->assertStringContainsString("LATEST message", $req['system']);
        $this->assertSame('And confetti?', end($req['messages'])['content']);
    }

    public function test_history_marks_failed_answers_and_shown_products(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $chat = Chat::create(['token' => 'hist', 'status' => 'bot', 'locale' => 'en']);
        $chat->say('visitor', 'Speakers?');
        $chat->say('clukie', 'Oops', ['failed' => true]);
        $chat->say('visitor', 'Fusion 400?');
        $chat->say('clukie', 'Here it is', ['cards' => [['name' => 'Equinox Fusion 400 Hybrid', 'code' => 'S-104561']]]);
        $chat->say('visitor', 'Price of that one?');
        Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'ok']]])]);
        (new Clukie($chat, null))->answer();
        $m = Http::recorded()[0][0]->data()['messages'];
        $this->assertStringContainsString('could not answer this', $m[1]['content']);
        $this->assertStringContainsString('Product cards shown: Equinox Fusion 400 Hybrid (S-104561)', $m[3]['content']);
        $this->assertSame(['Speakers?', 'Price of that one?'], [$m[0]['content'], $m[4]['content']]); // oldest first, latest last

        // a long chat: only the most recent lines go along, still in order
        for ($i = 1; $i <= 30; $i++) {
            $chat->say($i % 2 ? 'clukie' : 'visitor', 'line '.$i);
        }
        $chat->say('visitor', 'Newest question');
        (new Clukie($chat, null))->answer();
        $m = Http::recorded()[1][0]->data()['messages'];
        $this->assertStringEndsWith('Newest question', end($m)['content']);
        $this->assertStringNotContainsString('Speakers?', json_encode($m));
    }

    public function test_clukie_hands_over_when_asked(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'id' => 'h1', 'name' => 'hand_over', 'input' => ['summary' => 'Wants advice on a lighting rig for a 500-person venue.', 'name' => 'Tom Claes', 'email' => 'tom@example.be']]]])
            ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Done! A colleague continues right here.']]]),
        ]);
        $r = $this->postJson('/site/sales/clukie', ['text' => 'Can I talk to someone about a rig? Tom Claes, tom@example.be'])->assertOk();
        $this->assertSame('waiting', $r->json('status'));
        $chat = Chat::where('token', $r->json('token'))->first();
        $this->assertSame(['Tom Claes', 'tom@example.be'], [$chat->name, $chat->email]);
        $this->assertSame('tom@example.be', $chat->incoming->from_email);
        $this->assertStringContainsString('500-person venue', $chat->incoming->subject);
    }

    public function test_privacy_policy_mentions_clukie_and_old_chats_are_deleted(): void
    {
        $this->get('/site/privacy?lang=en')->assertOk()->assertSee('Anthropic')->assertSee('Chats with Clukie: 12 months');
        $this->get('/site/privacy?lang=nl')->assertOk()->assertSee('Chat met Clukie');
        $old = Chat::create(['token' => 'old', 'status' => 'bot', 'last_message_at' => now()->subMonths(13)]);
        $new = Chat::create(['token' => 'new', 'status' => 'bot', 'last_message_at' => now()->subMonths(2)]);
        $this->assertSame(1, Clukie::prune());
        $this->assertNull(Chat::find($old->id));
        $this->assertNotNull(Chat::find($new->id));
    }
}
