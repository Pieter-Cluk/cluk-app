<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\Check;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private User $crew;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $this->crew = $this->as('warehouse');
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    /** A Spiider with a hook in its kit, two pieces on a rack, an order with one piece and 4 safety cables. */
    private function job(): array
    {
        $hook = Item::create(['number' => 'ACC-HOOK', 'kind' => 'item', 'name' => 'Hook', 'category' => 'Rigging', 'serial' => false, 'stock_qty' => 50, 'day_price_cents' => 100, 'catalog_price_cents' => 1200]);
        $safety = Item::create(['number' => 'ACC-SAFETY', 'kind' => 'item', 'name' => 'Safety cable', 'category' => 'Rigging', 'serial' => false, 'stock_qty' => 40, 'day_price_cents' => 100, 'catalog_price_cents' => 1800]);
        $spiider = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'category' => 'Light', 'serial' => true, 'day_price_cents' => 6500]);
        $spiider->children()->attach($hook->id, ['qty' => 1, 'role' => 'kit']);
        $a = Asset::create(['item_id' => $spiider->id, 'code' => 'LT-0142', 'status' => Asset::AVAILABLE, 'location' => 'GA1B1']);
        $b = Asset::create(['item_id' => $spiider->id, 'code' => 'LT-0143', 'status' => Asset::AVAILABLE, 'location' => 'GA1B1']);
        $company = Company::create(['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $order = Order::create(['number' => 'R-OC-2026-0102', 'status' => 'approved', 'company_id' => $company->id, 'title' => 'Light rig festival', 'language' => 'nl',
            'out_from' => now()->addHours(3), 'out_until' => now()->addDays(2), 'use_from' => today(), 'use_until' => today()->addDay(), 'vat_rate_bp' => 2100, 'net_cents' => 1]);
        $l1 = $order->lines()->create(['kind' => 'item', 'item_id' => $spiider->id, 'name' => 'Robe Spiider', 'qty' => 1, 'amount_cents' => 1, 'position' => 0]);
        $l2 = $order->lines()->create(['kind' => 'item', 'item_id' => $safety->id, 'name' => 'Safety cable', 'qty' => 4, 'own_qty' => 4, 'amount_cents' => 1, 'position' => 1]);
        OrderAsset::create(['order_id' => $order->id, 'order_line_id' => $l1->id, 'asset_id' => $a->id]);

        return compact('hook', 'safety', 'spiider', 'a', 'b', 'order', 'l1', 'l2');
    }

    private function scan(string $code)
    {
        return $this->actingAs($this->crew)->post('/w/scan', ['code' => $code]);
    }

    public function test_the_warehouse_is_set_up_with_zones_and_452_positions(): void
    {
        $this->assertSame(452, Position::count());
        $this->assertSame(396, Position::where('floor', 'G')->count());
        $this->assertTrue(Position::where('code', 'GA1A2')->where('kind', 'pallet')->exists());
        $this->assertFalse(Position::where('code', 'GA1A3')->exists());
        $this->assertTrue(Position::where('code', 'MI1A2')->exists());
        $this->assertSame(['CLEAN'], Zone::byCode('INBOUND')->next);
    }

    public function test_pick_and_load_only_what_is_on_the_order(): void
    {
        $j = $this->job();
        $this->actingAs($this->crew)->get('/w')->assertOk()->assertSee('Light rig festival');
        $this->actingAs($this->crew)->post('/w/pick/'.$j['order']->id.'/scan', ['code' => 'https://app.cluk.live/a/LT-0143'])->assertSessionHasErrors('code');
        $this->actingAs($this->crew)->post('/w/pick/'.$j['order']->id.'/scan', ['code' => 'https://app.cluk.live/a/LT-0142'])->assertSessionHasNoErrors();
        $this->assertSame('DISPATCH', $j['a']->fresh()->location);
        $this->actingAs($this->crew)->post('/w/pick/'.$j['order']->id.'/load')->assertSessionHasErrors('code');
        $this->actingAs($this->crew)->post('/w/pick/'.$j['order']->id.'/lines/'.$j['l2']->id, ['field' => 'picked_qty', 'delta' => 10]);
        $this->assertSame(4, $j['l2']->fresh()->picked_qty);
        $this->actingAs($this->crew)->post('/w/pick/'.$j['order']->id.'/load')->assertRedirect('/w');
        $this->assertSame(Asset::OUT, $j['a']->fresh()->status);
        $this->assertNotNull($j['order']->fresh()->loaded_at);
        // Scanning a piece that is out into a rack: it comes back through Inbound first.
        $this->scan('LT-0142');
        $this->scan('GA2B1')->assertSessionHasErrors('code');
    }

    public function test_the_return_route_with_cleaning_check_repair_and_release(): void
    {
        $j = $this->job();
        $o = $j['order'];
        $this->actingAs($this->crew)->post('/w/pick/'.$o->id.'/scan', ['code' => 'LT-0142']);
        $this->actingAs($this->crew)->post('/w/pick/'.$o->id.'/lines/'.$j['l2']->id, ['field' => 'picked_qty', 'delta' => 4]);
        $this->actingAs($this->crew)->post('/w/pick/'.$o->id.'/load');

        // Back: into Inbound, not rentable.
        $this->actingAs($this->crew)->post('/w/return/'.$o->id.'/scan', ['code' => 'LT-0142'])->assertSessionHasNoErrors();
        $a = $j['a']->fresh();
        $this->assertSame([Asset::CHECK, 'INBOUND'], [$a->status, $a->location]);

        // Inbound → Pre-check is not allowed: cleaning first.
        $this->scan('LT-0142');
        $this->scan('PRE-LIGHT')->assertSessionHasErrors('code');
        $this->scan('https://app.cluk.live/l/CLEAN')->assertRedirect('/w/clean/'.$a->id);
        $this->scan('LT-0142')->assertRedirect('/w/clean/'.$a->id);

        // Stage 3 needs a photo.
        $this->actingAs($this->crew)->post('/w/clean/'.$a->id, ['dirt' => 3])->assertSessionHasErrors('photos');
        $this->actingAs($this->crew)->post('/w/clean/'.$a->id, ['dirt' => 3, 'photos' => [UploadedFile::fake()->image('dirty.jpg', 800, 600)]])->assertRedirect('/w/scan');
        // Still holding it: a lighting fixture does not go to Pre-check Sound.
        $this->scan('PRE-SOUND')->assertSessionHasErrors('code');
        $this->scan('LT-0142');
        $this->scan('PRE-LIGHT')->assertSessionHasNoErrors();
        $this->scan('LT-0142');
        $this->scan('LIGHT-ST')->assertRedirect('/w/check/'.$a->id);
        $this->scan('LT-0142')->assertRedirect('/w/check/'.$a->id);

        // The check: hook missing, zoom fails, with a photo → Repair.
        $tests = ['power' => 'ok', 'pan_tilt' => 'ok', 'leds' => 'ok', 'zoom' => 'fail', 'dmx' => 'ok'];
        $this->actingAs($this->crew)->post('/w/check/'.$a->id, ['tests' => ['power' => 'ok']])->assertSessionHasErrors('tests');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id, ['tests' => $tests, 'missing' => [$j['hook']->id => 1]])->assertSessionHasErrors('photos');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id, ['tests' => $tests, 'missing' => [$j['hook']->id => 1], 'damage_type' => ['lens'], 'damage_note' => ['scratch top'],
            'photos' => [UploadedFile::fake()->image('zoom.jpg')]])->assertRedirect('/w/scan');
        $check = Check::where('asset_id', $a->id)->firstOrFail();
        $this->assertSame('repair', $check->result);
        $this->assertSame(2, count($check->photos));
        $this->assertSame(49, $j['hook']->fresh()->stock_qty);
        $charges = Charge::where('order_id', $o->id)->orderBy('id')->get();
        $this->assertSame(['missing', 'cleaning', 'damage'], $charges->pluck('kind')->all());
        $this->assertSame([1200, 3000, null], $charges->pluck('amount_cents')->all());
        $this->assertStringContainsString('Ontbreekt · Hook', $charges[0]->label); // customer's language

        $this->scan('POST-CHECK')->assertSessionHasErrors('code');
        $this->scan('LT-0142');
        $this->scan('REPAIR')->assertSessionHasNoErrors();
        $this->assertSame(Asset::REPAIR, $a->fresh()->status);
        $this->scan('LT-0142');
        $this->scan('PRE-LIGHT')->assertSessionHasErrors('code');

        $this->actingAs($this->crew)->post('/warehouse/assets/'.$a->id.'/release')->assertForbidden();
        $this->actingAs($this->as('manager'))->post('/warehouse/assets/'.$a->id.'/release')->assertRedirect();
        $this->scan('LT-0142');
        $this->scan('PRE-LIGHT')->assertSessionHasNoErrors();
        $this->assertSame(Asset::CHECK, $a->fresh()->status);

        // Second round: clean OK this time → post-check → rack: rentable again.
        $this->scan('LT-0142');
        $this->scan('AUDIO-ST')->assertSessionHasErrors('code'); // a light goes to Light street, not Audio street
        $this->scan('LT-0142');
        $this->scan('LIGHT-ST');
        $this->actingAs($this->crew)->post('/w/check/'.$a->id, ['tests' => array_map(fn () => 'ok', $tests), 'reset' => '1'])->assertSessionHasNoErrors();
        $this->scan('POST-CHECK')->assertSessionHasNoErrors();
        $this->scan('LT-0142');
        $this->scan('GA2C3')->assertSessionHasNoErrors();
        $this->assertSame([Asset::AVAILABLE, 'GA2C3'], [$a->fresh()->status, $a->fresh()->location]);

        // Counted cables: 3 of 4 came back; closing writes one off and charges it.
        $this->actingAs($this->crew)->post('/w/pick/'.$o->id.'/lines/'.$j['l2']->id, ['field' => 'returned_qty', 'delta' => 3]);
        $this->actingAs($this->crew)->post('/w/return/'.$o->id.'/close')->assertRedirect('/w');
        $this->assertSame(39, $j['safety']->fresh()->stock_qty);
        $this->assertNotNull($o->fresh()->returned_at);
        $this->assertSame(1800, Charge::where('item_id', $j['safety']->id)->value('amount_cents'));
    }

    public function test_the_office_prices_waives_and_approves_charges_into_a_proforma(): void
    {
        $j = $this->job();
        $o = $j['order'];
        $c1 = Charge::create(['order_id' => $o->id, 'kind' => 'cleaning', 'label' => 'Opkuis · stap 3 · LT-0142', 'amount_cents' => 3000, 'original_cents' => 3000]);
        $c2 = Charge::create(['order_id' => $o->id, 'kind' => 'damage', 'label' => 'Schade · Lens gekrast · LT-0142', 'amount_cents' => null]);
        $c3 = Charge::create(['order_id' => $o->id, 'kind' => 'missing', 'label' => 'Ontbreekt · Hook', 'amount_cents' => 1200, 'original_cents' => 1200]);

        $this->actingAs($this->crew)->post('/warehouse/charges/'.$o->id.'/approve')->assertForbidden();
        $sales = $this->as('sales');
        $this->actingAs($sales)->post('/warehouse/charges/'.$o->id.'/approve')->assertSessionHasErrors('amount');
        $this->actingAs($sales)->post('/warehouse/charges/line/'.$c2->id, ['action' => 'price', 'amount' => '85.50']);
        $this->actingAs($sales)->post('/warehouse/charges/line/'.$c1->id, ['action' => 'half']);
        $this->actingAs($sales)->post('/warehouse/charges/line/'.$c3->id, ['action' => 'waive']);
        $this->assertSame([1500, 8550, 'waived'], [$c1->fresh()->amount_cents, $c2->fresh()->amount_cents, $c3->fresh()->status]);
        $this->actingAs($sales)->post('/warehouse/charges/'.$o->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('action', 'warehouse.charges_approved')->count());

        $this->actingAs($sales)->get('/warehouse/charges/'.$o->id.'/proforma')->assertOk()
            ->assertSee('R-CH-2026-0102')->assertSee('100,50')->assertSee('121,61')->assertDontSee('Hook')->assertSee('RETOURKOSTEN', false);
    }

    public function test_reading_is_not_moving(): void
    {
        $j = $this->job();
        $reader = $this->as('read-only');
        $this->actingAs($reader)->get('/w/scan')->assertOk();
        $this->actingAs($reader)->post('/w/scan', ['code' => 'LT-0142'])->assertForbidden();
        $this->actingAs($reader)->get('/settings/warehouse-locations')->assertForbidden();
    }

    public function test_locations_settings_layers_and_labels(): void
    {
        $admin = $this->as('admin');
        $this->actingAs($admin)->post('/settings/warehouse-locations', ['code' => 'rack-x', 'type' => 'storage', 'next' => ['DISPATCH'], 'active' => '1'])->assertRedirect();
        $this->assertTrue(Zone::where('code', 'RACK-X')->exists());
        $clean = Zone::byCode('CLEAN');
        $this->actingAs($admin)->put('/settings/warehouse-locations/zones/'.$clean->id, ['name' => 'Wasruimte', 'type' => 'cleaning', 'categories' => '', 'next' => ['PRE-LIGHT'], 'active' => '1'])->assertRedirect();
        $this->assertSame(['PRE-LIGHT'], $clean->fresh()->next);

        $p = Position::byCode('GB2C1');
        $this->actingAs($admin)->post('/settings/warehouse-locations/positions/'.$p->id.'/switch')->assertRedirect();
        $this->assertFalse(Position::where('code', 'GB2C3')->exists());
        $this->assertSame('pallet', $p->fresh()->kind);
        $this->actingAs($admin)->post('/settings/warehouse-locations/positions/'.$p->id.'/switch');
        $this->assertTrue(Position::where('code', 'GB2C3')->where('kind', 'shelf')->exists());
        $this->actingAs($admin)->post('/settings/warehouse-locations/positions/'.Position::byCode('GB2A1')->id.'/switch')->assertStatus(409);

        $this->actingAs($admin)->get('/settings/warehouse-locations/labels?rack=GA1')->assertOk()->assertSee('GA1D3')->assertSee('GA1A1');
        $this->actingAs($admin)->get('/settings/warehouse-locations/labels?zone=CLEAN')->assertOk()->assertSee('Wasruimte');
        $this->actingAs($admin)->get('/l/ga1a1')->assertRedirect('/w/scan?scanned=GA1A1');
    }

    public function test_pages_in_every_language(): void
    {
        $j = $this->job();
        $admin = $this->as('admin');
        $a = $j['a'];
        Charge::create(['order_id' => $j['order']->id, 'kind' => 'damage', 'label' => 'Damage', 'amount_cents' => null]);
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            $this->actingAs($admin->fresh());
            $this->post('/w/scan', ['code' => 'LT-0142']);
            $urls = ['/w', '/w/scan', '/w/pick/'.$j['order']->id, '/w/clean/'.$a->id, '/w/check/'.$a->id, '/warehouse', '/warehouse?zone=INBOUND', '/warehouse?zone=RACKS',
                '/warehouse/charges/'.$j['order']->id, '/settings/warehouse-locations', '/settings/warehouse-locations?zone=CLEAN', '/settings/warehouse-locations?zone=new',
                '/settings/warehouse-locations/positions', '/settings/warehouse-locations/positions?floor=M&row=I', '/dry-hire/assets/'.$a->id];
            foreach ($urls as $url) {
                $html = $this->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(warehouse|equipment|docs)\.[a-z_]+(\.[A-Za-z_\-]+)*\b/', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html)), "{$url} ({$locale})");
            }
            $this->post('/w/scan/cancel');
        }
        $this->post('/w/pick/'.$j['order']->id.'/scan', ['code' => 'LT-0142']);
        $this->post('/w/pick/'.$j['order']->id.'/lines/'.$j['l2']->id, ['field' => 'picked_qty', 'delta' => 4]);
        $this->post('/w/pick/'.$j['order']->id.'/load');
        $this->get('/w/return/'.$j['order']->id)->assertOk()->assertSee('LT-0142');
    }
}
