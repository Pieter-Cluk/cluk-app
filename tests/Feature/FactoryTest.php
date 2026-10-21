<?php

namespace Tests\Feature;

use App\Models\Factory\Booking;
use App\Models\Rental\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function form(array $over = []): array
    {
        return $over + [
            'event_type' => 'launch', 'guests' => 250, 'start_date' => today()->addMonth()->toDateString(), 'days' => 1, 'buildup_days' => 1, 'breakdown_days' => 0,
            'size' => 'L', 'level' => 'advanced', 'extras' => ['bus'], 'note' => 'New SUV', 'req_company' => 'Demo Motors NV', 'req_vat' => 'BE0123456749',
            'req_name' => 'Ann Peeters', 'req_email' => 'ann@demomotors.be', 'req_phone' => '+32 9 000 00 00', 'action' => 'request',
        ];
    }

    public function test_the_booking_page_works_in_every_language(): void
    {
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $html = $this->get('/book?lang='.$locale)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\b(factory|mails)\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags(preg_replace('#<script.*?</script>#s', '', $html)), $locale);
        }
        $this->get('/book?lang=en')->assertSee('€ 3.000,00')->assertSee('Request a quote');
    }

    public function test_a_request_from_the_page_is_confirmed_by_email_and_does_not_hold_the_hall(): void
    {
        $this->post('/book', $this->form())->assertRedirect('/book');
        $b = Booking::firstOrFail();
        $this->assertSame('request', $b->status);
        $this->assertSame('F-'.now()->year.'-0001', $b->number);
        $this->assertSame(300000 + 200000, $b->estimateCents());
        $this->assertFalse($b->holds());
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'ann@demomotors.be' && str_contains($n->subject, 'received your Cluk Factory request'));
        $this->get('/book')->assertSee('Your request is with our Factory team');
    }

    public function test_holding_a_date_blocks_it_including_build_up_until_the_option_ends(): void
    {
        $date = today()->addMonth();
        $this->post('/book', $this->form(['action' => 'hold', 'start_date' => $date->toDateString()]))->assertRedirect();
        $b = Booking::firstOrFail();
        $this->assertSame('option', $b->status);
        $this->assertTrue($b->holds());
        $this->assertSame(today()->addDays(3)->setTime(17, 0)->toDateTimeString(), $b->option_until->toDateTimeString());

        // The build-up day before is taken too.
        $this->post('/book', $this->form(['start_date' => $date->copy()->subDay()->toDateString(), 'buildup_days' => 0, 'req_email' => 'x@y.be']))->assertSessionHasErrors('start_date');
        // The day after is free.
        $this->post('/book', $this->form(['start_date' => $date->copy()->addDay()->toDateString(), 'buildup_days' => 0, 'req_email' => 'x@y.be']))->assertSessionHasNoErrors();

        // Once the option ran out, the date is free again.
        $b->update(['option_until' => now()->subMinute()]);
        $this->assertFalse($b->fresh()->holds());
        $this->post('/book', $this->form(['start_date' => $date->toDateString(), 'buildup_days' => 0, 'req_email' => 'z@y.be']))->assertSessionHasNoErrors();
    }

    public function test_robots_filling_the_hidden_field_are_ignored(): void
    {
        $this->post('/book', $this->form(['website' => 'http://spam']))->assertRedirect('/book');
        $this->assertSame(0, Booking::count());
    }

    public function test_from_request_to_quote_to_confirmed_booking(): void
    {
        $this->post('/book', $this->form(['days' => 2]));
        $b = Booking::firstOrFail();
        $sales = $this->as('sales');

        $this->actingAs($sales)->post('/factory/bookings/'.$b->id.'/quote')->assertRedirect();
        $b->refresh();
        $quote = $b->quote;
        $this->assertSame('factory', $b->deal->module);
        $this->assertSame('Demo Motors NV', $b->company->name);
        $this->assertSame('BE0123456749', $b->company->vat_number);
        $this->assertSame('ann@demomotors.be', $b->contact->email);
        $this->assertSame(['CF-L', 'CF-ADVANCED'], $quote->lines()->where('kind', 'item')->pluck('number')->all());
        $this->assertSame(2 * 300000 + 200000, $quote->net_cents); // space × 2 days (no rental factor) + Advanced
        $this->assertSame(3, $quote->lines()->where('kind', 'charge')->count() + 1); // build-up line + tour bus

        $this->actingAs($sales)->post('/sales/quotes/'.$quote->id.'/send', ['to' => 'ann@demomotors.be'])->assertRedirect();
        auth('web')->logout();
        $this->post('/q/'.$quote->fresh()->token.'/accept', ['name' => 'Ann Peeters', 'terms' => '1'])->assertRedirect();

        $b->refresh();
        $this->assertSame('confirmed', $b->status);
        $this->assertTrue($b->holds());
        $this->assertSame(0, Order::count()); // a venue booking is not a rental order
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n) => str_contains($n->subject, 'booking is confirmed'));
        Notification::assertNotSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n) => str_starts_with($n->subject, 'We received your confirmation'));
    }

    public function test_decline_and_permissions(): void
    {
        $this->post('/book', $this->form());
        $b = Booking::firstOrFail();
        $reader = $this->as('read-only');
        $this->actingAs($reader)->get('/factory')->assertOk()->assertSee('Demo Motors NV');
        $this->actingAs($reader)->post('/factory/bookings/'.$b->id.'/decline', ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->as('sales'))->post('/factory/bookings/'.$b->id.'/confirm')->assertForbidden(); // level 3 only
        $this->actingAs($this->as('warehouse'))->get('/factory/bookings/new')->assertForbidden();

        $this->actingAs($this->as('sales'))->post('/factory/bookings/'.$b->id.'/decline', ['reason' => 'Hall closed for maintenance'])->assertRedirect('/factory');
        $this->assertSame('declined', $b->fresh()->status);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n) => in_array('Hall closed for maintenance', $n->details, true));
    }

    public function test_staff_pages_in_every_language(): void
    {
        $this->post('/book', $this->form(['action' => 'hold']));
        $b = Booking::firstOrFail();
        $admin = $this->as('admin');
        $this->actingAs($admin)->post('/factory/bookings', ['title' => 'Phone booking', 'event_type' => 'wedding', 'guests' => 120, 'start_date' => today()->addMonths(2)->toDateString(), 'days' => 1, 'buildup_days' => 0, 'breakdown_days' => 1, 'size' => 'M', 'level' => 'basic', 'language' => 'nl'])->assertRedirect();
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/factory', '/factory?m='.$b->start_date->format('Y-m'), '/factory/bookings/'.$b->id, '/factory/bookings/'.$b->id.'/edit', '/factory/bookings/new', '/dashboard'] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(factory|deals|quotes)\.[a-z_]+(\.[a-z_]+)*\b/', strip_tags($html), "{$url} ({$locale})");
            }
        }
        $this->actingAs($admin)->get('/factory?m='.$b->start_date->format('Y-m'))->assertSee('/factory/bookings/'.$b->id);
    }
}
