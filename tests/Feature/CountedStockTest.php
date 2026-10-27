<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Counted items: one QR for the type, deliveries added from the office, and serial items switched back to counted. */
class CountedStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
    }

    private function softsteels(int $pieces): Item
    {
        $item = Item::create(['number' => 'RIG-SOFTSTEEL', 'kind' => 'item', 'name' => 'Softsteel 1 m', 'serial' => true]);
        for ($i = 1; $i <= $pieces; $i++) {
            $item->assets()->create(['code' => sprintf('SS-%04d', $i), 'status' => Asset::AVAILABLE, 'location' => 'GA1B1']);
        }

        return $item;
    }

    public function test_a_delivery_is_added_to_the_stock_of_a_counted_item(): void
    {
        $admin = $this->admin();
        $item = Item::create(['number' => 'RIG-CLAMP', 'kind' => 'item', 'name' => 'Clamp', 'serial' => false, 'stock_qty' => 10]);

        $this->actingAs($admin, 'web')->get('/dry-hire/items/'.$item->id)->assertOk()->assertSee('Delivery in')->assertSee('Print QR label');
        $this->actingAs($admin, 'web')->post('/dry-hire/items/'.$item->id.'/add-stock', ['qty' => 20, 'note' => 'Prolyte delivery'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame(30, $item->fresh()->stock_qty);
        $this->assertSame(0, Asset::count()); // no QR per piece
        $log = AuditLog::where('action', 'equipment.stock_added')->firstOrFail();
        $this->assertSame(20, $log->data['added']);

        $this->actingAs($admin, 'web')->post('/dry-hire/items/'.$item->id.'/add-stock', ['qty' => 0])->assertSessionHasErrors('qty');
    }

    public function test_a_serial_item_is_not_given_extra_stock(): void
    {
        $item = $this->softsteels(1);
        $this->actingAs($this->admin(), 'web')->post('/dry-hire/items/'.$item->id.'/add-stock', ['qty' => 5])->assertNotFound();
    }

    public function test_softsteels_with_a_qr_each_become_one_counted_type(): void
    {
        $admin = $this->admin();
        $item = $this->softsteels(20);
        $item->assets()->create(['code' => 'SS-LABEL', 'status' => Asset::LABEL]); // printed in advance, never found

        $this->actingAs($admin, 'web')->get('/dry-hire/items/'.$item->id)->assertOk()->assertSee('Switch to one QR for the type');
        $this->actingAs($admin, 'web')->post('/dry-hire/items/'.$item->id.'/to-counted')->assertRedirect()->assertSessionHas('status');

        $item->refresh();
        $this->assertFalse((bool) $item->serial);
        $this->assertSame(20, $item->stock_qty);
        $this->assertSame(0, Asset::count());
        $this->assertTrue(AuditLog::where('action', 'equipment.converted_counted')->exists());
        $this->actingAs($admin, 'web')->get('/dry-hire/items/'.$item->id)->assertOk()->assertSee('Delivery in');
    }

    public function test_switching_is_refused_while_a_piece_is_out_or_was_on_an_order(): void
    {
        $admin = $this->admin();
        $item = $this->softsteels(3);
        Asset::where('code', 'SS-0002')->update(['status' => Asset::OUT]);
        $this->actingAs($admin, 'web')->post('/dry-hire/items/'.$item->id.'/to-counted')->assertSessionHas('error');
        $this->assertTrue((bool) $item->fresh()->serial);
        $this->assertSame(3, Asset::count());

        Asset::where('code', 'SS-0002')->update(['status' => Asset::AVAILABLE]);
        DB::table('warehouse_checks')->insert(['asset_id' => Asset::where('code', 'SS-0003')->value('id')] + $this->checkRow());
        $this->actingAs($admin, 'web')->post('/dry-hire/items/'.$item->id.'/to-counted')->assertSessionHas('error');
        $this->assertSame(3, Asset::count());
    }

    public function test_a_new_item_asks_how_it_is_tracked(): void
    {
        $this->actingAs($this->admin(), 'web')->get('/dry-hire/items/new')->assertOk()
            ->assertSee('Counted · one QR for the type')->assertSee('Per piece · every piece its own QR');
    }

    private function checkRow(): array
    {
        $cols = DB::getSchemaBuilder()->getColumns('warehouse_checks');
        $row = [];
        foreach ($cols as $c) {
            if (in_array($c['name'], ['id', 'asset_id'], true) || $c['nullable'] || $c['default'] !== null) {
                continue;
            }
            $row[$c['name']] = str_contains($c['type_name'], 'int') ? 1 : (str_contains($c['type_name'], 'date') || str_contains($c['type_name'], 'time') ? now() : 'x');
        }

        return $row;
    }
}
