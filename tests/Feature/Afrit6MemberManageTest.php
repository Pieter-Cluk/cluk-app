<?php

namespace Tests\Feature;

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\Space;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MemberInvited;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The owner's tools for Afrit 6 members: add, change anything, bookings and rentals, delete. */
class Afrit6MemberManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function owner(): User
    {
        $u = $this->staff('manager');
        $u->extraRights()->create(['right' => 'afrit6.manage_members']);

        return $u->fresh();
    }

    public function test_only_people_with_the_right_see_and_use_the_tools(): void
    {
        $manager = $this->staff('manager'); // may approve members, but not manage them
        $m = Member::factory()->active()->create(['locale' => 'en']);
        $this->actingAs($manager)->get('/afrit6-staff')->assertOk()->assertDontSee('Add member');
        $this->actingAs($manager)->get('/afrit6-staff/members/new')->assertForbidden();
        $this->actingAs($manager)->get('/afrit6-staff/members/'.$m->id)->assertOk()->assertDontSee('Owner: change anything');
        $this->actingAs($manager)->put('/afrit6-staff/members/'.$m->id, ['first_name' => 'X'])->assertForbidden();
        $this->assertFalse(Role::where('slug', 'manager')->first()->hasRight('afrit6.manage_members'));
    }

    public function test_the_owner_adds_a_member_who_chooses_a_password_by_mail(): void
    {
        Notification::fake();
        $owner = $this->owner();
        $this->actingAs($owner)->get('/afrit6-staff')->assertSee('Add member');
        $this->actingAs($owner)->get('/afrit6-staff/members/new')->assertOk()->assertSee('Company');

        $this->actingAs($owner)->post('/afrit6-staff/members', [
            'first_name' => 'Lotte', 'last_name' => 'Demo', 'email' => 'Lotte@Studio.example', 'mobile' => '+32 470 00 00 00', 'locale' => 'nl',
            'company' => 'Studio Demo', 'reg_vat_number' => '', 'reg_country' => 'BE', 'reg_address' => 'Demostraat 1, 9870 Zulte', 'send_mail' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $m = Member::where('email', 'lotte@studio.example')->firstOrFail();
        $this->assertSame([Member::ACTIVE, 'Studio Demo'], [$m->status, $m->company->name]);
        $this->assertTrue(AuditLog::where('action', 'afrit6.member_added')->where('actor_id', $owner->id)->exists());
        $token = null;
        Notification::assertSentTo($m, MemberInvited::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        // The welcome link still works after 3 days (a normal reset link would not).
        Carbon::setTestNow(now()->addDays(3));
        $this->get('/afrit6/reset-password/'.$token.'?email=lotte@studio.example&invite=1')->assertOk()->assertSee('choose your password');
        $this->post('/afrit6/reset-password', ['token' => $token, 'email' => 'lotte@studio.example', 'password' => 'Zonnebloem-Veld-2026!', 'invite' => '1'])
            ->assertRedirect(route('afrit6.login'))->assertSessionHasNoErrors();
        $this->post('/afrit6/login', ['email' => 'lotte@studio.example', 'password' => 'Zonnebloem-Veld-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($m->fresh(), 'member');
    }

    public function test_the_owner_changes_anything_on_the_card(): void
    {
        $owner = $this->owner();
        $m = Member::factory()->active()->create(['locale' => 'en']);
        $other = Company::create(['name' => 'Other Studio BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $this->actingAs($owner)->get('/afrit6-staff/members/'.$m->id)->assertOk()->assertSee('Owner: change anything');

        $this->actingAs($owner)->put('/afrit6-staff/members/'.$m->id, [
            'first_name' => 'Jan', 'last_name' => 'Nieuw', 'email' => 'jan@other.example', 'locale' => 'fr', 'company' => 'Other Studio BV · #'.$other->id,
            'reg_country' => 'BE', 'status' => Member::BLOCKED,
        ])->assertSessionHasNoErrors();
        $m->refresh();
        $this->assertSame(['Jan Nieuw', 'jan@other.example', 'fr', $other->id, Member::BLOCKED], [$m->name, $m->email, $m->locale, $m->company_id, $m->status]);
        $log = AuditLog::where('action', 'afrit6.member_edited')->firstOrFail();
        $this->assertSame(Member::BLOCKED, $log->data['changes']['status'][1]);
    }

    public function test_bookings_and_desk_rentals(): void
    {
        $owner = $this->owner();
        $m = Member::factory()->active()->create(['locale' => 'en']);
        $desk = Space::where('code', 'D-04')->firstOrFail();

        // A desk day last week (used without booking), at the standard price; and the room.
        $this->actingAs($owner)->post('/afrit6-staff/members/'.$m->id.'/desk-days', ['space_id' => $desk->id, 'date' => '2026-10-13', 'part' => 'day'])->assertSessionHasNoErrors();
        $b = DeskBooking::where('member_id', $m->id)->firstOrFail();
        $this->assertSame(DeskBooking::price('day'), $b->price_cents);
        $this->actingAs($owner)->post('/afrit6-staff/members/'.$m->id.'/desk-days', ['space_id' => $desk->id, 'date' => '2026-10-13', 'part' => 'am'])->assertSessionHasErrors('space_id');
        $this->actingAs($owner)->post('/afrit6-staff/members/'.$m->id.'/room', ['date' => '2026-10-14', 'start' => 4, 'slots' => 4, 'setup' => 'meeting'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/afrit6-staff/members/'.$m->id.'/room', ['date' => '2026-10-14', 'start' => 6, 'slots' => 2, 'setup' => 'meeting'])->assertSessionHasErrors('start');
        $this->actingAs($owner)->get('/afrit6-staff/members/'.$m->id)->assertSee('13/10/2026')->assertSee('10:00–12:00');

        // Remove the desk day.
        $this->actingAs($owner)->delete('/afrit6-staff/members/'.$m->id.'/bookings/desk/'.$b->id)->assertSessionHasNoErrors();
        $this->assertNotNull($b->fresh()->cancelled_at);

        // Change and delete a desk rental.
        $r = DeskRental::create(['member_id' => $m->id, 'space_id' => $desk->id, 'starts_on' => '2026-10-01', 'monthly_price_cents' => 35000]);
        $this->actingAs($owner)->put('/afrit6-staff/members/'.$m->id.'/rentals/'.$r->id, ['starts_on' => '2026-10-05', 'ends_on' => '2026-12-31', 'price' => '300'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-10-05', '2026-12-31', 30000], [$r->fresh()->starts_on->toDateString(), $r->fresh()->ends_on->toDateString(), $r->fresh()->monthly_price_cents]);
        $this->actingAs($owner)->delete('/afrit6-staff/members/'.$m->id.'/rentals/'.$r->id)->assertSessionHasNoErrors();
        $this->assertNull($r->fresh());
        $this->assertSame(1, RoomBooking::where('member_id', $m->id)->count());
    }

    public function test_delete_a_member_only_without_statements(): void
    {
        $owner = $this->owner();
        $m = Member::factory()->active()->create(['locale' => 'en']);
        $this->actingAs($owner)->delete('/afrit6-staff/members/'.$m->id, ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
        $this->actingAs($owner)->delete('/afrit6-staff/members/'.$m->id, ['confirm' => $m->email])->assertRedirect();
        $this->assertNull($m->fresh());
        $this->assertTrue(AuditLog::where('action', 'afrit6.member_deleted')->exists());
    }
}
