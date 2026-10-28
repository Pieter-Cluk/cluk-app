<?php

namespace Tests\Feature;

use App\Models\ProductSales\SalesItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Website\SalesCatalogue;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_list_shows_if_a_product_is_online(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $noPhoto = SalesItem::create(['number' => 'S-100001', 'type' => 'SM58', 'brand' => 'Shure', 'unit' => 'piece', 'list_price_cents' => 11900, 'active' => true]);

        $s = SalesCatalogue::status($noPhoto);
        $this->assertSame('partial', $s['level']);
        $this->assertSame(['no_menu', 'no_photo'], $s['reasons']);
        config(['website.only_ticked' => true]);
        $this->assertSame(['off', ['not_ticked']], array_values(SalesCatalogue::status($noPhoto->fresh())));
        config(['website.only_ticked' => false]);

        $this->actingAs($admin, 'web')->get(route('psales.items', ['item' => $noPhoto->id]))->assertOk()
            ->assertSee('web-partial', false)->assertSee('Online, needs attention')->assertSee('no photo')->assertSee('Open on the website');
        $this->actingAs($admin, 'web')->get(route('psales.items', ['web' => 'partial']))->assertOk()->assertSee('S-100001');
        $this->actingAs($admin, 'web')->get(route('psales.items', ['web' => 'online']))->assertOk()->assertDontSee('S-100001');
        $this->actingAs($admin, 'web')->get(route('psales.items', ['web' => 'off']))->assertOk()->assertDontSee('S-100001');
    }
}
