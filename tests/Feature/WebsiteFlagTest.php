<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\ProductSales\SalesItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteFlagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $locale = 'en'): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => $locale]);
    }

    public function test_dry_hire_items_are_off_the_website_until_ticked(): void
    {
        $u = $this->admin();
        $this->actingAs($u)->post('/dry-hire/items', ['number' => 'LT-A', 'kind' => 'item', 'name' => 'Robe Spiider', 'unit' => 'pcs', 'serial' => 1, 'day_price' => '65', 'active' => 1, 'subcategory_id' => self::sub('MOV')])->assertRedirect();
        $a = Item::where('number', 'LT-A')->firstOrFail();
        $this->assertFalse($a->on_website);

        $this->actingAs($u)->put('/dry-hire/items/'.$a->id, ['number' => 'LT-A', 'kind' => 'item', 'name' => 'Robe Spiider', 'unit' => 'pcs', 'serial' => 1, 'day_price' => '65', 'active' => 1, 'on_website' => 1, 'subcategory_id' => self::sub('MOV')])->assertRedirect();
        $this->assertTrue($a->fresh()->on_website);
        Item::create(['number' => 'LT-B', 'kind' => 'item', 'name' => 'Hidden one', 'serial' => false]);

        $this->actingAs($u)->get('/dry-hire?web=1')->assertOk()->assertSee('Robe Spiider')->assertDontSee('Hidden one');
        $this->actingAs($u)->get('/dry-hire/items/'.$a->id.'/edit')->assertOk()->assertSee('Show on the website');
    }

    public function test_sales_products_have_the_same_tick_in_four_languages(): void
    {
        $u = $this->admin();
        $this->actingAs($u)->post('/sales-products', ['brand' => 'Shure', 'type' => 'SM58', 'unit' => 'piece', 'list_price' => '119', 'on_website' => 1])->assertRedirect();
        $it = SalesItem::firstOrFail();
        $this->assertTrue($it->on_website);
        $this->actingAs($u)->put('/sales-products/'.$it->id, ['brand' => 'Shure', 'type' => 'SM58', 'unit' => 'piece', 'list_price' => '119', 'on_website' => 0])->assertRedirect();
        $this->assertFalse($it->fresh()->on_website);

        foreach (['en' => 'Show on the website', 'nl' => 'Tonen op de website', 'fr' => 'Afficher sur le site web', 'es' => 'Mostrar en la web'] as $loc => $t) {
            $u->update(['locale' => $loc]);
            $this->actingAs($u)->get('/sales-products?item='.$it->id)->assertOk()->assertSee($t);
        }
    }

    public function test_one_click_in_the_list_needs_the_extra_right(): void
    {
        $this->seed(RoleSeeder::class);
        $item = Item::create(['number' => 'LT-C', 'name' => 'Claypaky Sharpy', 'serial' => false]);
        $am = User::factory()->create(['role_id' => Role::where('slug', 'asset-manager')->value('id'), 'locale' => 'en']); // dry-hire level 3, no right

        $this->actingAs($am)->get('/dry-hire')->assertOk()->assertDontSee('web-tick', false);
        $this->actingAs($am)->postJson('/dry-hire/items/'.$item->id.'/website', ['on' => true])->assertForbidden();
        // Without the right the edit form cannot change it either.
        $this->actingAs($am)->put('/dry-hire/items/'.$item->id, ['number' => 'LT-C', 'kind' => 'item', 'name' => 'Claypaky Sharpy', 'unit' => 'pcs', 'active' => 1, 'on_website' => 1, 'subcategory_id' => self::sub('MOV')])->assertRedirect();
        $this->assertFalse($item->fresh()->on_website);

        $am->extraRights()->create(['right' => 'equipment.website']);
        $am->unsetRelation('extraRights');
        $this->actingAs($am->fresh())->get('/dry-hire')->assertOk()->assertSee('web-tick', false);
        $this->actingAs($am->fresh())->postJson('/dry-hire/items/'.$item->id.'/website', ['on' => true])->assertOk()->assertJson(['on' => true]);
        $this->assertTrue($item->fresh()->on_website);
        $this->assertTrue(AuditLog::where('action', 'equipment.website')->exists());
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'nl']);
        $this->actingAs($admin)->get('/settings/roles')->assertOk()->assertSee('Producten: kiezen wat op de website staat');
    }
}
