<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Notifications\QuoteAnswered;
use App\Services\Rental\Availability;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuotesTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->sales = $this->as('sales');
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function company(array $attrs = []): Company
    {
        return Company::create($attrs + ['name' => 'Demo Agency BV', 'country' => 'BE', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
    }

    private function spiider(int $stock = 10): Item
    {
        return Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'category' => 'Light', 'serial' => false, 'stock_qty' => $stock, 'day_price_cents' => 6500, 'price_unit' => 'day', 'catalog_price_cents' => 985000, 'weight_kg' => 13.5]);
    }

    /** A deal with a Belgian company and a contact, used 14–15 Nov (2 days). */
    private function deal(?Company $company = null): Deal
    {
        $company ??= $this->company();
        $contact = Contact::create(['first_name' => 'Sarah', 'last_name' => 'Peeters', 'email' => 'sarah@demo.be', 'language' => 'en']);
        $company->contacts()->attach($contact->id);

        return Deal::create(['number' => Deal::nextNumber(), 'title' => 'Company evening', 'module' => 'dryhire', 'stage' => 'qualified', 'company_id' => $company->id, 'contact_id' => $contact->id, 'owner_id' => $this->sales->id, 'event_start' => '2026-11-14', 'event_end' => '2026-11-15', 'check_date' => true, 'check_budget' => true, 'check_decider' => true]);
    }

    private function quote(?Deal $deal = null): Quote
    {
        $deal ??= $this->deal();
        $this->actingAs($this->sales)->post('/sales/deals/'.$deal->id.'/quotes')->assertRedirect();

        return Quote::latest('id')->firstOrFail();
    }

    private function sendIt(Quote $quote): Quote
    {
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/send', ['to' => 'sarah@demo.be'])->assertRedirect();

        return $quote->fresh();
    }

    public function test_factor_follows_the_days_used(): void
    {
        $this->assertSame(1.0, Quote::factorFor(1));
        $this->assertSame(1.5, Quote::factorFor(2));
        $this->assertSame(1.5, Quote::factorFor(7));
        $this->assertSame(2.5, Quote::factorFor(8));
        $this->assertSame(2.5, Quote::factorFor(14));
        $this->assertSame(3.0, Quote::factorFor(21));
        $this->assertSame(3.5, Quote::factorFor(22));
        $this->assertSame(4.0, Quote::factorFor(35));
    }

    public function test_new_quote_from_a_deal_takes_customer_and_dates(): void
    {
        $quote = $this->quote();
        $this->assertSame('R-QUO-'.now()->year.'-0001', $quote->number);
        $this->assertSame(1, $quote->version);
        $this->assertSame('draft', $quote->status);
        $this->assertSame('2026-11-13 14:00', $quote->out_from->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 10:00', $quote->out_until->format('Y-m-d H:i'));
        $this->assertSame(2, $quote->usedDays());
        $this->assertSame('en', $quote->language);
        $this->assertSame(1, $quote->deal->activities()->count());
    }

    public function test_lines_prices_factor_discounts_and_vat(): void
    {
        $this->spiider();
        $quote = $this->quote();

        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 8])->assertSessionHasNoErrors();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'charge', 'name' => 'Delivery and collection', 'qty' => 1, 'price' => '95'])->assertSessionHasNoErrors();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'text', 'name' => 'Light'])->assertSessionHasNoErrors();
        $quote->refresh();
        // 8 × 65 × 1.5 = 780, + 95 (charge, factor 1) = 875; VAT 21%
        $this->assertSame(87500, $quote->net_cents);
        $this->assertSame(2100, $quote->vat_rate_bp);
        $this->assertSame(18375, $quote->vat_cents);
        $this->assertSame(105875, $quote->gross_cents);

        // Adding the same item again raises the qty.
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 2]);
        $line = $quote->lines()->where('kind', 'item')->first();
        $this->assertSame('10.00', $line->qty);

        // Manual factor 1.2, 10% line discount, 3% project discount.
        $charge = $quote->lines()->where('kind', 'charge')->first();
        $text = $quote->lines()->where('kind', 'text')->first();
        $this->actingAs($this->sales)->put('/sales/quotes/'.$quote->id, [
            'title' => 'Company evening', 'language' => 'en', 'out_from' => '2026-11-13T14:00', 'out_until' => '2026-11-16T10:00',
            'use_from' => '2026-11-14', 'use_until' => '2026-11-15', 'project_discount' => '3',
            'lines' => [
                $line->id => ['name' => 'Robe Spiider', 'qty' => 10, 'factor' => '1.2', 'discount' => '10', 'price' => '65.00'],
                $charge->id => ['name' => 'Delivery and collection', 'qty' => 1, 'factor' => '', 'price' => '95'],
                $text->id => ['name' => 'Light', 'qty' => 0],
            ],
        ])->assertSessionHasNoErrors();
        $quote->refresh();
        // 10 × 65 × 1.2 × 0.9 = 702 + 95 = 797; −3% = 773.09
        $this->assertSame(79700, $quote->subtotal_cents);
        $this->assertSame(2391, $quote->discount_cents);
        $this->assertSame(77309, $quote->net_cents);
    }

    public function test_use_days_must_fall_inside_the_out_period(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->sales)->put('/sales/quotes/'.$quote->id, [
            'title' => 'X', 'language' => 'en', 'out_from' => '2026-11-13T14:00', 'out_until' => '2026-11-16T10:00',
            'use_from' => '2026-11-12', 'use_until' => '2026-11-15',
        ])->assertSessionHasErrors('use_from');
    }

    public function test_vat_follows_the_customer(): void
    {
        $this->spiider();
        $eu = $this->company(['name' => 'Agentur GmbH', 'country' => 'DE']);
        $eu->forceFill(['vies_status' => 'valid'])->save();
        $quote = $this->quote($this->deal($eu));
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 1]);
        $quote->refresh();
        $this->assertSame('46', $quote->vat_code);
        $this->assertSame(0, $quote->vat_cents);

        // A private person (no company) pays Belgian VAT.
        $deal = Deal::create(['number' => Deal::nextNumber(), 'title' => 'Wedding', 'module' => 'dryhire', 'stage' => 'new', 'owner_id' => $this->sales->id, 'event_start' => '2026-12-05']);
        $private = $this->quote($deal);
        $this->actingAs($this->sales)->post('/sales/quotes/'.$private->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 1]);
        $this->assertSame(2100, $private->fresh()->vat_rate_bp);
        $this->assertSame(6500, $private->fresh()->net_cents); // one day: factor 1
    }

    public function test_sent_and_accepted_quotes_hold_equipment_on_overlapping_dates(): void
    {
        Notification::fake();
        $item = $this->spiider(10);
        $first = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$first->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 6]);

        $second = $this->quote();
        $avail = fn () => new Availability($second->out_from, $second->out_until, $second->number);
        $this->assertSame(10, $avail()->free($item)); // a draft holds nothing

        $this->sendIt($first);
        $this->assertSame(4, $avail()->free($item)); // sent: held during the option

        $first->forceFill(['reserved_until' => now()->subMinute()])->save();
        $this->assertSame(10, $avail()->free($item)); // option ran out

        $first->forceFill(['status' => 'accepted'])->save();
        $this->assertSame(4, $avail()->free($item)); // accepted: booked

        // The editor shows the shortage.
        $this->actingAs($this->sales)->post('/sales/quotes/'.$second->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 6]);
        $this->actingAs($this->sales)->get('/sales/quotes/'.$second->id)->assertOk()->assertSee('only 4 free');

        // Other dates are not affected.
        $later = new Availability(now()->addYears(2), now()->addYears(2)->addDays(2));
        $this->assertSame(10, $later->free($item));
    }

    public function test_send_freezes_the_quote_and_moves_the_deal(): void
    {
        Notification::fake();
        $this->spiider();
        $quote = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 2]);
        $quote = $this->sendIt($quote);

        $this->assertSame('sent', $quote->status);
        $this->assertSame(48, strlen($quote->token));
        $this->assertSame(today()->addDays(30)->toDateString(), $quote->valid_until->toDateString());
        $this->assertSame(today()->addDays(3)->setTime(17, 0)->toDateTimeString(), $quote->reserved_until->toDateTimeString());
        $this->assertSame('proposal', $quote->deal->stage);
        $this->assertSame(19500, $quote->deal->value_cents);
        $this->assertTrue(AuditLog::where('action', 'quote.sent')->exists());
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $ch, $notifiable) => $notifiable->routes['mail'] === 'sarah@demo.be' && str_contains($n->subject, 'Company evening') && $n->signer?->is($this->sales));

        // Frozen: no more edits.
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 1])->assertStatus(409);
    }

    public function test_customer_accepts_with_po_and_the_deal_is_won(): void
    {
        Notification::fake();
        $this->spiider();
        $quote = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 2]);
        $quote = $this->sendIt($quote);
        auth('web')->logout();

        $this->get('/q/'.$quote->token)->assertOk()->assertSee('Company evening')->assertSee('Accept quote');
        $this->post('/q/'.$quote->token.'/accept', ['name' => 'Sarah Peeters', 'po_number' => 'PO 4500-9031'])->assertSessionHasErrors('terms');
        $this->post('/q/'.$quote->token.'/accept', ['name' => 'Sarah Peeters', 'po_number' => 'PO 4500-9031', 'terms' => '1'])->assertRedirect('/q/'.$quote->token);

        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame('PO 4500-9031', $quote->po_number);
        $this->assertSame('won', $quote->deal->stage);
        $this->assertTrue(AuditLog::where('action', 'quote.accepted')->whereNull('actor_id')->exists());
        Notification::assertSentTo($this->sales, QuoteAnswered::class);
        // The customer gets a confirmation of what they did, with the PO.
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n) => str_starts_with($n->subject, 'We received your confirmation') && in_array('PO 4500-9031', $n->details, true));

        // Answering twice is not possible.
        $this->post('/q/'.$quote->token.'/decline', ['reason' => 'price'])->assertStatus(409);
        $this->get('/q/'.$quote->token)->assertOk()->assertDontSee('Accept quote');
    }

    public function test_customer_asks_a_change_then_gets_a_new_version(): void
    {
        Notification::fake();
        $this->spiider();
        $quote = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 2]);
        $quote = $this->sendIt($quote);

        $this->post('/q/'.$quote->token.'/change', ['message' => 'Can we have 4 more?'])->assertRedirect();
        $this->assertSame('change', $quote->fresh()->status);
        $this->assertStringContainsString('Can we have 4 more?', $quote->deal->activities()->latest('id')->value('body'));

        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/revise')->assertRedirect();
        $v2 = Quote::where('number', $quote->number)->where('version', 2)->firstOrFail();
        $this->assertSame('draft', $v2->status);
        $this->assertSame(1, $v2->lines()->count());
        $this->assertSame('replaced', $quote->fresh()->status);
        // The old link points to the newer version once that is sent.
        $v2 = $this->sendIt($v2);
        $this->get('/q/'.$quote->token)->assertOk()->assertSee('/q/'.$v2->token, false);
    }

    public function test_customer_declines_with_a_reason(): void
    {
        Notification::fake();
        $this->spiider();
        $quote = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 2]);
        $quote = $this->sendIt($quote);

        $this->post('/q/'.$quote->token.'/decline', ['reason' => 'competitor', 'note' => 'Cheaper elsewhere'])->assertRedirect();
        $quote->refresh();
        $this->assertSame('declined', $quote->status);
        $this->assertNull($quote->reserved_until);
        $this->assertSame('proposal', $quote->deal->stage); // the salesperson decides about the deal
    }

    public function test_an_expired_quote_cannot_be_accepted(): void
    {
        Notification::fake();
        $this->spiider();
        $quote = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 1]);
        $quote = $this->sendIt($quote);
        $quote->forceFill(['valid_until' => today()->subDay()])->save();

        $this->get('/q/'.$quote->token)->assertOk()->assertSee('was valid until');
        $this->post('/q/'.$quote->token.'/accept', ['name' => 'Sarah', 'terms' => '1'])->assertStatus(409);
    }

    public function test_drafts_and_unknown_links_are_not_public(): void
    {
        $quote = $this->quote();
        $quote->forceFill(['token' => str_repeat('a', 48)])->save();
        $this->get('/q/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/q/'.str_repeat('b', 48))->assertNotFound();
    }

    public function test_staff_can_record_an_acceptance_by_phone(): void
    {
        Notification::fake();
        $this->spiider();
        $quote = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 1]);
        $quote = $this->sendIt($quote);

        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/accepted', ['name' => 'Sarah Peeters', 'po_number' => 'PO 1'])->assertRedirect();
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame($this->sales->id, AuditLog::where('action', 'quote.accepted')->value('actor_id'));
        Notification::assertNotSentTo($this->sales, QuoteAnswered::class);
    }

    public function test_staff_can_record_a_decline_by_phone(): void
    {
        Notification::fake();
        $this->spiider();
        $quote = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 1]);
        $quote = $this->sendIt($quote);
        $this->actingAs($this->sales)->get('/sales/quotes/'.$quote->id)->assertOk()->assertSee('Mark as declined');

        $this->actingAs($this->sales)->post(route('quotes.declined', $quote), ['reason' => 'nonsense'])->assertSessionHasErrors('reason');
        $this->actingAs($this->sales)->post(route('quotes.declined', $quote), ['reason' => 'price', 'note' => 'Too expensive'])->assertRedirect();
        $quote->refresh();
        $this->assertSame(['declined', 'price', 'Too expensive'], [$quote->status, $quote->decline_reason, $quote->answer_note]);
        $this->assertSame($this->sales->id, AuditLog::where('action', 'quote.declined')->value('actor_id'));
        Notification::assertNotSentTo($this->sales, QuoteAnswered::class);

        // Only once: a declined quote cannot be declined again.
        $this->actingAs($this->sales)->post(route('quotes.declined', $quote), ['reason' => 'price'])->assertStatus(409);
    }

    public function test_permissions(): void
    {
        $quote = $this->quote();
        $reader = $this->as('read-only');
        $this->actingAs($reader)->get('/sales/quotes/'.$quote->id)->assertOk()->assertDontSee('Send to');
        $this->actingAs($reader)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'text', 'name' => 'X'])->assertForbidden();

        $colleague = $this->as('sales');
        $this->actingAs($colleague)->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'text', 'name' => 'X'])->assertForbidden();
        $this->actingAs($this->as('manager'))->post('/sales/quotes/'.$quote->id.'/lines', ['kind' => 'text', 'name' => 'X'])->assertRedirect();
    }

    public function test_pages_work_in_every_language_without_missing_texts(): void
    {
        Notification::fake();
        $this->spiider();
        $draft = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$draft->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 2]);
        $this->actingAs($this->sales)->post('/sales/quotes/'.$draft->id.'/lines', ['kind' => 'text', 'name' => 'Light']);
        $sent = $this->quote();
        $this->actingAs($this->sales)->post('/sales/quotes/'.$sent->id.'/lines', ['kind' => 'item', 'number' => 'LT-SPIIDER', 'qty' => 1]);
        $sent = $this->sendIt($sent);
        $admin = $this->as('admin');
        $pattern = '/\b(quotes|deals|equipment|nav)\.[a-z_]+(\.[a-z_]+)*\b/';

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/sales/quotes', '/sales/quotes?status=sent', '/sales/quotes/'.$draft->id, '/sales/quotes/'.$sent->id, '/sales/quotes/'.$draft->id.'/print', '/sales/deals/'.$draft->deal_id] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
            $html = $this->get('/q/'.$sent->token.'?lang='.$locale)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "public ({$locale})");
        }

        $this->actingAs($admin)->getJson('/sales/quotes/'.$draft->id.'/search?q=spii')->assertOk()->assertJsonPath('0.number', 'LT-SPIIDER');
    }
}
