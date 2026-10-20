<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $slug, array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('slug', $slug)->value('id')]);
    }

    public function test_the_role_sets_the_level_per_module(): void
    {
        $crew = $this->userWithRole('warehouse');

        $this->assertSame(2, $crew->moduleLevel('warehouse'));
        $this->assertSame(0, $crew->moduleLevel('sales'));
        $this->assertTrue($crew->canUseModule('dryhire', 2));
        $this->assertFalse($crew->canUseModule('dryhire', 3));
    }

    public function test_a_personal_override_wins_over_the_role_and_is_logged(): void
    {
        $admin = $this->userWithRole('admin');
        $crew = $this->userWithRole('warehouse');

        $crew->setModuleOverride('afrit6', 2, $admin);
        $this->assertSame(2, $crew->moduleLevel('afrit6'));
        $this->assertTrue($crew->hasOverride('afrit6'));

        $crew->setModuleOverride('warehouse', 0, $admin);
        $this->assertSame(0, $crew->moduleLevel('warehouse'));
        $this->assertNotContains('warehouse', $crew->allowedModules());

        $crew->setModuleOverride('warehouse', null, $admin);
        $this->assertSame(2, $crew->fresh()->moduleLevel('warehouse'));

        $this->assertSame(3, AuditLog::where('action', 'user.module_override')->count());
        $this->assertSame($admin->id, AuditLog::first()->actor_id);
    }

    public function test_the_menu_only_shows_allowed_modules_in_order(): void
    {
        $host = $this->userWithRole('workspace-host');

        $this->assertSame(['companies', 'warehouse', 'factory', 'afrit6'], $host->allowedModules());
    }

    public function test_rights_come_from_the_role_and_from_the_person(): void
    {
        $crew = $this->userWithRole('warehouse');

        $this->assertTrue($crew->hasRight('warehouse.sign_delivery'));
        $this->assertFalse($crew->hasRight('warehouse.release_repair'));

        $crew->grantRight('warehouse.release_repair');
        $this->assertTrue($crew->hasRight('warehouse.release_repair'));

        $crew->revokeRight('warehouse.release_repair');
        $this->assertFalse($crew->hasRight('warehouse.release_repair'));
        $this->assertSame(2, AuditLog::whereIn('action', ['user.right_granted', 'user.right_revoked'])->count());
    }

    public function test_admin_has_every_module_and_right(): void
    {
        $admin = $this->userWithRole('admin');

        $this->assertSame(config('cluk.modules'), $admin->allowedModules());
        foreach (config('cluk.rights') as $right) {
            $this->assertTrue($admin->hasRight($right), $right);
        }
    }

    public function test_a_disabled_person_has_no_access(): void
    {
        $admin = $this->userWithRole('admin', ['status' => User::STATUS_DISABLED]);

        $this->assertSame([], $admin->allowedModules());
        $this->assertFalse($admin->hasRight('users.manage'));
    }

    public function test_every_seeded_right_and_module_exists_in_the_config(): void
    {
        foreach (RoleSeeder::ROLES as [$name, $levels, $rights]) {
            $this->assertCount(count(config('cluk.modules')), $levels, $name);
            foreach ($rights as $right) {
                if ($right !== '*') {
                    $this->assertContains($right, config('cluk.rights'), $name);
                }
            }
        }
    }

    public function test_name_and_initials(): void
    {
        $u = User::factory()->make(['first_name' => 'Lotte', 'last_name' => 'Wouters']);

        $this->assertSame('Lotte Wouters', $u->name);
        $this->assertSame('LW', $u->initials);
    }
}
