<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Finance\PreInvoice;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\ChargeNotice;
use App\Models\Warehouse\Check;
use App\Models\Warehouse\CheckProcedure;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Check Builder → step-by-step Tech Check → return costs: account owner, customer informed, invoiced after the deadline. */
class TechCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $crew;

    private User $builder;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Notification::fake();
        $this->crew = $this->as('warehouse', 'nl');
        $this->builder = $this->as('warehouse');
        $this->builder->extraRights()->create(['right' => 'warehouse.build_checks']);
        $this->owner = $this->as('sales', 'en', ['first_name' => 'Elke', 'email' => 'elke@cluk.example']);
    }

    private function as(string $role, string $locale = 'en', array $extra = []): User
    {
        return User::factory()->create($extra + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => $locale]);
    }

    /** Two Spiiders back from one order of a customer whose account owner is Elke. */
    protected function returned(): array
    {
        $spiider = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'category' => 'Light', 'serial' => true, 'day_price_cents' => 6500]);
        $cable = Item::create(['number' => 'RIG-SAFETY', 'kind' => 'item', 'name' => 'Safety cable', 'category' => 'Rigging', 'serial' => false, 'stock_qty' => 50, 'catalog_price_cents' => 800]);
        $spiider->children()->attach($cable->id, ['qty' => 1, 'role' => 'kit']);
        $company = Company::create(['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl', 'account_owner_id' => $this->owner->id]);
        $contact = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@demo.example', 'language' => 'nl']);
        $order = Order::create(['number' => 'R-OC-2026-0102', 'status' => 'approved', 'company_id' => $company->id, 'contact_id' => $contact->id, 'title' => 'Light rig festival', 'language' => 'nl',
            'out_from' => now()->subDays(3), 'out_until' => now()->subDay(), 'use_from' => today()->subDays(2), 'use_until' => today()->subDay(), 'vat_rate_bp' => 2100, 'net_cents' => 1,
            'loaded_at' => now()->subDays(3), 'returned_at' => now()->subHours(2), 'handed_by_id' => $this->crew->id]);
        $line = $order->lines()->create(['kind' => 'item', 'item_id' => $spiider->id, 'name' => 'Robe Spiider', 'qty' => 2, 'amount_cents' => 1, 'position' => 0]);
        $assets = collect(['LT-0142', 'LT-0143'])->map(function ($code) use ($spiider, $order, $line) {
            $a = Asset::create(['item_id' => $spiider->id, 'code' => $code, 'status' => Asset::CHECK, 'location' => 'BENCH', 'out_order_id' => $order->id]);
            OrderAsset::create(['order_id' => $order->id, 'order_line_id' => $line->id, 'asset_id' => $a->id]);
            Check::create(['asset_id' => $a->id, 'order_id' => $order->id, 'dirt' => 1, 'cleaned_at' => now()]);

            return $a;
        });

        return [$spiider, $order, $assets];
    }

    /** The builder makes "Moving head" with two steps, publishes v1 and links the Spiider. */
    protected function procedure(Item $item): CheckProcedure
    {
        $this->actingAs($this->builder)->get('/warehouse/check-builder')->assertOk()->assertSee('Robe Spiider · LT-SPIIDER');
        $this->actingAs($this->builder)->post('/warehouse/check-builder', ['item_id' => $item->id])->assertRedirect();
        $p = CheckProcedure::firstOrFail();
        $this->assertSame([$item->id, 'Robe Spiider · LT-SPIIDER', $p->id], [$p->item_id, $p->name('en'), $item->fresh()->check_procedure_id]);
        $this->actingAs($this->builder)->post('/warehouse/check-builder/'.$p->id.'/steps', [
            'title_nl' => 'Ventilator controleren', 'title_en' => 'Check the fan', 'text_nl' => 'Open het luik. Draait de ventilator vrij?', 'text_en' => 'Open the hatch. Does the fan turn freely?',
            'answer' => 'okfail', 'costs' => "Ventilator vervangen | Replace fan | 45\nGrondige reiniging | Heavy cleaning",
            'photos' => [UploadedFile::fake()->image('fan.jpg')],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->builder)->post('/warehouse/check-builder/'.$p->id.'/steps', ['title_nl' => 'Lampuren noteren', 'title_en' => 'Note the lamp hours', 'answer' => 'value'])->assertSessionHasNoErrors();
        $this->actingAs($this->builder)->post('/warehouse/check-builder/'.$p->id.'/publish', ['note' => 'first'])->assertSessionHasNoErrors();

        return $p->fresh();
    }

    public function test_the_builder_makes_a_procedure_in_versions(): void
    {
        [$spiider] = $this->returned();
        $this->actingAs($this->crew)->get('/warehouse/check-builder')->assertForbidden(); // extra right, off by default
        $p = $this->procedure($spiider);
        $this->assertSame([1, 2, $p->id], [$p->current->version, count($p->current->steps), $spiider->fresh()->check_procedure_id]);
        $this->assertSame(['label' => ['nl' => 'Ventilator vervangen', 'en' => 'Replace fan'], 'cents' => 4500], $p->current->steps[0]['costs'][0]);
        $this->assertNull($p->current->steps[0]['costs'][1]['cents']); // priced later
        $this->assertCount(1, $p->current->steps[0]['photos']);
        $this->actingAs($this->builder)->get('/warehouse/check-builder/'.$p->id)->assertOk()->assertSee('Check the fan')->assertSee('Version 1 live')
            ->assertSee('1 × Safety cable')->assertSee('open the dry-hire card');
        $this->actingAs($this->builder)->get('/warehouse/check-photo?p='.urlencode($p->current->steps[0]['photos'][0]))->assertOk();
        $this->actingAs($this->builder)->get('/warehouse/check-photo?p=../../.env')->assertNotFound();

        // Changed and moved: the draft differs until version 2 is published.
        $key = $p->draft_steps[1]['key'];
        $this->actingAs($this->builder)->post('/warehouse/check-builder/'.$p->id.'/steps/'.$key.'/move', ['dir' => 'up']);
        $this->assertTrue($p->fresh()->draftChanged());
        $this->assertSame('Note the lamp hours', $p->fresh()->draft_steps[0]['title']['en']);
        $this->assertSame('Check the fan', $p->fresh()->current->steps[0]['title']['en']); // live version unchanged
        $this->actingAs($this->builder)->post('/warehouse/check-builder/'.$p->id.'/publish');
        $this->assertSame(2, $p->fresh()->current->version);
        $this->actingAs($this->builder)->get('/warehouse/tech-check')->assertOk()->assertSee('LT-0142')->assertSee('Robe Spiider · LT-SPIIDER · v2');
    }

    public function test_a_step_by_step_check_and_the_return_costs_to_the_customer(): void
    {
        [$spiider, $order, $assets] = $this->returned();
        $p = $this->procedure($spiider);
        [$a, $b] = $assets;

        // Step 1 on screen (Dutch for this crew member), the next one only after answering.
        // Step 1, always: the complete view. Not clean (€ 10) needs a photo.
        $this->actingAs($this->crew)->get('/w/check/'.$a->id)->assertOk()->assertSee('Stap 1 van 4')->assertSee('Volledig zicht op de fixture')->assertSee('€ 10,00')->assertDontSee('Ventilator controleren');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', ['dirt' => 2, 'broken' => 'no'])->assertSessionHasErrors('note');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', ['dirt' => 2, 'broken' => 'no', 'photos' => [UploadedFile::fake()->image('dust.jpg')]])->assertRedirect();
        $this->actingAs($this->crew)->get('/w/check/'.$a->id)->assertOk()->assertSee('Stap 2 van 4')->assertSee('Ventilator controleren')->assertDontSee('Lampuren noteren');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', [])->assertSessionHasErrors('answer');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', ['answer' => 'fail'])->assertSessionHasErrors('note'); // note and photo needed
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', ['answer' => 'fail', 'note' => 'Fan blocked', 'costs' => [0], 'photos' => [UploadedFile::fake()->image('blocked.jpg')]])->assertRedirect('/w/check/'.$a->id);
        $this->actingAs($this->crew)->get('/w/check/'.$a->id)->assertSee('Stap 3 van 4')->assertSee('Lampuren noteren');

        // The procedure changes now: this check keeps version 1.
        $this->actingAs($this->builder)->post('/warehouse/check-builder/'.$p->id.'/steps', ['title_nl' => 'Extra', 'answer' => 'confirm']);
        $this->actingAs($this->builder)->post('/warehouse/check-builder/'.$p->id.'/publish');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', ['value' => '1240'])->assertRedirect();
        // Last step: the accessories from the dry-hire card; the card itself is one tap away.
        $this->actingAs($this->crew)->get('/w/check/'.$a->id)->assertSee('Stap 4 van 4')->assertSee('Toebehoren volledig?')->assertSee('1 × Safety cable')->assertSee('/dry-hire/items/'.$spiider->id, false);
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', [])->assertSessionHasErrors('missing');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', ['missing' => [Item::where('number', 'RIG-SAFETY')->value('id') => 1]])->assertSessionHasErrors('missing'); // photo first
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/step', ['missing' => [Item::where('number', 'RIG-SAFETY')->value('id') => 1], 'photos' => [UploadedFile::fake()->image('back.jpg')]])->assertRedirect();
        $this->actingAs($this->crew)->get('/w/check/'.$a->id)->assertSee('Overzicht')->assertSee('Fan blocked')->assertSee('1240')->assertSee('Onvolledige retour');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id.'/finish')->assertRedirect('/w/scan');
        $check = Check::where('asset_id', $a->id)->firstOrFail();
        $this->assertSame(['repair', 1], [$check->result, $check->procedureVersion->version]);
        $this->assertSame(['Ventilator vervangen · LT-0142', 4500], [Charge::first()->label, Charge::first()->amount_cents]); // customer's language
        $this->assertSame(1000, Charge::where('kind', 'cleaning')->value('amount_cents')); // not clean
        $this->assertSame(['missing', 800], [Charge::where('kind', 'missing')->value('kind'), Charge::where('kind', 'missing')->value('amount_cents')]);
        $this->assertNull(ChargeNotice::first()); // the second piece is not checked yet

        // The second piece (version 2 now: 3 steps), all OK: every piece checked → the account owner gets it.
        $this->actingAs($this->crew)->post('/w/check/'.$b->id.'/step', ['dirt' => 1, 'broken' => 'no']);
        $this->actingAs($this->crew)->post('/w/check/'.$b->id.'/step', ['answer' => 'ok']);
        $this->actingAs($this->crew)->post('/w/check/'.$b->id.'/step', ['value' => '300']);
        $this->actingAs($this->crew)->post('/w/check/'.$b->id.'/step', ['answer' => 'done']);
        $this->actingAs($this->crew)->post('/w/check/'.$b->id.'/step', ['missing' => [Item::where('number', 'RIG-SAFETY')->value('id') => 0]]);
        $this->actingAs($this->crew)->post('/w/check/'.$b->id.'/finish')->assertRedirect();
        $this->assertSame('ok', Check::where('asset_id', $b->id)->value('result'));
        $this->assertSame('review', ChargeNotice::firstOrFail()->status);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'elke@cluk.example' && str_contains($n->subject, 'R-OC-2026-0102'));
        $this->actingAs($this->owner)->get('/dashboard')->assertSee('Return costs to review');

        // Only the account owner (or someone who approves orders) decides; she informs the customer.
        $this->actingAs($this->as('sales'))->post('/warehouse/charges/'.$order->id.'/inform')->assertForbidden();
        $this->actingAs($this->owner)->get('/warehouse/charges/'.$order->id)->assertOk()->assertSee('Account owner: Elke')->assertSee('Incomplete return')->assertSee('jo@demo.example', false);
        $this->actingAs($this->owner)->post('/warehouse/charges/'.$order->id.'/inform')->assertSessionHasNoErrors();
        $notice = ChargeNotice::firstOrFail();
        $this->assertSame(['sent', 'jo@demo.example', today()->addDays(7)->toDateString(), 'informed'], [$notice->status, $notice->sent_to, $notice->deadline->toDateString(), Charge::first()->status]);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'jo@demo.example' && str_contains($n->subject, 'Kosten na uw huur'));

        // The customer's page: the line with the photo, and a reaction goes back to Elke.
        $this->get('/costs/'.$notice->token)->assertOk()->assertSee('Ventilator vervangen · LT-0142')->assertSee('/costs/'.$notice->token.'/photo/'.$check->id.'/0', false);
        $this->get('/costs/'.$notice->token.'/photo/'.$check->id.'/0')->assertOk();
        $this->post('/costs/'.$notice->token.'/react', ['text' => 'The fan was already noisy when we picked it up.'])->assertRedirect();
        $this->assertSame(['objected', 'pending'], [$notice->fresh()->status, Charge::first()->status]);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'elke@cluk.example' && str_contains($n->subject, 'Reaction'));

        // Elke halves it as a gesture and informs again; no reaction before the deadline → invoiced.
        $this->actingAs($this->owner)->post('/warehouse/charges/line/'.Charge::first()->id, ['action' => 'half']);
        $this->actingAs($this->owner)->post('/warehouse/charges/'.$order->id.'/inform')->assertSessionHasNoErrors();
        $this->travel(8)->days();
        $this->artisan('cluk:return-costs')->assertSuccessful();
        $this->assertSame(['invoiced', 'approved'], [$notice->fresh()->status, Charge::first()->status]);
        $pf = PreInvoice::where('source_type', 'charges')->firstOrFail();
        $this->assertSame([2250 + 800 + 1000, 'R-CH-2026-0102'], [$pf->net_cents, $pf->source_code]);
    }

    public function test_pages_speak_every_language(): void
    {
        [$spiider, $order, $assets] = $this->returned();
        $p = $this->procedure($spiider);
        foreach (array_keys(config('cluk.locales')) as $l) {
            $this->builder->forceFill(['locale' => $l])->save();
            foreach (['/warehouse/check-builder', '/warehouse/check-builder/'.$p->id, '/warehouse/tech-check', '/w/check/'.$assets[0]->id] as $url) {
                $html = $this->actingAs($this->builder->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\bchecks\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html)), "{$url} ({$l})");
            }
        }
    }
}
