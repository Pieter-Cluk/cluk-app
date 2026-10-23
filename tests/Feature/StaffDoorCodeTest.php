<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Staff door codes: set by people with the door-code right, seen by the person on their profile, logged without the code. */
class StaffDoorCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_door_codes_for_staff(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $crew = User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
        $this->actingAs($admin)->get('/settings/users/'.$crew->id)->assertOk()->assertSee('Door code');

        $this->actingAs($admin)->put('/settings/users/'.$crew->id.'/door', ['door_code' => '12a4'])->assertSessionHasErrors('door_code');
        $this->actingAs($admin)->put('/settings/users/'.$crew->id.'/door', ['door_code' => '2468 13'])->assertSessionHasNoErrors();
        $this->assertSame('246813', $crew->fresh()->door_code);
        $this->assertNotSame('246813', DB::table('users')->where('id', $crew->id)->value('door_code'), 'stored encrypted');
        $log = AuditLog::where('action', 'user.door_code_set')->firstOrFail();
        $this->assertStringNotContainsString('246813', json_encode($log->data));
        $this->assertArrayNotHasKey('door_code', $crew->fresh()->toArray());

        // The person sees their own code; others without the right don't, and can't change it.
        $this->actingAs($crew->fresh())->get('/me/profile')->assertOk()->assertSee('246813');
        $other = User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
        $this->actingAs($other)->put('/settings/users/'.$crew->id.'/door', ['door_code' => '1111'])->assertForbidden();

        $this->actingAs($admin)->put('/settings/users/'.$crew->id.'/door', ['door_code' => ''])->assertSessionHasNoErrors();
        $this->assertNull($crew->fresh()->door_code);
        $this->assertSame(1, AuditLog::where('action', 'user.door_code_removed')->count());
    }
}
