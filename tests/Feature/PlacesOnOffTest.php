<?php

namespace Tests\Feature;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse\Position;
use App\Services\Warehouse\Moves;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Warehouse places (shelf and pallet) can be switched off and on, alone or per layer or rack, only when empty. */
class PlacesOnOffTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
    }

    public function test_an_empty_place_layer_and_rack_go_off_and_on(): void
    {
        $admin = $this->admin();
        $p = Position::where('code', 'GA1B1')->firstOrFail();
        $url = '/settings/warehouse-locations/positions/'.$p->id.'/active';

        $this->actingAs($admin, 'web')->post($url, ['scope' => 'spot', 'active' => 0])->assertRedirect()->assertSessionHas('status');
        $this->assertFalse($p->fresh()->active);
        $this->assertNull(Moves::resolve('GA1B1')['model'] ?? null);
        $this->actingAs($admin, 'web')->get('/settings/warehouse-locations/positions?floor=G&row=A&p=GA1B1')->assertOk()->assertSee('wp-inactive')->assertSee('Switch GA1B1 on');

        $this->actingAs($admin, 'web')->post($url, ['scope' => 'layer', 'active' => 0])->assertRedirect();
        $this->assertSame(0, Position::where('code', 'like', 'GA1B%')->where('active', true)->count());
        $this->actingAs($admin, 'web')->post($url, ['scope' => 'rack', 'active' => 1])->assertRedirect();
        $this->assertSame(0, Position::where('code', 'like', 'GA1%')->where('active', false)->count());
    }

    public function test_a_place_with_something_on_it_stays_on(): void
    {
        $admin = $this->admin();
        $p = Position::where('code', 'GA1B1')->firstOrFail();
        $item = Item::create(['number' => 'LT-X', 'kind' => 'item', 'name' => 'X', 'serial' => true]);
        $item->assets()->create(['code' => 'LT-1', 'status' => Asset::AVAILABLE, 'location' => 'GA1B2']);
        Item::create(['number' => 'RIG-CL', 'kind' => 'item', 'name' => 'Clamp', 'serial' => false, 'stock_qty' => 5, 'position_code' => 'GA1C1']);
        $url = '/settings/warehouse-locations/positions/'.$p->id.'/active';

        $this->actingAs($admin, 'web')->post($url, ['scope' => 'spot', 'active' => 0])->assertSessionHasNoErrors(); // GA1B1 itself is empty
        $this->actingAs($admin, 'web')->post($url, ['scope' => 'layer', 'active' => 0])->assertSessionHasErrors('active');
        $this->assertTrue(Position::where('code', 'GA1B2')->value('active'));
        $this->actingAs($admin, 'web')->post($url, ['scope' => 'rack', 'active' => 1]);
        Asset::query()->update(['location' => 'INBOUND']);
        $this->actingAs($admin, 'web')->post($url, ['scope' => 'rack', 'active' => 0])->assertSessionHasErrors('active'); // the clamps are kept on GA1C1
        $this->assertTrue(Position::where('code', 'GA1C1')->value('active'));
    }
}
