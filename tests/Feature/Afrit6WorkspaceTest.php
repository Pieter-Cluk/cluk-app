<?php

namespace Tests\Feature;

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Afrit6\Space;
use App\Models\Afrit6\Statement;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\Afrit6\StatementBuilder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Afrit 6 member portal, staff tasks and the monthly statement. */
class Afrit6WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        // Monday 19 October 2026, 09:00 in Brussels.
        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function desk(string $code): Space
    {
        return Space::where('code', $code)->firstOrFail();
    }

    private function member(array $desks = ['D-04'], string $from = '2026-10-14', array $attrs = []): Member
    {
        $m = Member::factory()->active()->create($attrs + ['locale' => 'en']);
        foreach ($desks as $code) {
            DeskRental::create(['member_id' => $m->id, 'space_id' => $this->desk($code)->id, 'starts_on' => $from, 'monthly_price_cents' => 35000]);
        }

        return $m;
    }

    private function staff(string $role, array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function book(Member $m, string $date, int $start, int $slots, string $setup = 'meeting')
    {
        return $this->actingAs($m, 'member')->post('/afrit6/portal/room', compact('date', 'start', 'slots', 'setup'));
    }

    // ------------------------------------------------------------- pages

    public function test_every_portal_and_staff_page_is_translated(): void
    {
        $m = $this->member(['D-04', 'D-05']);
        $m->forceFill(['door_code' => '4827'])->save();
        $this->book($m, '2026-10-20', 4, 2, 'classroom');
        ServiceRequest::create(['member_id' => $m->id, 'kind' => 'packing', 'date' => '2026-10-20', 'colis' => 4, 'amount_cents' => 1000]);
        $admin = $this->staff('admin');
        $task = ServiceRequest::first();
        $pattern = '/\b(workspace|afrit6|nav|users)\.[a-z_]+(\.[a-z_]+)*\b/';

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $m->forceFill(['locale' => $locale])->save();
            foreach (['/afrit6/portal', '/afrit6/portal/desks?desk=D-07&day=2026-10-20&part=am', '/afrit6/portal/desks?desk=D-04', '/afrit6/portal/room?day=2026-10-20', '/afrit6/portal/services?new=receive', '/afrit6/portal/bill'] as $url) {
                $html = $this->actingAs($m->fresh(), 'member')->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/afrit6-staff/tasks', '/afrit6-staff/tasks/'.$task->id, '/afrit6-staff/room?week=2026-10-19', '/afrit6-staff/desks', '/afrit6-staff/members/'.$m->id, '/dashboard'] as $url) {
                $html = $this->actingAs($admin->fresh(), 'web')->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
        }
    }

    public function test_only_approved_members_use_the_workspace(): void
    {
        $pending = Member::factory()->verified()->create();

        $this->actingAs($pending, 'member')->get('/afrit6/portal/desks')->assertRedirect('/afrit6/portal');
        $this->actingAs($pending, 'member')->post('/afrit6/portal/room', [])->assertRedirect('/afrit6/portal');
        $this->get('/afrit6-staff/tasks')->assertRedirect('/login');
    }

    public function test_the_door_code_is_encrypted_shown_to_the_member_and_set_with_the_right(): void
    {
        $m = $this->member();
        $host = $this->staff('workspace-host');
        $sales = $this->staff('sales');

        $this->actingAs($sales, 'web')->put('/afrit6-staff/members/'.$m->id.'/door-code', ['door_code' => '1234'])->assertForbidden();
        $this->actingAs($host, 'web')->put('/afrit6-staff/members/'.$m->id.'/door-code', ['door_code' => '48 27'])->assertRedirect();

        $this->assertSame('4827', $m->fresh()->door_code);
        $this->assertStringNotContainsString('4827', (string) DB::table('members')->where('id', $m->id)->value('door_code'));
        $this->actingAs($m->fresh(), 'member')->get('/afrit6/portal')->assertSee('4 8 2 7');
        $log = AuditLog::where('action', 'afrit6.door_code_set')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('4827', json_encode($log->data));
    }

    // ------------------------------------------------------------- desks

    public function test_a_member_swaps_a_desk_from_tomorrow(): void
    {
        $m = $this->member(['D-04']);

        $this->actingAs($m, 'member')->post('/afrit6/portal/desks/swap', ['from' => $this->desk('D-04')->id, 'to' => $this->desk('D-07')->id])
            ->assertRedirect('/afrit6/portal/desks');

        $rentals = $m->deskRentals()->with('space')->get();
        $this->assertSame('2026-10-19', $rentals[0]->ends_on->toDateString());
        $this->assertSame('D-07', $rentals[1]->space->code);
        $this->assertSame('2026-10-20', $rentals[1]->starts_on->toDateString());
        $this->assertSame(35000, $rentals[1]->monthly_price_cents);
        $this->assertTrue(ServiceRequest::where('kind', 'desk_swap')->where('status', 'confirmed')->exists());
        $this->assertTrue(AuditLog::where('action', 'afrit6.desk_swapped')->exists());
    }

    public function test_a_rented_desk_cannot_be_taken(): void
    {
        $m = $this->member(['D-04']);
        $this->member(['D-07']);

        $this->actingAs($m, 'member')->post('/afrit6/portal/desks/swap', ['from' => $this->desk('D-04')->id, 'to' => $this->desk('D-07')->id])
            ->assertSessionHasErrors('to');
        $this->actingAs($m, 'member')->post('/afrit6/portal/desks/add', ['space_id' => $this->desk('D-07')->id, 'date' => '2026-10-20'])
            ->assertSessionHasErrors('space_id');
        $this->assertSame(1, $m->deskRentals()->count());
    }

    public function test_an_extra_desk_is_booked_when_the_team_confirms(): void
    {
        $m = $this->member(['D-04']);
        $host = $this->staff('workspace-host');

        $this->actingAs($m, 'member')->post('/afrit6/portal/desks/add', ['space_id' => $this->desk('A-4')->id, 'date' => '2026-10-21'])->assertRedirect();
        $task = ServiceRequest::where('kind', 'desk_add')->firstOrFail();
        $this->assertSame('pending', $task->status);

        $this->actingAs($this->staff('sales'), 'web')->post('/afrit6-staff/tasks/'.$task->id.'/confirm')->assertForbidden();
        $this->actingAs($host, 'web')->post('/afrit6-staff/tasks/'.$task->id.'/confirm')->assertRedirect();

        $this->assertSame('done', $task->fresh()->status);
        $this->assertSame(['D-04', 'A-4'], $m->deskRentals()->with('space')->get()->pluck('space.code')->all());
    }

    // -------------------------------------------------------------- room

    public function test_room_booking_rules(): void
    {
        $a = $this->member(['D-01']);
        $b = $this->member(['D-02']);

        // Classroom 10:00–11:00 (slots 4–5): resets at 09:30 and 11:00.
        $this->book($a, '2026-10-20', 4, 2, 'classroom')->assertSessionHasNoErrors();
        $this->assertTrue(ServiceRequest::where('kind', 'room_reset')->exists());

        $this->book($b, '2026-10-20', 5, 2)->assertSessionHasErrors('start');                 // overlaps
        $this->book($b, '2026-10-20', 6, 2)->assertSessionHasErrors('start');                 // reset after the classroom
        $this->book($b, '2026-10-20', 2, 2)->assertSessionHasErrors('start');                 // meeting ends in the reset before
        $this->book($b, '2026-10-20', 6, 2, 'classroom')->assertSessionHasNoErrors();         // classroom right after: no reset needed
        $this->book($b, '2026-10-20', 9, 2)->assertSessionHasNoErrors();                      // 12:30, after the second classroom's reset
        $this->book($a, '2026-10-20', 11, 2, 'classroom')->assertSessionHasErrors('start');   // needs a reset before but 12:30 is booked

        $this->book($a, '2026-10-19', 0, 2)->assertSessionHasErrors('start');                 // 08:00 today has passed
        $this->book($a, '2026-10-24', 4, 2)->assertSessionHasErrors('start');                 // Saturday
        $this->book($a, '2027-01-04', 4, 2)->assertSessionHasErrors('start');                 // more than 8 weeks ahead
        $this->book($a, '2026-10-21', 18, 4)->assertSessionHasErrors('start');                // past closing time

        $this->assertSame(3, RoomBooking::live()->count());
    }

    public function test_cancelling_a_booking_removes_the_reset_task_and_is_not_possible_once_started(): void
    {
        $m = $this->member();
        $this->book($m, '2026-10-20', 4, 2, 'classroom');
        $booking = RoomBooking::firstOrFail();

        $this->actingAs($m, 'member')->post('/afrit6/portal/room/'.$booking->id.'/cancel')->assertRedirect();
        $this->assertNotNull($booking->fresh()->cancelled_at);
        $this->assertFalse(ServiceRequest::where('kind', 'room_reset')->exists());

        $this->book($m, '2026-10-19', 6, 2)->assertSessionHasNoErrors(); // today 11:00
        $today = RoomBooking::live()->firstOrFail();
        Carbon::setTestNow(Carbon::parse('2026-10-19 11:15:00'));
        $this->actingAs($m, 'member')->post('/afrit6/portal/room/'.$today->id.'/cancel')->assertSessionHasErrors('booking');

        $other = $this->member(['D-02']);
        $this->actingAs($other, 'member')->post('/afrit6/portal/room/'.$today->id.'/cancel')->assertNotFound();
    }

    public function test_each_desk_gives_one_free_booking_of_two_hours_per_week(): void
    {
        $m = $this->member(['D-04']);
        $this->book($m, '2026-10-20', 0, 6);   // 3 h: 1 h extra
        $this->book($m, '2026-10-22', 0, 2);   // second booking that week: all extra (1 h)
        $this->book($m, '2026-10-27', 0, 4);   // next week, 2 h: free

        $bill = (new StatementBuilder($m))->build(today());
        $room = collect($bill['lines'])->firstWhere('type', 'room');

        $this->assertSame(4, (int) $room['qty']);              // 4 half hours
        $this->assertSame(4 * 625, $room['amount_cents']); // € 12.50 per hour
    }

    // ---------------------------------------------------------- services

    public function test_service_prices(): void
    {
        $m = $this->member();
        $post = fn (array $d) => $this->actingAs($m, 'member')->post('/afrit6/portal/services', $d + ['date' => '2026-10-20']);

        $post(['kind' => 'packing', 'colis' => 40])->assertSessionHasNoErrors();
        $post(['kind' => 'packing', 'colis' => 2, 'pallets' => 1, 'then_send' => 1])->assertSessionHasNoErrors();
        $post(['kind' => 'receive', 'pallets' => 2, 'receive_mode' => 'shelf24'])->assertSessionHasNoErrors();
        $post(['kind' => 'receive', 'pallets' => 2, 'receive_mode' => 'store'])->assertSessionHasNoErrors();
        $post(['kind' => 'send', 'colis' => 6])->assertSessionHasNoErrors();
        $post(['kind' => 'send'])->assertSessionHasErrors('colis');
        $post(['kind' => 'shelf', 'qty' => 0])->assertSessionHasErrors('qty');

        $this->assertSame([40 * 160, 2 * 160 + 1500 + 400 + 800, 0, 1600, 1200], ServiceRequest::orderBy('id')->pluck('amount_cents')->all());
        $this->assertSame(['pending'], ServiceRequest::distinct()->pluck('status')->all());
    }

    public function test_storage_is_billed_per_day_once_confirmed_and_can_be_stopped(): void
    {
        $m = $this->member(['D-04'], '2026-10-01');
        $host = $this->staff('workspace-host');
        $this->actingAs($m, 'member')->post('/afrit6/portal/services', ['kind' => 'shelf', 'qty' => 2, 'date' => '2026-10-20']);
        $r = ServiceRequest::firstOrFail();

        $lines = fn () => collect((new StatementBuilder($m))->build(today())['lines']);
        $this->assertNull($lines()->firstWhere('type', 'shelf'), 'not billed before confirmation');

        $this->actingAs($host, 'web')->post('/afrit6-staff/tasks/'.$r->id.'/confirm');
        $shelf = $lines()->firstWhere('type', 'shelf');
        $this->assertSame(2 * 12 * 50, $shelf['amount_cents']); // 20–31 Oct = 12 days

        Carbon::setTestNow(Carbon::parse('2026-10-25 10:00:00'));
        $this->actingAs($m, 'member')->post('/afrit6/portal/services/'.$r->id.'/stop')->assertRedirect();
        $this->assertSame('2026-10-25', $r->fresh()->ends_on->toDateString());
        $this->assertSame(2 * 6 * 50, $lines()->firstWhere('type', 'shelf')['amount_cents']);

        // Confirmed requests cannot be cancelled by the member; others cannot touch it at all.
        $this->actingAs($m, 'member')->post('/afrit6/portal/services/'.$r->id.'/cancel')->assertSessionHasErrors('request');
        $this->actingAs($this->member(['D-02']), 'member')->post('/afrit6/portal/services/'.$r->id.'/reply', ['body' => 'hi'])->assertNotFound();
    }

    // ------------------------------------------------------------- tasks

    public function test_tasks_follow_the_reassignment_rule(): void
    {
        $ruben = $this->staff('manager', ['first_name' => 'Ruben', 'title' => 'Production manager']);
        $lotte = $this->staff('workspace-host', ['first_name' => 'Lotte', 'title' => 'Afrit 6 host', 'reports_to_id' => $ruben->id]);
        $sofie = $this->staff('workspace-host', ['first_name' => 'Sofie', 'title' => 'Afrit 6 host', 'reports_to_id' => $ruben->id]);
        $emma = $this->staff('workspace-host', ['first_name' => 'Emma', 'title' => 'Afrit 6 cleaner', 'reports_to_id' => $lotte->id]);
        $jens = $this->staff('warehouse', ['first_name' => 'Jens', 'title' => 'Warehouse manager', 'reports_to_id' => $ruben->id]);
        $m = $this->member();
        $t = ServiceRequest::create(['member_id' => $m->id, 'kind' => 'packing', 'date' => '2026-10-20', 'colis' => 10, 'amount_cents' => 2500]);
        $url = '/afrit6-staff/tasks/'.$t->id;

        $this->actingAs($lotte, 'web')->post($url.'/take')->assertRedirect();
        $this->assertSame($lotte->id, $t->fresh()->assignee_id);

        $this->actingAs($lotte, 'web')->post($url.'/reassign', ['assignee_id' => $jens->id])->assertSessionHasErrors('assignee_id');
        $this->actingAs($sofie, 'web')->post($url.'/reassign', ['assignee_id' => $sofie->id])->assertForbidden();   // not hers, not above Lotte
        $this->actingAs($lotte, 'web')->post($url.'/reassign', ['assignee_id' => $emma->id])->assertSessionHasNoErrors(); // reports to Lotte
        $this->actingAs($ruben, 'web')->post($url.'/reassign', ['assignee_id' => $sofie->id])->assertSessionHasNoErrors(); // Ruben is above Emma
        $this->assertSame($sofie->id, $t->fresh()->assignee_id);
        $this->assertSame(3, AuditLog::where('action', 'afrit6.task_assigned')->count());

        $this->actingAs($sofie, 'web')->post($url.'/done')->assertSessionHasErrors('task');       // confirm first
        $this->actingAs($sofie, 'web')->post($url.'/confirm')->assertRedirect();
        $this->actingAs($ruben, 'web')->post($url.'/done')->assertForbidden();                    // only the person who has it
        $this->actingAs($sofie, 'web')->post($url.'/done')->assertRedirect();
        $this->assertSame('done', $t->fresh()->status);
    }

    public function test_asking_the_member_puts_the_task_on_hold_until_the_answer(): void
    {
        $host = $this->staff('workspace-host');
        $m = $this->member();
        $t = ServiceRequest::create(['member_id' => $m->id, 'kind' => 'receive', 'date' => '2026-10-22', 'pallets' => 2, 'receive_mode' => 'shelf24']);

        $this->actingAs($host, 'web')->post('/afrit6-staff/tasks/'.$t->id.'/message', ['body' => 'Which carrier?', 'wait' => 1])->assertRedirect();
        $this->assertSame('waiting', $t->fresh()->status);
        $this->actingAs($m, 'member')->get('/afrit6/portal')->assertSee(__('workspace.portal.question_waiting'));
        $this->actingAs($m, 'member')->get('/afrit6/portal/services')->assertSee('Which carrier?');

        $this->actingAs($m, 'member')->post('/afrit6/portal/services/'.$t->id.'/reply', ['body' => 'DHL Freight'])->assertRedirect();
        $this->assertSame('pending', $t->fresh()->status);
        $this->assertSame(2, $t->messages()->count());

        $this->actingAs($host, 'web')->post('/afrit6-staff/tasks/'.$t->id.'/decline', ['reason' => 'No room on Thursday'])->assertRedirect();
        $this->assertSame('declined', $t->fresh()->status);
        $this->actingAs($m, 'member')->get('/afrit6/portal/services')->assertSee('No room on Thursday');
    }

    public function test_the_numbers_can_be_corrected_and_the_amount_follows(): void
    {
        $host = $this->staff('workspace-host');
        $t = ServiceRequest::create(['member_id' => $this->member()->id, 'kind' => 'send', 'date' => '2026-10-20', 'colis' => 6, 'amount_cents' => 1200]);

        $this->actingAs($host, 'web')->post('/afrit6-staff/tasks/'.$t->id.'/amount', ['colis' => 8, 'pallets' => 1])->assertRedirect();

        $this->assertSame(8 * 200 + 800, $t->fresh()->amount_cents);
        $this->assertTrue(AuditLog::where('action', 'afrit6.task_amount')->exists());
    }

    // --------------------------------------------------------- statement

    public function test_the_contract_month_follows_the_desk_start_and_part_months_are_pro_rata(): void
    {
        $m = $this->member(['D-04'], '2026-10-14');
        DeskRental::create(['member_id' => $m->id, 'space_id' => $this->desk('D-05')->id, 'starts_on' => '2026-10-24', 'monthly_price_cents' => 35000]);
        $builder = new StatementBuilder($m);

        [$start, $end] = $builder->period(today());
        $this->assertSame(['2026-10-14', '2026-11-13'], [$start->toDateString(), $end->toDateString()]);
        [$prev] = $builder->period($start->copy()->subDay());
        $this->assertSame('2026-09-14', $prev->toDateString());

        $bill = $builder->build(today());
        $this->assertSame(35000, $bill['lines'][0]['amount_cents']);
        $this->assertSame((int) round(35000 * 21 / 31), $bill['lines'][1]['amount_cents']); // 24 Oct – 13 Nov
        $this->assertSame($bill['lines'][0]['amount_cents'] + $bill['lines'][1]['amount_cents'], $bill['total_cents']);
    }

    public function test_the_statement_is_saved_once_as_proforma_by_a_manager(): void
    {
        $m = $this->member();
        $url = '/afrit6-staff/members/'.$m->id.'/statements';

        $this->actingAs($this->staff('workspace-host'), 'web')->post($url, ['day' => '2026-10-19'])->assertForbidden();
        $manager = $this->staff('manager');
        $this->actingAs($manager, 'web')->post($url, ['day' => '2026-10-19'])->assertSessionHasNoErrors();
        $this->actingAs($manager, 'web')->post($url, ['day' => '2026-10-19'])->assertSessionHasErrors('day');

        $s = Statement::firstOrFail();
        $this->assertSame(35000, $s->total_cents);
        $this->assertSame('proforma', $s->status);
        $this->assertSame('2026-10-14', $s->period_start->toDateString());
        $this->actingAs($m, 'member')->get('/afrit6/portal/bill')->assertSee('350,00');
    }

    public function test_the_statement_is_made_by_itself_the_day_after_the_contract_month(): void
    {
        $m = $this->member(); // desk from 14 October: the contract month runs 14 Oct – 13 Nov
        $this->assertSame(0, \App\Services\Afrit6\Statements::monthly(Carbon::parse('2026-11-13')));
        $this->assertSame(1, \App\Services\Afrit6\Statements::monthly(Carbon::parse('2026-11-14')));
        $this->assertSame(0, \App\Services\Afrit6\Statements::monthly(Carbon::parse('2026-11-15'))); // once
        $s = Statement::firstOrFail();
        $this->assertSame(['2026-10-14', '2026-11-13', 35000], [$s->period_start->toDateString(), $s->period_end->toDateString(), $s->total_cents]);
        $this->assertNull($s->created_by_id);
        $this->assertSame('check', \App\Models\Finance\PreInvoice::where('source_type', 'afrit6')->value('status'));
        // A missed day is caught up within a week.
        $this->assertSame(1, \App\Services\Afrit6\Statements::monthly(Carbon::parse('2026-12-18')));
    }

    public function test_staff_add_and_end_desks_without_double_booking(): void
    {
        $host = $this->staff('workspace-host');
        $m = $this->member([]);
        $this->member(['B-1'], '2026-10-01');
        $url = '/afrit6-staff/members/'.$m->id.'/desks';

        $this->actingAs($host, 'web')->post($url, ['space_id' => $this->desk('B-1')->id, 'starts_on' => '2026-11-01', 'price' => 350])->assertSessionHasErrors('space_id');
        $this->actingAs($host, 'web')->post($url, ['space_id' => $this->desk('B-2')->id, 'starts_on' => '2026-11-01', 'ends_on' => '2027-04-30', 'price' => 300])->assertSessionHasNoErrors();
        $rental = $m->deskRentals()->firstOrFail();
        $this->assertSame(30000, $rental->monthly_price_cents);

        $this->actingAs($host, 'web')->post($url.'/'.$rental->id.'/end', ['ends_on' => '2027-01-31'])->assertSessionHasNoErrors();
        $this->assertSame('2027-01-31', $rental->fresh()->ends_on->toDateString());
        $this->actingAs($this->staff('sales'), 'web')->post($url, ['space_id' => $this->desk('A-1')->id, 'starts_on' => '2026-11-01', 'price' => 350])->assertForbidden();
    }

    // -------------------------------------------------------- day desks

    public function test_a_free_desk_can_be_booked_for_half_a_day_or_a_day(): void
    {
        $m = $this->member([]);
        $other = $this->member([]);
        $this->member(['D-01'], '2026-10-01'); // D-01 is rented per month
        $post = fn (Member $who, string $desk, string $date, string $part) => $this->actingAs($who, 'member')
            ->post('/afrit6/portal/desks/day', ['space_id' => $this->desk($desk)->id, 'date' => $date, 'part' => $part]);

        $post($m, 'D-03', '2026-10-20', 'am')->assertSessionHasNoErrors();
        $post($other, 'D-03', '2026-10-20', 'pm')->assertSessionHasNoErrors();      // afternoon is still free
        $post($other, 'D-03', '2026-10-20', 'day')->assertSessionHasErrors('space_id'); // clashes with both
        $post($other, 'D-03', '2026-10-20', 'am')->assertSessionHasErrors('space_id');
        $post($m, 'D-01', '2026-10-20', 'day')->assertSessionHasErrors('space_id');     // rented per month
        $post($m, 'D-03', '2026-10-24', 'day')->assertSessionHasErrors('date');         // Saturday
        $post($m, 'D-03', '2026-10-19', 'am')->assertSessionHasErrors('date');          // this morning started at 08:00
        $post($m, 'D-03', '2026-10-19', 'pm')->assertSessionHasNoErrors();              // this afternoon is fine
        $post($m, 'D-04', '2026-10-21', 'day')->assertSessionHasNoErrors();

        $this->assertSame([3000, 3000, 3000, 5000], DeskBooking::orderBy('id')->pluck('price_cents')->all());
        $bill = collect((new StatementBuilder($m))->build(today())['lines'])->where('type', 'desk_day');
        $this->assertSame(3000 + 3000 + 5000, $bill->sum('amount_cents'));

        // The free desk list leaves out what is taken.
        $this->actingAs($other, 'member')->get('/afrit6/portal/desks?day=2026-10-20&part=day')
            ->assertOk()->assertDontSee('name="space_id" value="'.$this->desk('D-03')->id.'"', false);
    }

    public function test_a_day_booking_can_be_cancelled_until_it_starts(): void
    {
        $m = $this->member([]);
        $this->actingAs($m, 'member')->post('/afrit6/portal/desks/day', ['space_id' => $this->desk('B-2')->id, 'date' => '2026-10-19', 'part' => 'pm']);
        $b = DeskBooking::firstOrFail();

        Carbon::setTestNow(Carbon::parse('2026-10-19 13:05:00'));
        $this->actingAs($m, 'member')->post('/afrit6/portal/desks/day/'.$b->id.'/cancel')->assertSessionHasErrors('booking');
        $this->actingAs($this->member([]), 'member')->post('/afrit6/portal/desks/day/'.$b->id.'/cancel')->assertNotFound();

        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00'));
        $this->actingAs($m, 'member')->post('/afrit6/portal/desks/day/'.$b->id.'/cancel')->assertSessionHasNoErrors();
        $this->assertNotNull($b->fresh()->cancelled_at);
        $this->assertSame([], (new StatementBuilder($m))->build(today())['lines']);
    }

    public function test_the_private_office_is_a_price_request_and_staff_can_rent_it_out(): void
    {
        $m = $this->member();
        $host = $this->staff('workspace-host');

        $this->actingAs($m, 'member')->post('/afrit6/portal/office', ['date' => '2026-11-01', 'people' => 3, 'wishes' => '3 desks, a whiteboard'])->assertSessionHasNoErrors();
        $this->actingAs($m, 'member')->post('/afrit6/portal/office', ['date' => '2026-11-01', 'people' => 3])->assertSessionHasErrors('date');
        $r = ServiceRequest::where('kind', 'office')->firstOrFail();
        $this->assertSame([0, 'pending', 3], [$r->amount_cents, $r->status, $r->qty]);
        $this->actingAs($host, 'web')->get('/afrit6-staff/tasks/'.$r->id)->assertOk()->assertSee('3 desks, a whiteboard');

        $office = Space::office();
        $this->actingAs($host, 'web')->post('/afrit6-staff/members/'.$m->id.'/desks', ['space_id' => $office->id, 'starts_on' => '2026-11-01', 'price' => 950])->assertSessionHasNoErrors();
        $this->assertTrue($m->deskRentals()->where('space_id', $office->id)->where('monthly_price_cents', 95000)->exists());
        $this->assertFalse(Space::desks()->pluck('code')->contains('OFFICE'), 'the office is not on the desk map');
    }
}
