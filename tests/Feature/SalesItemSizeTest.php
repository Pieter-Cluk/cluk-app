<?php

namespace Tests\Feature;

use App\Models\ProductSales\SalesItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sales product card: width, length and height entered in cm (stored in metres) and weight in kg. */
class SalesItemSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_size_and_weight_are_saved_and_shown_again(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $this->actingAs($admin, 'web')->post('/sales-products', ['brand' => 'Shure', 'type' => 'SM58', 'unit' => 'piece', 'list_price' => '119',
            'width_cm' => '12', 'length_cm' => '8.5', 'height_cm' => '4', 'weight_kg' => '0.35'])->assertSessionHasNoErrors();
        $it = SalesItem::where('type', 'SM58')->firstOrFail();
        $this->assertEqualsWithDelta(0.12, $it->width_m, 0.00001);
        $this->assertEqualsWithDelta(0.085, $it->length_m, 0.00001);
        $this->assertEqualsWithDelta(0.35, $it->weight_kg, 0.00001);

        $this->actingAs($admin, 'web')->get(route('psales.items', ['item' => $it->id]))->assertOk()->assertSee('name="length_cm"', false)->assertSee('value="8.5"', false);
        $this->actingAs($admin, 'web')->put('/sales-products/'.$it->id, ['type' => 'SM58', 'unit' => 'piece', 'list_price' => '119'])->assertSessionHasNoErrors();
        $this->assertNull($it->fresh()->width_m);
    }
}
