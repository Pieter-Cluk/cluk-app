<?php

namespace Tests\Feature;

use App\Models\Afrit6\ServiceRequest;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse\Position;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A member's storage gets its exact shelf or pallet place: on the member card, or by scanning on the phone. */
class Afrit6StoragePlacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Position::whereIn('code', ['GF5B1', 'GF5B2'])->update(['use' => 'afrit6']);
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function shelf(Member $m, array $attrs = []): ServiceRequest
    {
        return ServiceRequest::create($attrs + ['member_id' => $m->id, 'kind' => 'shelf', 'status' => 'confirmed', 'date' => today(), 'qty' => 1]);
    }

    public function test_staff_set_and_free_a_place_on_the_member_card(): void
    {
        $admin = $this->as('admin');
        $a = Member::factory()->active()->create(['locale' => 'en', 'reg_company_name' => 'Studio Noord']);
        $b = Member::factory()->active()->create(['locale' => 'en', 'reg_company_name' => 'Pixel Co']);
        $ra = $this->shelf($a);
        $rb = $this->shelf($b);
        $p1 = Position::byCode('GF5B1');
        $rental = Position::byCode('GA1B1');

        $this->actingAs($admin)->get('/afrit6-staff/members/'.$a->id)->assertOk()->assertSee('Storage places')->assertSee('No exact place yet.')->assertSee('GF5B1');
        $this->actingAs($admin)->post('/afrit6-staff/storage/'.$ra->id.'/places', ['position_id' => $p1->id])->assertRedirect();
        $this->assertSame(['GF5B1'], $ra->places()->pluck('code')->all());
        $this->assertTrue(AuditLog::where('action', 'afrit6.place_set')->exists());

        // Taken by A: not offered to B, and refused.
        $this->actingAs($admin)->get('/afrit6-staff/members/'.$b->id)->assertOk()->assertDontSee('<option value="'.$p1->id.'"', false);
        $this->actingAs($admin)->post('/afrit6-staff/storage/'.$rb->id.'/places', ['position_id' => $p1->id])->assertSessionHasErrors('place');
        $this->actingAs($admin)->post('/afrit6-staff/storage/'.$rb->id.'/places', ['position_id' => $rental->id])->assertSessionHasErrors('place');

        // Shown on the place and in the member's portal.
        $this->actingAs($admin)->get(route('locations.positions', ['floor' => 'G', 'row' => 'F', 'p' => 'GF5B1']))->assertOk()->assertSee('Studio Noord');
        $this->actingAs($a, 'member')->get('/afrit6/portal/services')->assertOk()->assertSee('Your place: GF5B1');

        $this->actingAs($admin)->delete('/afrit6-staff/storage/'.$ra->id.'/places/'.$p1->id)->assertRedirect();
        $this->assertSame(0, $ra->places()->count());
        $this->actingAs($admin)->post('/afrit6-staff/storage/'.$rb->id.'/places', ['position_id' => $p1->id])->assertSessionHasNoErrors();
    }

    public function test_ended_storage_frees_the_place(): void
    {
        $a = Member::factory()->active()->create();
        $b = Member::factory()->active()->create();
        $old = $this->shelf($a, ['status' => 'done', 'date' => today()->subMonth(), 'ends_on' => today()->subDay()]);
        $old->places()->attach(Position::byCode('GF5B1')->id);
        $this->assertNull(\App\Services\Afrit6\StoragePlaces::problem($this->shelf($b), Position::byCode('GF5B1')));
    }

    public function test_scanning_a_place_on_the_phone_sets_it_and_refuses_another_members_place(): void
    {
        $crew = $this->as('warehouse');
        $a = Member::factory()->active()->create(['reg_company_name' => 'Studio Noord']);
        $b = Member::factory()->active()->create();
        $ra = $this->shelf($a);
        $rb = $this->shelf($b);
        $this->actingAs($crew)->post('/w/afrit6/'.$ra->id.'/scan', ['code' => 'GF5B1'])->assertSessionHasNoErrors();
        $this->assertSame(['GF5B1'], $ra->places()->pluck('code')->all());
        $this->actingAs($crew)->post('/w/afrit6/'.$rb->id.'/scan', ['code' => 'GF5B1'])->assertSessionHasErrors('code');
        $this->actingAs($crew)->post('/w/afrit6/'.$rb->id.'/scan', ['code' => 'GF5B2'])->assertSessionHasNoErrors();
        $this->assertSame(['GF5B2'], $rb->places()->pluck('code')->all());
    }
}
