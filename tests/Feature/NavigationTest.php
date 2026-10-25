<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function keys(User $user): array
    {
        $nav = Navigation::for($user);
        $keys = array_column($nav['top'], 'key');
        foreach ($nav['groups'] as $items) {
            $keys = array_merge($keys, array_column($items, 'key'));
        }

        return $keys;
    }

    public function test_warehouse_crew_only_sees_their_part_of_the_menu(): void
    {
        $keys = $this->keys($crew = $this->as('warehouse'));

        $this->assertContains('warehouse', $keys);
        $this->assertContains('dryhire', $keys);
        $this->assertNotContains('preinvoice', $keys);
        $this->assertNotContains('management', $keys);
        $this->assertNotContains('users', $keys);
        $this->assertArrayNotHasKey('finance', Navigation::for($crew)['groups'], 'empty groups are hidden');
    }

    public function test_admin_sees_everything(): void
    {
        $keys = $this->keys($this->as('admin'));

        foreach (['management', 'companies', 'productsales', 'preinvoice', 'users', 'locations', 'import'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_a_personal_override_changes_the_menu(): void
    {
        $crew = $this->as('warehouse');
        $crew->setModuleOverride('preinvoice', 1);

        $this->assertContains('preinvoice', $this->keys($crew->fresh()));
    }

    public function test_screens_are_blocked_when_the_menu_item_is_hidden(): void
    {
        $crew = $this->as('warehouse');

        $this->actingAs($crew)->get('/sales-products')->assertForbidden();
        $this->actingAs($crew)->get('/warehouse')->assertOk()->assertSee('Movement log');
        $this->actingAs($crew)->get('/pre-invoicing')->assertForbidden();
        $this->actingAs($crew)->get('/settings/users')->assertForbidden();
    }

    public function test_the_layout_shows_the_menu_account_menu_and_logout(): void
    {
        $finance = $this->as('finance');

        $this->actingAs($finance)->get('/dashboard')
            ->assertOk()
            ->assertSee('Pre-invoicing')
            ->assertSee('My preferences')
            ->assertSee('Help &amp; shortcuts', false)
            ->assertSee('Log out')
            ->assertSee($finance->name)
            ->assertDontSee('Warehouse locations');
    }

    public function test_account_pages_are_open_to_everyone_signed_in(): void
    {
        $this->actingAs($this->as('read-only'))->get('/me/preferences')->assertOk();
        $this->get('/help')->assertOk();
    }

    public function test_the_menu_follows_the_language(): void
    {
        $user = $this->as('admin');
        $user->update(['locale' => 'fr']);

        $this->actingAs($user)->get('/dashboard')->assertSee('Préfacturation')->assertSee('Se déconnecter');
    }

    public function test_every_menu_item_has_a_label_in_every_language(): void
    {
        $items = array_merge(config('navigation.top'), config('navigation.account'), ...array_values(config('navigation.groups')));

        foreach (array_keys(config('cluk.locales')) as $locale) {
            app()->setLocale($locale);
            foreach ($items as $item) {
                $this->assertNotSame('nav.items.'.$item['key'], __('nav.items.'.$item['key']), $locale.': '.$item['key']);
            }
            foreach (array_keys(config('navigation.groups')) as $group) {
                $this->assertNotSame('nav.groups.'.$group, __('nav.groups.'.$group));
            }
        }
    }
}
