<?php

namespace Tests\Feature;

use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use App\Models\Sales\IncomingMessage;
use App\Notifications\Confirmation;
use App\Services\Website\DryHireCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebsiteCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $number, string $name, string $sub, string $cat, array $extra = []): Item
    {
        $i = new Item(array_merge(['number' => $number, 'kind' => 'item', 'name' => $name, 'brand' => 'L-Acoustics', 'type' => $number, 'unit' => 'pcs', 'serial' => false, 'stock_qty' => 4, 'day_price_cents' => 9500, 'active' => true], $extra));
        $i->placeIn(Subcategory::findOrFail(self::sub($sub, $cat)));
        $i->save();

        return $i;
    }

    public function test_the_catalogue_shows_what_the_platform_has(): void
    {
        $sub = $this->item('KS28', 'L-Acoustics KS28 subwoofer', 'SUB', 'AUD'); // not ticked for the website
        $this->item('K2', 'L-Acoustics K2', 'SPK', 'AUD', ['on_website' => true]);
        $this->item('OLD', 'Retired speaker', 'SPK', 'AUD', ['active' => false]);
        $this->item('SRV', 'Transport service', 'SPK', 'AUD', ['kind' => 'service']);

        $page = $this->get('/site/dry-hire')->assertOk()->assertSee('L-Acoustics KS28 subwoofer')->assertSee('L-Acoustics K2')
            ->assertDontSee('Retired speaker')->assertDontSee('Transport service');
        $data = json_decode(html_entity_decode($this->between($page->getContent(), '<script type="application/json" id="dh-data">', '</script>')), true);
        $ids = array_column($data['items'], 'id');
        $this->assertContains($sub->id, $ids);
        $this->assertNotContains(Item::where('number', 'OLD')->value('id'), $ids);
        $this->assertNotContains(Item::where('number', 'SRV')->value('id'), $ids);
        $this->assertSame(DryHireCatalogue::query()->count(), $data['stats']['products']);
        $this->assertSame(4, collect($data['items'])->firstWhere('id', $sub->id)['q']);
        $this->assertContains('Subwoofers', array_column($data['subs'], 'name'));
        $this->assertSame(9500, collect($data['items'])->firstWhere('id', $sub->id)['p']);

        config(['website.only_ticked' => true]);
        $this->get('/site/dry-hire')->assertOk()->assertDontSee('L-Acoustics KS28 subwoofer')->assertSee('L-Acoustics K2');
    }

    public function test_names_follow_the_visitor_language(): void
    {
        $this->item('KS28', 'L-Acoustics KS28 subwoofer', 'SPK', 'AUD');
        $this->get('/site/dry-hire?lang=nl')->assertOk()->assertSee('Luidsprekers')->assertSee('Zoek in ', false)->assertSee('Wat zit erin', false);
        $this->get('/site/dry-hire?lang=fr')->assertOk()->assertSee('Enceintes');
        $this->get('/site/?lang=es')->assertOk()->assertSee('Alquiler en seco');
    }

    public function test_product_page_and_panel_data(): void
    {
        $i = $this->item('KS28', 'L-Acoustics KS28 subwoofer', 'SUB', 'AUD', ['descriptions' => ['en' => 'Dual 18-inch reference sub.', 'nl' => 'Dubbele 18-inch referentiesub.'], 'weight_kg' => 79]);
        $slug = $i->id.'-l-acoustics-ks28-subwoofer';

        $this->get('/site/dry-hire/'.$slug)->assertOk()->assertSee('Dual 18-inch reference sub.')->assertSee('79 kg')->assertSee('"@type":"Product"', false);
        $this->get('/site/dry-hire/'.$i->id)->assertRedirect('/site/dry-hire/'.$slug);
        $this->getJson('/site/dry-hire/data/'.$i->id.'?lang=nl')->assertOk()->assertJsonPath('description', 'Dubbele 18-inch referentiesub.')->assertJsonPath('subcategory', 'Subwoofers');

        $i->update(['active' => false]);
        $this->get('/site/dry-hire/'.$slug)->assertNotFound();
        $this->get('/site/dry-hire/photo/'.$i->id.'/480')->assertNotFound();
    }

    public function test_a_request_lands_in_incoming_with_its_lines_and_a_copy_by_email(): void
    {
        Notification::fake();
        $k2 = $this->item('K2', 'L-Acoustics K2', 'SPK', 'AUD');
        $hidden = $this->item('OLD', 'Retired speaker', 'SPK', 'AUD', ['active' => false]);
        $from = now()->addDays(3)->toDateString();
        $until = now()->addDays(6)->toDateString();

        $this->postJson('/site/dry-hire/request', [
            'name' => 'Lotte Peeters', 'email' => 'Lotte@Example.be', 'company' => 'Peeters Events', 'message' => 'Delivery in Gent please.',
            'first_day' => $from, 'last_day' => $until, 'lines' => [['id' => $k2->id, 'qty' => 12], ['id' => $hidden->id, 'qty' => 2]],
        ])->assertOk()->assertJsonPath('ok', true);

        $m = IncomingMessage::firstOrFail();
        $this->assertSame(['website', 'dryhire', 'new', 'lotte@example.be'], [$m->channel, $m->module, $m->status ?? 'new', $m->from_email]);
        $this->assertCount(1, $m->payload['lines']);
        $this->assertSame(['K2', 12, 9500], [$m->payload['lines'][0]['number'], $m->payload['lines'][0]['qty'], $m->payload['lines'][0]['day_price_cents']]);
        $this->assertSame(4, $m->payload['days']);
        $this->assertEquals(1.5, $m->payload['factor']);
        $this->assertSame(171000, $m->payload['estimate_cents']); // 12 × € 95 × 1.5
        $this->assertStringContainsString('12 × L-Acoustics K2 (K2)', $m->body);
        $this->assertStringContainsString('Delivery in Gent please.', $m->body);
        Notification::assertSentOnDemand(Confirmation::class);
    }

    public function test_requests_are_checked_and_bots_are_ignored(): void
    {
        $k2 = $this->item('K2', 'L-Acoustics K2', 'SPK', 'AUD');
        $this->postJson('/site/dry-hire/request', ['email' => 'not-an-email', 'first_day' => now()->subDay()->toDateString(), 'last_day' => now()->toDateString(), 'lines' => [['id' => $k2->id, 'qty' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'first_day']);
        $this->postJson('/site/dry-hire/request', ['name' => 'Bot', 'email' => 'bot@spam.test', 'website_url' => 'http://spam', 'first_day' => now()->toDateString(), 'last_day' => now()->toDateString(), 'lines' => [['id' => $k2->id, 'qty' => 1]]])
            ->assertOk();
        $this->assertSame(0, IncomingMessage::count());
    }

    public function test_homepage_and_header_link_to_the_platform(): void
    {
        $this->get('/site')->assertOk()->assertSee('Cluk it. Why not!')->assertSee(rtrim(config('app.url'), '/').'/afrit6', false)->assertSee('/site/dry-hire', false)->assertDontSee('CLUK Factory')->assertSee('images/site/favicon.ico', false);
    }

    private function between(string $s, string $a, string $b): string
    {
        $start = strpos($s, $a) + strlen($a);

        return substr($s, $start, strpos($s, $b, $start) - $start);
    }
}
