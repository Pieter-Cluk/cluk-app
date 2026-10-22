<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\Vies;
use App\Services\ViesRecheck;
use App\Support\VatNumber;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ViesAndDoorCodeTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array> answers VIES gives, one per call (the last one repeats) */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Http::preventStrayRequests();
        Http::fake([Vies::URL => fn () => Http::response(count($this->answers) > 1 ? array_shift($this->answers) : ($this->answers[0] ?? []))]);
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    public function test_any_way_of_typing_a_belgian_number_works(): void
    {
        foreach (['BE0123456749', 'BE 0123.456.749', '0123 456 749', 'be0123456749', '123.456.749', 'BE123456749'] as $typed) {
            $this->assertSame('BE0123456749', VatNumber::normalize($typed, 'BE'), $typed);
        }
        $this->assertTrue(VatNumber::looksValid(VatNumber::normalize('123456749')));
    }

    public function test_a_busy_vies_is_retried_and_explained(): void
    {
        $this->answers = [['valid' => false, 'userError' => 'MS_MAX_CONCURRENT_REQ'], ['valid' => true, 'name' => 'DEMO STUDIO BV', 'address' => 'Zulte']];
        $r = (new Vies)->check('BE1010948054');
        $this->assertSame(['valid', null], [$r['status'], $r['reason']]);

        $this->answers = [['valid' => false, 'userError' => 'MS_MAX_CONCURRENT_REQ']];
        $r = (new Vies)->check('BE1010948054');
        $this->assertSame(['unavailable', 'busy'], [$r['status'], $r['reason']]);
    }

    public function test_reasons_show_and_busy_numbers_are_checked_again(): void
    {
        $c = Company::create(['name' => 'Demo Small Shop', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'BE', 'vat_number' => 'BE1010948054']);
        $c->forceFill(['vies_status' => 'unavailable', 'vies_reason' => 'busy'])->save();
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get('/companies/'.$c->id)->assertSee('VIES was busy');

        $this->answers = [['valid' => false]];
        $this->artisan('cluk:vies-recheck')->assertSuccessful();
        $this->assertSame(['invalid', 'not_registered'], [$c->fresh()->vies_status, $c->fresh()->vies_reason]);
        $this->actingAs($admin)->get('/companies/'.$c->id)->assertSee('not registered for VAT on intra-EU trade');

        // A member whose number is valid but known under another name.
        $m = Member::factory()->create(['reg_vat_number' => 'BE1010948054', 'reg_company_name' => 'Studio Lien', 'locale' => 'en']);
        $this->answers = [['valid' => true, 'name' => 'BAKKERIJ JANSSENS BV']];
        ViesRecheck::member($m, new Vies);
        $this->assertSame(['valid', 'name_differs'], [$m->fresh()->vies_status, $m->fresh()->vies_reason]);
        $this->actingAs($admin)->get('/afrit6-staff/members/'.$m->id)->assertSee('BAKKERIJ JANSSENS BV')->assertSee('Check in VIES again');
        $this->assertFalse(Vies::nameDiffers('Demo Studio', 'DEMO STUDIO BV'));
    }

    public function test_a_member_requests_a_door_code_and_staff_see_a_task(): void
    {
        $m = Member::factory()->active()->create(['locale' => 'en']);
        $this->actingAs($m, 'member')->get('/afrit6/portal')->assertOk()->assertSee('Request a door code');
        $this->actingAs($m, 'member')->post('/afrit6/portal/door-code', ['note' => 'New starter'])->assertRedirect();
        $this->assertNotNull($m->fresh()->door_requested_at);
        $this->actingAs($m, 'member')->get('/afrit6/portal')->assertSee('Door code requested on');

        $admin = $this->staff('admin');
        $this->actingAs($admin, 'web')->get('/dashboard')->assertSee('Door codes requested');
        $this->actingAs($admin)->get('/afrit6-staff/members/'.$m->id)->assertSee('asked for a door code')->assertSee('New starter');
        $this->actingAs($admin)->put('/afrit6-staff/members/'.$m->id.'/door-code', ['door_code' => '4827'])->assertRedirect();
        $this->assertNull($m->fresh()->door_requested_at);
        $this->actingAs($admin)->get('/dashboard')->assertDontSee('Door codes requested');
    }
}
