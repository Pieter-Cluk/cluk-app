<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = $this->person('admin', ['first_name' => 'Pieter', 'last_name' => 'Van de Velde']);
    }

    private function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }

    private function person(string $role, array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => $this->role($role)->id, 'locale' => 'en']);
    }

    private function details(array $override = []): array
    {
        return $override + [
            'first_name' => 'Lotte', 'last_name' => 'Peeters', 'email' => 'Lotte@CLUK.live',
            'title' => 'Account manager', 'role_id' => $this->role('sales')->id, 'locale' => 'nl',
            'reports_to_id' => $this->admin->id, 'is_commercial' => '1',
        ];
    }

    // ------------------------------------------------------------ who may

    public function test_only_people_who_manage_users_can_open_the_screens(): void
    {
        $sales = $this->person('sales');

        foreach (['/settings/users', '/settings/users/new', '/settings/roles', '/settings/activity', '/settings/users/'.$sales->id] as $url) {
            $this->actingAs($sales)->get($url)->assertForbidden();
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($sales)->post('/settings/users', $this->details())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'lotte@cluk.live']);
    }

    public function test_an_extra_right_opens_the_screens_for_one_person(): void
    {
        $finance = $this->person('finance');
        $this->actingAs($finance)->get('/settings/users')->assertForbidden();

        $finance->grantRight('users.manage', $this->admin);

        $this->actingAs($finance->fresh())->get('/settings/users')->assertOk();
    }

    // -------------------------------------------------------------- list

    public function test_the_list_shows_people_and_filters(): void
    {
        $this->person('warehouse', ['first_name' => 'Jens', 'title' => 'Warehouse crew']);
        $this->person('sales', ['first_name' => 'Ruben', 'status' => User::STATUS_DISABLED]);
        $this->person('finance', ['first_name' => 'Sofie', 'last_name' => 'Maes', 'title' => 'Bookkeeper']);

        $this->actingAs($this->admin)->get('/settings/users')
            ->assertOk()->assertSee('Pieter')->assertSee('Jens')->assertDontSee('Ruben');

        $this->actingAs($this->admin)->get('/settings/users?status=disabled')
            ->assertSee('Ruben')->assertDontSee('Jens');

        $this->actingAs($this->admin)->get('/settings/users?q=warehouse')
            ->assertSee('Jens')->assertDontSee('Maes');

        $this->actingAs($this->admin)->get('/settings/users?role='.$this->role('warehouse')->id)
            ->assertSee('Jens')->assertDontSee('Maes');
    }

    // ------------------------------------------------------------ adding

    public function test_a_colleague_is_added_with_a_lowercase_email_and_logged(): void
    {
        $response = $this->actingAs($this->admin)->post('/settings/users', $this->details());

        $lotte = User::where('email', 'lotte@cluk.live')->firstOrFail();
        $response->assertRedirect('/settings/users/'.$lotte->id);

        $this->assertTrue($lotte->isActive());
        $this->assertTrue($lotte->is_commercial);
        $this->assertNull($lotte->password, 'staff never get a password');
        $this->assertTrue($lotte->reportsTo->is($this->admin));

        $log = AuditLog::where('action', 'user.created')->firstOrFail();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($lotte->id, $log->subject_id);
    }

    public function test_the_email_must_be_unique_whatever_the_case(): void
    {
        $this->person('sales', ['email' => 'lotte@cluk.live']);

        $this->actingAs($this->admin)->post('/settings/users', $this->details())
            ->assertSessionHasErrors('email');
    }

    public function test_required_fields_are_checked(): void
    {
        $this->actingAs($this->admin)->post('/settings/users', ['email' => 'not-an-email', 'locale' => 'xx'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'role_id', 'locale']);
    }

    // ----------------------------------------------------------- editing

    public function test_details_are_saved_and_only_real_changes_are_logged(): void
    {
        $lotte = $this->person('sales', ['first_name' => 'Lotte', 'last_name' => 'Peeters', 'email' => 'lotte@cluk.live', 'title' => 'Sales']);

        $this->actingAs($this->admin)->put('/settings/users/'.$lotte->id, $this->details(['title' => 'Account manager']))
            ->assertRedirect('/settings/users/'.$lotte->id);

        $this->assertSame('Account manager', $lotte->fresh()->title);
        $log = AuditLog::where('action', 'user.updated')->firstOrFail();
        $this->assertArrayHasKey('title', $log->data['changes']);
        $this->assertArrayNotHasKey('first_name', $log->data['changes']);
    }

    public function test_a_role_change_is_logged_with_both_names(): void
    {
        $lotte = $this->person('sales', ['first_name' => 'Lotte', 'last_name' => 'Peeters', 'email' => 'lotte@cluk.live']);

        $this->actingAs($this->admin)->put('/settings/users/'.$lotte->id, $this->details(['role_id' => $this->role('manager')->id]));

        $this->assertSame('manager', $lotte->fresh()->role->slug);
        $log = AuditLog::where('action', 'user.role_changed')->firstOrFail();
        $this->assertSame(['from' => 'Sales', 'to' => 'Manager'], $log->data);
    }

    public function test_reports_to_cannot_make_a_loop(): void
    {
        $ruben = $this->person('manager', ['reports_to_id' => $this->admin->id]);
        $lotte = $this->person('sales', ['reports_to_id' => $ruben->id]);

        // Ruben may not report to Lotte, who reports to him.
        $this->actingAs($this->admin)->put('/settings/users/'.$ruben->id, $this->details([
            'email' => $ruben->email, 'reports_to_id' => $lotte->id, 'role_id' => $ruben->role_id,
        ]))->assertSessionHasErrors('reports_to_id');

        // Nor to himself.
        $this->actingAs($this->admin)->put('/settings/users/'.$ruben->id, $this->details([
            'email' => $ruben->email, 'reports_to_id' => $ruben->id, 'role_id' => $ruben->role_id,
        ]))->assertSessionHasErrors('reports_to_id');

        $this->assertSame($this->admin->id, $ruben->fresh()->reports_to_id);
    }

    // ------------------------------------------------------------ access

    public function test_module_overrides_and_extra_rights_are_saved_and_logged(): void
    {
        $jens = $this->person('warehouse');

        $this->actingAs($this->admin)->put('/settings/users/'.$jens->id.'/access', [
            'overrides' => ['afrit6' => '2', 'sales' => '', 'warehouse' => '0'],
            'rights' => ['warehouse.write_off', 'warehouse.sign_delivery'], // sign_delivery comes from the role
        ])->assertRedirect('/settings/users/'.$jens->id);

        $jens = $jens->fresh();
        $this->assertSame(2, $jens->moduleLevel('afrit6'));
        $this->assertSame(0, $jens->moduleLevel('warehouse'));
        $this->assertFalse($jens->hasOverride('sales'));
        $this->assertTrue($jens->hasRight('warehouse.write_off'));
        $this->assertSame(['warehouse.write_off'], $jens->extraRights->pluck('right')->all(), 'rights from the role are not copied to the person');

        $this->assertSame(2, AuditLog::where('action', 'user.module_override')->count());
        $this->assertSame(1, AuditLog::where('action', 'user.right_granted')->count());

        // Back to the role: overrides and the extra right are removed.
        $this->actingAs($this->admin)->put('/settings/users/'.$jens->id.'/access', ['overrides' => ['afrit6' => '', 'warehouse' => '']]);
        $jens = $jens->fresh();
        $this->assertSame(1, $jens->moduleLevel('afrit6'));
        $this->assertFalse($jens->hasRight('warehouse.write_off'));
        $this->assertSame(1, AuditLog::where('action', 'user.right_revoked')->count());
    }

    public function test_unknown_rights_and_levels_are_refused(): void
    {
        $jens = $this->person('warehouse');

        $this->actingAs($this->admin)->put('/settings/users/'.$jens->id.'/access', ['rights' => ['make.coffee']])
            ->assertSessionHasErrors('rights.0');
        $this->actingAs($this->admin)->put('/settings/users/'.$jens->id.'/access', ['overrides' => ['sales' => '7']])
            ->assertSessionHasErrors('overrides.sales');
    }

    // ------------------------------------------------------ disable/enable

    public function test_disabling_signs_the_person_out_and_blocks_them(): void
    {
        $jens = $this->person('warehouse');

        $this->actingAs($this->admin)->post('/settings/users/'.$jens->id.'/disable')->assertRedirect();
        $this->assertFalse($jens->fresh()->isActive());
        $this->assertSame(1, AuditLog::where('action', 'user.disabled')->count());

        // Still holding a session: the next click signs them out.
        $this->actingAs($jens->fresh())->get('/dashboard')
            ->assertRedirect('/login')->assertSessionHas('login_error', 'disabled');
        $this->assertGuest();

        $this->actingAs($this->admin)->post('/settings/users/'.$jens->id.'/enable');
        $this->assertTrue($jens->fresh()->isActive());
        $this->assertSame(1, AuditLog::where('action', 'user.enabled')->count());
    }

    public function test_you_cannot_disable_yourself(): void
    {
        $this->actingAs($this->admin)->post('/settings/users/'.$this->admin->id.'/disable')
            ->assertSessionHasErrors('access');

        $this->assertTrue($this->admin->fresh()->isActive());
    }

    public function test_the_last_person_who_manages_users_cannot_lose_that_right(): void
    {
        $jens = $this->person('warehouse');
        $jens->grantRight('users.manage', $this->admin);

        // Jens can disable Pieter: Jens still manages users.
        $this->actingAs($jens->fresh())->post('/settings/users/'.$this->admin->id.'/disable')->assertSessionHasNoErrors();
        $this->assertFalse($this->admin->fresh()->isActive());

        // Taking Jens' own right away now would leave nobody: refused and nothing changes.
        $this->actingAs($jens->fresh())->put('/settings/users/'.$jens->id.'/access', ['rights' => []])
            ->assertSessionHasErrors('access');
        $this->assertTrue($jens->fresh()->hasRight('users.manage'));
        $this->assertSame(0, AuditLog::where('action', 'user.right_revoked')->count(), 'the log is rolled back too');
    }

    public function test_the_last_admin_cannot_change_their_own_role_away(): void
    {
        $this->actingAs($this->admin)->put('/settings/users/'.$this->admin->id, $this->details([
            'first_name' => 'Pieter', 'last_name' => 'Van de Velde', 'email' => $this->admin->email,
            'role_id' => $this->role('sales')->id, 'reports_to_id' => null,
        ]))->assertSessionHasErrors('access');

        $this->assertSame('admin', $this->admin->fresh()->role->slug);
    }

    // ------------------------------------------------------------- roles

    public function test_the_roles_matrix_saves_levels_and_rights_and_logs_the_difference(): void
    {
        $sales = $this->role('sales');
        $payload = $this->matrixPayload();
        $payload['levels'][$sales->id]['afrit6'] = 3;
        $payload['rights'][$sales->id][] = 'sales.edit_items';

        $this->actingAs($this->admin)->put('/settings/roles', $payload)->assertRedirect('/settings/roles');

        $sales = $sales->fresh(['moduleLevels', 'rights']);
        $this->assertSame(3, $sales->levelFor('afrit6'));
        $this->assertTrue($sales->hasRight('sales.edit_items'));

        $logs = AuditLog::where('action', 'role.updated')->get();
        $this->assertCount(1, $logs, 'only the changed role is logged');
        $this->assertSame(['afrit6' => [1, 3]], $logs[0]->data['levels']);
        $this->assertSame(['sales.edit_items'], $logs[0]->data['rights_added']);
    }

    public function test_the_admin_role_cannot_lose_user_management_when_nobody_else_has_it(): void
    {
        $admin = $this->role('admin');
        $payload = $this->matrixPayload();
        $payload['rights'][$admin->id] = array_values(array_diff($payload['rights'][$admin->id], ['users.manage']));

        $this->actingAs($this->admin)->put('/settings/roles', $payload)->assertSessionHasErrors('access');

        $this->assertTrue($admin->fresh('rights')->hasRight('users.manage'));
    }

    public function test_a_role_added_meanwhile_is_not_touched(): void
    {
        $payload = $this->matrixPayload();
        $late = Role::create(['name' => 'Late', 'slug' => 'late']);
        $late->setRights(['orders.approve']);

        $this->actingAs($this->admin)->put('/settings/roles', $payload)->assertSessionHasNoErrors();

        $this->assertTrue($late->fresh('rights')->hasRight('orders.approve'));
    }

    public function test_a_new_role_can_start_from_another_and_an_unused_role_can_be_deleted(): void
    {
        $this->actingAs($this->admin)->post('/settings/roles', ['name' => 'Freelance crew', 'copy_from' => $this->role('warehouse')->id])
            ->assertRedirect('/settings/roles');

        $crew = Role::where('slug', 'freelance-crew')->firstOrFail();
        $this->assertSame(2, $crew->levelFor('warehouse'));
        $this->assertTrue($crew->hasRight('warehouse.sign_delivery'));
        $this->assertSame(1, AuditLog::where('action', 'role.created')->count());

        $this->actingAs($this->admin)->delete('/settings/roles/'.$crew->id)->assertRedirect('/settings/roles');
        $this->assertModelMissing($crew);

        $this->actingAs($this->admin)->delete('/settings/roles/'.$this->role('admin')->id)->assertSessionHasErrors('access');
        $this->assertNotNull(Role::where('slug', 'admin')->first());
    }

    // ----------------------------------------------------- activity log

    public function test_the_activity_log_reads_as_sentences_and_filters_by_area(): void
    {
        $jens = $this->person('warehouse', ['first_name' => 'Jens', 'last_name' => 'Claes']);
        $this->actingAs($this->admin);
        $jens->setModuleOverride('afrit6', 2, $this->admin);
        AuditLog::record('auth.login', $this->admin, [], $this->admin);

        $this->get('/settings/activity')->assertOk()
            ->assertSee('Changed module access')->assertSee('Jens Claes')->assertSee('Signed in');

        $this->get('/settings/activity?area=auth')->assertOk()
            ->assertSee('Signed in')->assertDontSee('Changed module access');
    }

    public function test_every_screen_renders_in_every_language(): void
    {
        $jens = $this->person('warehouse', ['reports_to_id' => $this->admin->id]);
        $jens->grantRight('orders.approve', $this->admin);

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $this->admin->forceFill(['locale' => $locale])->save();
            foreach (['/settings/users', '/settings/users/new', '/settings/users/'.$jens->id, '/settings/users/'.$this->admin->id, '/settings/roles', '/settings/activity'] as $url) {
                $html = $this->actingAs($this->admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(users|cluk|nav)\.[a-z_]+\.[a-z_]+/', strip_tags($html), "untranslated text on {$url} ({$locale})");
            }
        }
    }

    /** The matrix as the screen sends it: every role with its current levels and rights. */
    private function matrixPayload(): array
    {
        $payload = ['shown' => [], 'levels' => [], 'rights' => []];
        foreach (Role::with(['moduleLevels', 'rights'])->get() as $role) {
            $payload['shown'][] = $role->id;
            foreach (config('cluk.modules') as $module) {
                $payload['levels'][$role->id][$module] = $role->levelFor($module);
            }
            $payload['rights'][$role->id] = $role->rights->pluck('right')->all();
        }

        return $payload;
    }
}
