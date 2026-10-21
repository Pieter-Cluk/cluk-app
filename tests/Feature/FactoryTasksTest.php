<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Factory\Booking;
use App\Models\Factory\Task;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Factory\Bookings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FactoryTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    private function as(string $role, array $over = []): User
    {
        return User::factory()->create($over + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function booking(string $level = 'advanced', array $over = []): Booking
    {
        $b = Booking::create($over + [
            'number' => Booking::nextNumber(), 'status' => 'option', 'source' => 'staff', 'title' => 'Product launch', 'event_type' => 'launch',
            'start_date' => today()->addDays(20), 'days' => 1, 'buildup_days' => 1, 'breakdown_days' => 0, 'size' => 'L', 'level' => $level,
            'guests' => 250, 'language' => 'nl', 'req_company' => 'Demo Motors NV', 'req_name' => 'Ann Peeters', 'req_email' => 'ann@demomotors.be',
            'option_until' => now()->addDays(3), 'load_in_time' => '07:30', 'doors_time' => '18:00', 'end_time' => '01:00',
        ]);
        Bookings::confirm($b);

        return $b->fresh();
    }

    public function test_confirming_makes_the_standard_tasks_for_the_level(): void
    {
        $b = $this->booking('advanced');
        $this->assertSame(['blackbox', 'rigging', 'gear', 'cleaning', 'reset'], $b->tasks->pluck('kind')->all());
        $this->assertSame(today()->addDays(19)->setTime(8, 0)->toDateTimeString(), $b->tasks->firstWhere('kind', 'blackbox')->due_at->toDateTimeString());
        $this->assertSame(today()->addDays(21)->setTime(9, 0)->toDateTimeString(), $b->tasks->firstWhere('kind', 'cleaning')->due_at->toDateTimeString());

        $this->assertSame(4, $this->booking('basic')->tasks->count());
        $this->assertContains('hospitality', $this->booking('allin', ['start_date' => today()->addDays(40)])->tasks->pluck('kind')->all());
    }

    public function test_tasks_follow_date_and_level_changes_but_done_ones_stay(): void
    {
        $b = $this->booking('advanced');
        $sales = $this->as('sales');
        $b->tasks->firstWhere('kind', 'rigging')->update(['assignee_id' => $sales->id, 'done_at' => now(), 'done_by_id' => $sales->id]);
        $rigDue = $b->tasks->firstWhere('kind', 'rigging')->due_at->toDateTimeString();

        $this->actingAs($sales)->put('/factory/bookings/'.$b->id, [
            'title' => 'Product launch', 'event_type' => 'launch', 'guests' => 250, 'start_date' => today()->addDays(30)->toDateString(), 'days' => 1,
            'buildup_days' => 1, 'breakdown_days' => 0, 'size' => 'L', 'level' => 'basic', 'language' => 'nl', 'load_in_time' => '07:30', 'doors_time' => '19:00', 'end_time' => '00:30',
        ])->assertRedirect();
        $b->refresh();
        $this->assertSame('19:00', $b->doors_time);
        $this->assertNull($b->tasks->firstWhere('kind', 'gear'));
        $this->assertSame(today()->addDays(29)->setTime(8, 0)->toDateTimeString(), $b->tasks->firstWhere('kind', 'blackbox')->due_at->toDateTimeString());
        $this->assertSame($rigDue, $b->tasks->firstWhere('kind', 'rigging')->due_at->toDateTimeString());
    }

    public function test_take_hand_over_and_done_follow_the_rules(): void
    {
        $b = $this->booking();
        $task = $b->tasks->firstWhere('kind', 'blackbox');
        $boss = $this->as('manager');
        $ruben = $this->as('sales', ['title' => 'Venue crew', 'reports_to_id' => $boss->id]);
        $jens = $this->as('sales', ['title' => 'Venue crew']);
        $elke = $this->as('sales', ['title' => 'Account manager']);
        $reader = $this->as('read-only');

        $this->actingAs($reader)->post('/factory/tasks/'.$task->id.'/take')->assertForbidden();
        $this->actingAs($ruben)->post('/factory/tasks/'.$task->id.'/take')->assertRedirect();
        $this->assertSame($ruben->id, $task->fresh()->assignee_id);

        // Not his task, not above Ruben: no taking over, no done.
        $this->actingAs($elke)->post('/factory/tasks/'.$task->id.'/take')->assertForbidden();
        $this->actingAs($elke)->post('/factory/tasks/'.$task->id.'/done')->assertForbidden();
        // Ruben can hand it to someone with the same role and job title, not to another job title.
        $this->actingAs($ruben)->post('/factory/tasks/'.$task->id.'/reassign', ['assignee_id' => $elke->id])->assertSessionHasErrors('assignee_id');
        $this->actingAs($ruben)->post('/factory/tasks/'.$task->id.'/reassign', ['assignee_id' => $jens->id])->assertSessionHasNoErrors();
        $this->assertSame($jens->id, $task->fresh()->assignee_id);
        // The manager above Jens? No: Jens does not report to the manager, so the manager cannot move it.
        $this->actingAs($boss)->post('/factory/tasks/'.$task->id.'/reassign', ['assignee_id' => $ruben->id])->assertForbidden();

        $this->actingAs($jens)->post('/factory/tasks/'.$task->id.'/done', ['note' => 'Draping up'])->assertRedirect();
        $task->refresh();
        $this->assertTrue($task->isDone());
        $this->assertSame($jens->id, $task->done_by_id);
        $this->assertSame('Draping up', $task->note);
        $this->assertSame(1, AuditLog::where('action', 'factory.task_done')->count());
        $this->assertSame(2, AuditLog::where('action', 'factory.task_assigned')->count());
    }

    public function test_staff_add_and_remove_their_own_tasks_and_cancelling_drops_open_ones(): void
    {
        $b = $this->booking();
        $sales = $this->as('sales');
        $this->actingAs($sales)->post('/factory/bookings/'.$b->id.'/tasks', ['title' => 'Pick up the brand banners', 'due_date' => today()->addDays(3)->toDateString(), 'due_time' => '14:00', 'assignee_id' => $sales->id])->assertRedirect();
        $own = Task::whereNull('kind')->firstOrFail();
        $this->assertSame($sales->id, $own->assignee_id);
        $this->actingAs($sales)->get('/dashboard')->assertSee('Cluk Factory crew tasks this week', false);
        $this->actingAs($sales)->delete('/factory/tasks/'.$b->tasks()->whereNotNull('kind')->value('id'))->assertStatus(409);
        $this->actingAs($sales)->delete('/factory/tasks/'.$own->id)->assertRedirect();

        $this->actingAs($sales)->post('/factory/bookings/'.$b->id.'/status', ['to' => 'cancelled'])->assertRedirect();
        $this->assertSame(0, $b->tasks()->count());
    }

    public function test_equipment_quote_becomes_an_order_without_prices_or_customer_mail(): void
    {
        $b = $this->booking();
        $sales = $this->as('sales');
        $warehouse = $this->as('warehouse');
        $this->actingAs($warehouse)->post('/factory/bookings/'.$b->id.'/gear')->assertForbidden();

        $this->actingAs($sales)->post('/factory/bookings/'.$b->id.'/gear')->assertRedirect();
        $quote = $b->fresh()->gearQuote;
        $this->assertSame('Materiaal · Product launch', $quote->title);
        $this->assertSame($b->firstDay()->setTime(7, 30)->toDateTimeString(), $quote->out_from->toDateTimeString());
        $this->assertSame(config('factory.address'), $quote->location);

        $this->actingAs($sales)->post('/factory/bookings/'.$b->id.'/gear/included')->assertSessionHasErrors('gear');
        $item = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'category' => 'Light', 'serial' => false, 'stock_qty' => 8, 'day_price_cents' => 6500, 'price_unit' => 'day']);
        $this->actingAs($sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => $item->number, 'qty' => 6])->assertRedirect();
        $this->assertGreaterThan(0, $quote->fresh()->net_cents);

        $this->actingAs($sales)->post('/factory/bookings/'.$b->id.'/gear/included')->assertRedirect('/factory/bookings/'.$b->id);
        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame(0, $quote->net_cents);
        $order = Order::firstOrFail();
        $this->assertSame($quote->id, $order->quote_id);
        $this->assertSame('allocation', $order->status);
        $this->assertSame(6.0, (float) $order->lines()->value('qty'));
        $this->assertSame('confirmed', $b->fresh()->status);
        Notification::assertNotSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n) => str_contains($n->subject, 'Materiaal'));
        $this->actingAs($sales)->get('/factory/bookings/'.$b->id)->assertSee($order->number);
    }

    public function test_venue_documents_in_the_customer_language(): void
    {
        $b = $this->booking();
        $sales = $this->as('sales');
        $this->actingAs($sales)->get('/factory/bookings/'.$b->id.'/print')->assertOk()->assertSee('BEVESTIGING ZAALBOEKING')->assertSee('Huisregels')->assertSee('Maximaal 250 gasten')->assertSee('07:30');
        $this->actingAs($sales)->get('/factory/bookings/'.$b->id.'/print?type=pf')->assertNotFound();

        $req = $this->booking('basic', ['start_date' => today()->addDays(50), 'status' => 'request']);
        $quote = Bookings::makeQuote($req->fresh(), $sales);
        $quote->forceFill(['status' => 'accepted'])->save();
        $this->actingAs($sales)->get('/factory/bookings/'.$req->id.'/print?type=pf')->assertOk()->assertSee('Proforma')->assertSee('F-PF-');
    }

    public function test_pages_in_every_language(): void
    {
        $b = $this->booking('allin');
        $admin = $this->as('admin');
        Bookings::gearQuote($b, $admin);
        $b->tasks()->first()->update(['assignee_id' => $admin->id]);
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/factory', '/factory?tasks=mine', '/factory/bookings/'.$b->id, '/factory/bookings/'.$b->id.'/edit', '/dashboard'] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(factory|workspace|orders)\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags($html), "{$url} ({$locale})");
            }
            $b->forceFill(['language' => $locale])->save();
            $html = $this->get('/factory/bookings/'.$b->id.'/print')->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\b(factory|docs|quotes)\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags($html), "print ({$locale})");
        }
    }
}
