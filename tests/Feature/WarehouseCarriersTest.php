<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse\Carrier;
use App\Models\Warehouse\Move;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Light dollies and storage boxes: fill, move as one, pick with an order, and piece-by-piece at the return. */
class WarehouseCarriersTest extends TestCase
{
    use RefreshDatabase;

    private User $crew;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $this->crew = User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
    }

    private function scan(string $code)
    {
        return $this->actingAs($this->crew)->post('/w/scan', ['code' => $code]);
    }

    /** Three blinders on a rack; an order for two of them. */
    private function job(): array
    {
        $item = Item::create(['number' => 'LT-BLINDER', 'kind' => 'item', 'name' => 'Sol I Blinder', 'category' => 'Light', 'serial' => true, 'day_price_cents' => 1900]);
        $p = [];
        foreach ([1, 2, 3] as $i) {
            $p[$i] = Asset::create(['item_id' => $item->id, 'code' => 'LT-000'.$i, 'status' => Asset::AVAILABLE, 'location' => 'GA1B1']);
        }
        $company = Company::create(['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $order = Order::create(['number' => 'R-OC-2026-0200', 'status' => 'approved', 'company_id' => $company->id, 'title' => 'Blinder show', 'language' => 'en',
            'out_from' => now()->addHours(3), 'out_until' => now()->addDays(2), 'use_from' => today(), 'use_until' => today()->addDay(), 'vat_rate_bp' => 2100, 'net_cents' => 1]);
        $line = $order->lines()->create(['kind' => 'item', 'item_id' => $item->id, 'name' => 'Sol I Blinder', 'qty' => 2, 'amount_cents' => 1, 'position' => 0]);
        foreach ([1, 2] as $i) {
            OrderAsset::create(['order_id' => $order->id, 'order_line_id' => $line->id, 'asset_id' => $p[$i]->id]);
        }

        return [$p, $order];
    }

    public function test_the_dollies_and_boxes_exist_with_labels(): void
    {
        $this->assertSame(6, Carrier::where('kind', 'dolly')->count());
        $this->assertSame(22, Carrier::where('kind', 'box')->count());
        $this->assertTrue(Carrier::where('code', 'DOLLY-06')->exists() && Carrier::where('code', 'BOX-22')->exists());
        $this->actingAs($this->crew)->get('/warehouse/labels')->assertOk()->assertSee('DOLLY-01')->assertSee('Light dolly');
        $r = $this->actingAs($this->crew)->post('/warehouse/labels/locations', ['codes' => ['DOLLY-01', 'BOX-12']])->assertOk();
        $r->assertSee('Storage box BOX-12')->assertSee('Light dolly DOLLY-01')->assertSee('<path d="M', false);
    }

    public function test_fill_a_dolly_and_move_it_with_one_scan(): void
    {
        [$p] = $this->job();
        // Piece first, then the dolly: it goes in, and the dolly stays open.
        $this->scan('LT-0001');
        $this->scan('https://app.cluk.live/l/DOLLY-01')->assertSessionHasNoErrors();
        $dolly = Carrier::where('code', 'DOLLY-01')->first();
        $this->assertSame($dolly->id, $p[1]->fresh()->carrier_id);
        // More pieces go straight in.
        $this->scan('LT-0002')->assertSessionHasNoErrors();
        $this->scan('LT-0002')->assertSessionHasErrors('code'); // already in it
        $this->actingAs($this->crew)->get('/w/scan')->assertSee('LT-0001')->assertSee('LT-0002')->assertSee('2 pieces');
        // A place moves the dolly with everything in it.
        $this->scan('GA2B1')->assertSessionHasNoErrors();
        $this->assertSame('GA2B1', $dolly->fresh()->location);
        $this->assertSame(['GA2B1', 'GA2B1', 'GA1B1'], [$p[1]->fresh()->location, $p[2]->fresh()->location, $p[3]->fresh()->location]);
        $this->assertSame(2, Move::where('carrier_id', $dolly->id)->where('kind', 'move')->count());
        // Scanning the place shows the dolly.
        $this->scan('GA2B1');
        $this->actingAs($this->crew)->get('/w/scan')->assertSee('Light dolly DOLLY-01');
        // Moving one piece on its own takes it out of the dolly.
        $this->scan('LT-0002');
        $this->scan('GA3B1')->assertSessionHasNoErrors();
        $this->assertNull($p[2]->fresh()->carrier_id);
        // A dolly cannot go to cleaning.
        $this->scan('DOLLY-01');
        $this->scan('CLEAN')->assertSessionHasErrors('code');
    }

    public function test_pick_a_dolly_load_it_and_return_piece_by_piece(): void
    {
        [$p, $order] = $this->job();
        $dolly = Carrier::where('code', 'DOLLY-02')->first();
        // A dolly holding a piece that is not on the order cannot be picked.
        foreach (['LT-0001', 'LT-0002', 'LT-0003'] as $c) {
            $this->scan('DOLLY-02');
            $this->scan($c);
        }
        $this->actingAs($this->crew)->post('/w/scan/cancel');
        $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/scan', ['code' => 'DOLLY-02'])->assertSessionHasErrors('code');
        $this->scan('DOLLY-02');
        $this->actingAs($this->crew)->post('/w/carrier/out/'.$p[3]->id)->assertRedirect('/w/scan');
        $this->actingAs($this->crew)->post('/w/scan/cancel');
        // Now one scan picks both, and the dolly goes with the order.
        $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/scan', ['code' => 'DOLLY-02'])->assertSessionHasNoErrors();
        $this->assertSame(['DISPATCH', 'DISPATCH'], [$p[1]->fresh()->location, $p[2]->fresh()->location]);
        $this->assertSame($order->id, $dolly->fresh()->out_order_id);
        $this->actingAs($this->crew)->get('/w/pick/'.$order->id)->assertSee('Light dolly DOLLY-02');
        $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/load')->assertRedirect('/w');
        $this->assertSame('OUT', $dolly->fresh()->location);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']))->get(route('orders.doc', [$order, 'dn']))->assertOk()->assertSee('DOLLY-02');

        // Return: the dolly comes back empty; only scanned pieces count, and they go in the dolly scanned before.
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'DOLLY-02'])->assertSessionHasNoErrors();
        $this->assertSame(['INBOUND', null], [$dolly->fresh()->location, $dolly->fresh()->out_order_id]);
        $this->assertSame(0, $dolly->assets()->count());
        $this->assertSame(Asset::OUT, $p[1]->fresh()->status);
        $box = Carrier::where('code', 'BOX-05')->first();
        $box->update(['out_order_id' => $order->id, 'location' => 'OUT']);
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'LT-0001'])->assertSessionHasNoErrors();
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'BOX-05']);
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'LT-0002'])->assertSessionHasNoErrors();
        $this->assertSame($dolly->id, $p[1]->fresh()->carrier_id);
        $this->assertSame($box->id, $p[2]->fresh()->carrier_id); // pieces may change dolly
        $this->assertSame([Asset::CHECK, 'INBOUND'], [$p[2]->fresh()->status, $p[2]->fresh()->location]);
        $this->assertNotNull($order->fresh()->returned_at);
        // Moving a piece on to cleaning takes it out of its box.
        $this->scan('LT-0002');
        $this->scan('CLEAN');
        $this->assertNull($p[2]->fresh()->carrier_id);
    }

    public function test_only_stored_pieces_go_in_a_dolly(): void
    {
        [$p] = $this->job();
        $p[1]->update(['status' => Asset::CHECK, 'location' => 'INBOUND']);
        $this->scan('BOX-01');
        $this->scan('LT-0001')->assertSessionHasErrors('code');
        $this->assertNull($p[1]->fresh()->carrier_id);
    }
}
