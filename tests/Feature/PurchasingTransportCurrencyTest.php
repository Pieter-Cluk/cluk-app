<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductSales\SalesItem;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Finance\ExchangeRates;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Purchasing: CLUK organises the transport, and supplier prices in another currency at the ECB day rate. */
class PurchasingTransportCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const ECB = '<?xml version="1.0" encoding="UTF-8"?><gesmes:Envelope><Cube><Cube time="2026-11-02">'
        .'<Cube currency="USD" rate="1.0800"/><Cube currency="GBP" rate="0.8400"/></Cube></Cube></gesmes:Envelope>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Cache::flush();
    }

    /** Shure SM58 (list 119) with a US supplier (USD) and a Belgian one (EUR). */
    private function setup_(): array
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $us = Company::create(['name' => 'Demo US Audio Inc', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en', 'email' => 'po@usaudio.example']);
        $be = Company::create(['name' => 'Demo Pro Supply BV', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'nl', 'email' => 'sales@dps.example']);
        $this->actingAs($admin)->post('/sales-products', ['brand' => 'Shure', 'type' => 'SM58-LCE', 'description' => 'Mic', 'category' => 'Audio', 'unit' => 'piece', 'list_price' => '119', 'stock_qty' => 0])->assertRedirect();
        $item = SalesItem::firstOrFail();
        $this->actingAs($admin)->post('/sales-products/'.$item->id.'/suppliers', ['company_id' => $us->id, 'discount' => '30', 'lead_days' => 10, 'preferred' => '1']);
        $this->actingAs($admin)->post('/sales-products/'.$item->id.'/suppliers', ['company_id' => $be->id, 'discount' => '25', 'lead_days' => 3]);

        return [$admin, $item, $us, $be];
    }

    public function test_cluk_collects_and_the_us_supplier_quotes_in_dollars(): void
    {
        Http::fake([ExchangeRates::URL => Http::response(self::ECB)]);
        [$admin, $item, $us, $be] = $this->setup_();

        // A request where we organise the transport: the suppliers are asked for an ex-works price and a ready date.
        $this->actingAs($admin)->post('/purchasing/requests', ['qty' => [$item->id => 10], 'purpose' => [$item->id => 'sales'], 'delivery' => 'cluk', 'send' => '1'])->assertRedirect();
        $pq = PurchaseRequest::firstOrFail();
        $this->assertSame('cluk', $pq->delivery);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'po@usaudio.example'
            && str_contains($n->intro[0], 'pick-up date') && str_contains($n->intro[0], 'dimensions (L × W × H) and weight') && in_array('Organised by CLUK: we collect the goods at your address', $n->details, true));
        $this->actingAs($admin)->get('/purchasing/requests/'.$pq->id.'/print')->assertOk()->assertSee('ex works')->assertSee('the pick-up date')->assertDontSee('Deliver to');

        // US price 90.00 USD (= € 83.33 at 1.08), Belgian price € 85.00: the US one is cheapest.
        $line = $pq->lines()->firstOrFail();
        $this->actingAs($admin)->post('/purchasing/requests/'.$pq->id.'/replies', ['r' => [$line->id => [
            $us->id => ['cur' => 'USD', 'net' => '90.00', 'date' => today()->addDays(9)->toDateString()],
            $be->id => ['cur' => 'EUR', 'net' => '85.00', 'date' => today()->addDays(4)->toDateString()],
        ]]])->assertSessionHasNoErrors();
        $reply = $line->replies()->where('company_id', $us->id)->firstOrFail();
        $this->assertSame(['USD', 9000, 8333, 1.08, '2026-11-02'], [$reply->currency, $reply->foreign_cents, $reply->net_cents, $reply->rate, $reply->rate_date->toDateString()]);
        $this->assertSame($us->id, $line->fresh()->chosen_company_id);
        $this->actingAs($admin)->get('/purchasing/requests/'.$pq->id)->assertOk()->assertSee('value="90.00"', false)->assertSee('1 EUR = 1,08 USD');

        // Saving again a day later keeps the rate of the day the price was entered.
        Cache::flush();
        Http::fake([ExchangeRates::URL => Http::response(str_replace('1.0800', '1.2000', self::ECB))]);
        $this->actingAs($admin)->post('/purchasing/requests/'.$pq->id.'/replies', ['r' => [$line->id => [$us->id => ['cur' => 'USD', 'net' => '90.00', 'date' => today()->addDays(9)->toDateString()]]]]);
        $this->assertSame(8333, $reply->fresh()->net_cents);

        // The order: in dollars for the supplier, in euro for us; we collect.
        $this->actingAs($admin)->post('/purchasing/requests/'.$pq->id.'/replies', ['order' => '1'])->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $this->assertSame(['USD', 'cluk', 83330], [$po->currency, $po->delivery, $po->total_cents]);
        $this->assertSame(9000, $po->lines()->value('unit_foreign_cents'));
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'po@usaudio.example'
            && str_contains($n->subject, $po->number) && str_contains(implode(' ', $n->totals), 'US$900.00') && str_contains($n->rows[0][2], 'US$900.00'));
        $this->actingAs($admin)->get('/purchasing/orders/'.$po->id.'/print')->assertOk()->assertSee('US$900.00')->assertSee('Organised by CLUK')->assertSee('Pick-up date')->assertSee('dimensions L × W × H and weight')->assertDontSee('delivery date');
        $this->actingAs($admin)->get('/purchasing/orders/'.$po->id)->assertOk()->assertSee('€ 833,30', false)->assertSee('US$900.00');

        // Switching back to delivery by the supplier on the order.
        $this->actingAs($admin)->post('/purchasing/orders/'.$po->id.'/confirm', ['delivery' => 'supplier', 'their_ref' => 'SO-1'])->assertRedirect();
        $this->assertSame('supplier', $po->fresh()->delivery);
        $this->actingAs($admin)->get('/purchasing/orders/'.$po->id.'/print')->assertOk()->assertSee('Deliver to');
    }

    public function test_without_a_day_rate_the_price_must_be_given_in_euro(): void
    {
        Http::fake([ExchangeRates::URL => Http::response('down', 503)]);
        [$admin, $item, $us] = $this->setup_();
        // Who arranges the transport must be chosen: there is no default.
        $this->actingAs($admin)->post('/purchasing/requests', ['qty' => [$item->id => 2], 'purpose' => [$item->id => 'sales'], 'send' => '0'])->assertSessionHasErrors('delivery');
        $this->actingAs($admin)->post('/purchasing/requests', ['qty' => [$item->id => 2], 'purpose' => [$item->id => 'sales'], 'delivery' => 'supplier', 'send' => '0'])->assertRedirect();
        $pq = PurchaseRequest::firstOrFail();
        $this->assertSame('supplier', $pq->delivery);
        $line = $pq->lines()->firstOrFail();
        $this->actingAs($admin)->post('/purchasing/requests/'.$pq->id.'/replies', ['r' => [$line->id => [$us->id => ['cur' => 'USD', 'net' => '90']]]])
            ->assertSessionHasErrors('r');
        $this->assertSame(0, $line->replies()->count());
    }
}
