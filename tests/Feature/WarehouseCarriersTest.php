<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\Role;
use App\Models\User;
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
        (require database_path('migrations/2026_10_23_001600_dollies_and_boxes_as_case_items.php'))->seed();
    }

    private static function case(string $code): Asset
    {
        return Asset::where('code', $code)->firstOrFail();
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

    public function test_the_dollies_and_boxes_are_case_items_with_small_labels(): void
    {
        $dolly = Item::where('number', 'CASLIGHTDOLLY')->firstOrFail();
        $box = Item::where('number', 'CASSTORAGEBOX')->firstOrFail();
        $this->assertSame(['case', 0, false], [$dolly->kind, $dolly->day_price_cents, $dolly->in_search]);
        $this->assertSame(6, $dolly->assets()->count());
        $this->assertSame(22, $box->assets()->count());
        $this->assertTrue(self::case('DOLLY-06')->isContainer());
        // In the Cases tab; more pieces get the next code.
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $this->actingAs($admin)->get(route('equipment.index', ['kind' => 'case']))->assertOk()->assertSee('Light dolly (meat rack)')->assertSee('Storage box');
        $this->actingAs($admin)->post(route('equipment.assets.store', $dolly), ['count' => 2])->assertSessionHasNoErrors();
        $this->assertTrue(Asset::where('code', 'DOLLY-07')->exists() && Asset::where('code', 'DOLLY-08')->exists());
        // Small square labels like any piece.
        $this->actingAs($this->crew)->get('/warehouse/labels?tab=items&q=DOLLY')->assertOk()->assertSee('DOLLY-01');
        $r = $this->actingAs($this->crew)->post('/warehouse/labels/items', ['ids' => [self::case('BOX-12')->id]])->assertOk();
        $r->assertSee('BOX-12')->assertSee('size: 25mm 25mm', false);
    }

    public function test_fill_a_dolly_and_move_it_with_one_scan(): void
    {
        [$p] = $this->job();
        // Piece first, then the dolly: it goes in, and the dolly stays open.
        $this->scan('LT-0001');
        $this->scan('https://app.cluk.live/l/DOLLY-01')->assertSessionHasNoErrors();
        $dolly = self::case('DOLLY-01');
        $this->assertSame($dolly->id, $p[1]->fresh()->case_id);
        // More pieces go straight in.
        $this->scan('LT-0002')->assertSessionHasNoErrors();
        $this->scan('LT-0002')->assertSessionHasErrors('code'); // already in it
        $this->actingAs($this->crew)->get('/w/scan')->assertSee('LT-0001')->assertSee('LT-0002')->assertSee('2 pieces');
        // A place moves the dolly with everything in it.
        $this->scan('GA2B1')->assertSessionHasNoErrors();
        $this->assertSame('GA2B1', $dolly->fresh()->location);
        $this->assertSame(['GA2B1', 'GA2B1', 'GA1B1'], [$p[1]->fresh()->location, $p[2]->fresh()->location, $p[3]->fresh()->location]);
        $this->assertSame(2, Move::where('case_id', $dolly->id)->where('kind', 'move')->count());
        // Scanning the place shows the dolly.
        $this->scan('GA2B1');
        $this->actingAs($this->crew)->get('/w/scan')->assertSee('DOLLY-01');
        // Moving one piece on its own takes it out of the dolly.
        $this->scan('LT-0002');
        $this->scan('GA3B1')->assertSessionHasNoErrors();
        $this->assertNull($p[2]->fresh()->case_id);
        // A dolly cannot go to cleaning.
        $this->scan('DOLLY-01');
        $this->scan('CLEAN')->assertSessionHasErrors('code');
    }

    public function test_pick_a_dolly_load_it_and_return_piece_by_piece(): void
    {
        [$p, $order] = $this->job();
        $dolly = self::case('DOLLY-02');
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
        $this->actingAs($this->crew)->get('/w/pick/'.$order->id)->assertSee('Light dolly (meat rack) DOLLY-02');
        $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/load')->assertRedirect('/w');
        $this->assertSame('OUT', $dolly->fresh()->location);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']))->get(route('orders.doc', [$order, 'dn']))->assertOk()->assertSee('DOLLY-02');

        // Return: the dolly comes back empty; only scanned pieces count, and they go in the dolly scanned before.
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'DOLLY-02'])->assertSessionHasNoErrors();
        $this->assertSame(['INBOUND', null], [$dolly->fresh()->location, $dolly->fresh()->out_order_id]);
        $this->assertSame(0, $dolly->packed()->count());
        $this->assertSame(Asset::OUT, $p[1]->fresh()->status);
        $box = self::case('BOX-05');
        $box->update(['out_order_id' => $order->id, 'location' => 'OUT', 'status' => Asset::OUT]);
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'LT-0001'])->assertSessionHasNoErrors();
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'BOX-05']);
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'LT-0002'])->assertSessionHasNoErrors();
        $this->assertSame($dolly->id, $p[1]->fresh()->case_id);
        $this->assertSame($box->id, $p[2]->fresh()->case_id); // pieces may change dolly
        $this->assertSame([Asset::CHECK, 'INBOUND'], [$p[2]->fresh()->status, $p[2]->fresh()->location]);
        $this->assertNotNull($order->fresh()->returned_at);
        // Moving a piece on to cleaning takes it out of its box.
        $this->scan('LT-0002');
        $this->scan('CLEAN');
        $this->assertNull($p[2]->fresh()->case_id);
    }

    public function test_only_stored_pieces_go_in_a_dolly(): void
    {
        [$p] = $this->job();
        $p[1]->update(['status' => Asset::CHECK, 'location' => 'INBOUND']);
        $this->scan('BOX-01');
        $this->scan('LT-0001')->assertSessionHasErrors('code');
        $this->assertNull($p[1]->fresh()->case_id);
    }

    public function test_labels_for_pieces_still_to_find_count_only_once_scanned(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $cable = Item::create(['number' => 'POWCABTRUECONCEE', 'kind' => 'item', 'name' => 'Power cable Truecon – CEE 3-pole 1.5 m', 'serial' => true]);
        $this->actingAs($admin)->get(route('equipment.show', $cable))->assertOk()->assertSee('Labels for pieces still to find');
        $this->actingAs($admin)->post(route('equipment.assets.reserve', $cable), ['labels' => 5])->assertSessionHas('print');
        $this->assertSame(5, $cable->assets()->where('status', Asset::LABEL)->count());
        $this->assertSame(0, $cable->fresh()->totalQty());
        $this->assertSame(0, $cable->fresh()->availableQty());

        // Found one: scan its label, it is in stock and waits for its place.
        $code = $cable->assets()->orderBy('code')->value('code');
        $this->scan($code)->assertSessionHasNoErrors();
        $this->assertSame(1, $cable->fresh()->totalQty());
        $this->scan('GA1B1')->assertSessionHasNoErrors();
        $this->assertSame([Asset::AVAILABLE, 'GA1B1'], [self::case($code)->status, self::case($code)->location]);
        // Scanning it again does not add another one.
        $this->scan($code);
        $this->actingAs($this->crew)->post('/w/scan/cancel');
        $this->assertSame(1, $cable->fresh()->totalQty());

        // Found straight into an open box.
        $second = $cable->assets()->where('status', Asset::LABEL)->orderBy('code')->value('code');
        $this->scan('BOX-03');
        $this->scan($second)->assertSessionHasNoErrors();
        $this->assertSame(self::case('BOX-03')->id, self::case($second)->case_id);
        $this->assertSame(2, $cable->fresh()->totalQty());
    }

    public function test_add_and_take_out_buttons(): void
    {
        [$p] = $this->job();
        $this->scan('BOX-07');
        $this->actingAs($this->crew)->get('/w/scan')->assertSee('Add pieces')->assertSee('Take out pieces')->assertSee('Scan the pieces that go in');
        $this->scan('LT-0001');
        $this->scan('LT-0002');
        $this->assertSame(2, self::case('BOX-07')->packed()->count());
        // Switch to taking out: a scan removes the piece; one that is not in the box is refused.
        $this->actingAs($this->crew)->post('/w/carrier/mode', ['mode' => 'remove'])->assertRedirect('/w/scan');
        $this->actingAs($this->crew)->get('/w/scan')->assertSee('Scan the pieces you take out');
        $this->scan('LT-0001')->assertSessionHasNoErrors();
        $this->assertNull($p[1]->fresh()->case_id);
        $this->scan('LT-0003')->assertSessionHasErrors('code');
        $this->assertSame(1, self::case('BOX-07')->packed()->count());
        // A place still moves the whole box.
        $this->scan('GA2B1')->assertSessionHasNoErrors();
        $this->assertSame('GA2B1', $p[2]->fresh()->location);
    }

    public function test_counted_items_in_a_box_on_the_pick_list_and_back(): void
    {
        [$p, $order] = $this->job();
        $barndoor = Item::create(['number' => 'LIGACCELASOLIBARNDOOR', 'kind' => 'item', 'name' => 'Elation Sol I Barndoor', 'type' => 'Sol I Barndoor', 'serial' => false, 'stock_qty' => 3]);
        $label = 'https://app.cluk.live/i/LIGACCELASOLIBARNDOOR';
        // Without an open box: it is recognised and explained.
        $this->scan($label)->assertSessionHasNoErrors()->assertSessionHas('status');
        // Into a box: +1 per scan, never more than in stock.
        $this->scan('BOX-01');
        foreach ([1, 2, 3] as $i) {
            $this->scan($label)->assertSessionHasNoErrors();
        }
        $this->scan($label)->assertSessionHasErrors('code');
        $this->actingAs($this->crew)->get('/w/scan')->assertSee('3 ×')->assertSee('Sol I Barndoor');
        $this->actingAs($this->crew)->post('/w/carrier/mode', ['mode' => 'remove']);
        $this->scan($label)->assertSessionHasNoErrors();
        $this->assertSame(2, self::case('BOX-01')->counted()->value('qty'));
        $this->actingAs($this->crew)->post('/w/scan/cancel');

        // The order also has 2 barndoors: picking the box picks them.
        $line = $order->lines()->create(['kind' => 'item', 'item_id' => $barndoor->id, 'name' => 'Sol I Barndoor', 'qty' => 2, 'own_qty' => 2, 'amount_cents' => 1, 'position' => 1]);
        $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/scan', ['code' => 'BOX-01'])->assertSessionHasNoErrors();
        $this->assertSame(2, $line->fresh()->picked_qty);
        $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/scan', ['code' => $label])->assertSessionHasErrors('code'); // all picked

        // Scanning the label on the pick list of another job counts +1; at the return, +1 back.
        foreach (['LT-0001', 'LT-0002'] as $c) {
            $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/scan', ['code' => $c]);
        }
        $this->actingAs($this->crew)->post('/w/pick/'.$order->id.'/load')->assertRedirect('/w');
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => 'BOX-01'])->assertSessionHasNoErrors();
        $this->assertSame(0, self::case('BOX-01')->counted()->count());
        $this->actingAs($this->crew)->post('/w/return/'.$order->id.'/scan', ['code' => $label])->assertSessionHasNoErrors();
        $this->assertSame(1, $line->fresh()->returned_qty);
        $this->assertSame(1, self::case('BOX-01')->counted()->value('qty'));
    }
}
