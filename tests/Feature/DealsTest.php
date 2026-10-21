<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\Sales\IncomingMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function deal(array $attrs = []): Deal
    {
        return Deal::create($attrs + ['number' => Deal::nextNumber(), 'title' => 'Sound for a party', 'module' => 'dryhire', 'stage' => 'new', 'value_cents' => 250000]);
    }

    public function test_pages_work_in_every_language_without_missing_texts(): void
    {
        $company = Company::create(['name' => 'Acme', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $deal = $this->deal(['company_id' => $company->id]);
        $deal->log('note', 'Called them');
        $won = $this->deal(['stage' => 'lost', 'lost_reason' => 'competitor', 'competitor' => 'Other BV', 'closed_at' => now()]);
        $item = IncomingMessage::create(['channel' => 'website', 'subject' => 'Need speakers', 'from_email' => 'a@b.be', 'status' => 'new', 'received_at' => now()]);
        $admin = $this->as('admin');
        $pattern = '/\b(deals|nav|equipment)\.[a-z_]+(\.[a-z_]+)*\b/';

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/sales', '/sales?view=closed', '/sales/incoming', '/sales/incoming?open='.$item->id, '/sales/incoming?status=handled', '/sales/deals/'.$deal->id, '/sales/deals/'.$won->id, '/sales/deals/new', '/dashboard'] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
        }
    }

    public function test_board_shows_open_deals_per_stage_with_totals(): void
    {
        $this->deal(['title' => 'Alpha', 'value_cents' => 100000]);
        $this->deal(['title' => 'Beta', 'stage' => 'proposal', 'value_cents' => 200000, 'check_date' => true, 'check_budget' => true, 'check_decider' => true]);
        $this->deal(['title' => 'Gone', 'stage' => 'lost', 'closed_at' => now()]);

        $this->actingAs($this->as('sales'))->get('/sales')->assertOk()
            ->assertSee('Alpha')->assertSee('Beta')->assertDontSee('Gone')
            ->assertSee('2 open deals');
        // weighted: 1000 × 10% + 2000 × 50% = 1100
        $this->assertSame(110000, Deal::open()->get()->sum(fn ($d) => $d->weightedCents()));
    }

    public function test_access_by_level(): void
    {
        $deal = $this->deal();
        $this->actingAs($this->as('warehouse'))->get('/sales')->assertForbidden();
        $reader = $this->as('read-only');
        $this->actingAs($reader)->get('/sales/deals/'.$deal->id)->assertOk()->assertDontSee(route('deals.win', $deal));
        $this->actingAs($reader)->get('/sales/deals/new')->assertForbidden();
        $this->actingAs($reader)->post('/sales/deals/'.$deal->id.'/win')->assertForbidden();
    }

    public function test_sales_level_2_works_on_own_and_unassigned_deals_only(): void
    {
        $sales = $this->as('sales');
        $colleague = $this->as('sales');
        $theirs = $this->deal(['owner_id' => $colleague->id]);
        $free = $this->deal();

        $this->actingAs($sales)->post('/sales/deals/'.$theirs->id.'/checks', ['check_date' => 1])->assertForbidden();
        $this->actingAs($sales)->post('/sales/deals/'.$free->id.'/checks', ['check_date' => 1])->assertRedirect();
        $this->assertTrue($free->fresh()->check_date);

        // Level 2 can take a deal but not hand it to someone else.
        $this->actingAs($sales)->put('/sales/deals/'.$free->id, ['title' => 'X', 'module' => 'dryhire', 'owner_id' => $colleague->id])->assertRedirect();
        $this->assertNull($free->fresh()->owner_id);
        $this->actingAs($sales)->put('/sales/deals/'.$free->id, ['title' => 'X', 'module' => 'dryhire', 'owner_id' => $sales->id])->assertRedirect();
        $this->assertSame($sales->id, $free->fresh()->owner_id);

        // Level 3 (manager) can work on anyone's deal.
        $this->actingAs($this->as('manager'))->post('/sales/deals/'.$theirs->id.'/checks', ['check_budget' => 1])->assertRedirect();
    }

    public function test_create_a_deal(): void
    {
        $sales = $this->as('sales');
        $this->actingAs($sales)->post('/sales/deals', ['title' => 'Led wall', 'module' => 'dryhire', 'source' => 'phone', 'value' => '1234.50', 'event_start' => '2026-12-01', 'event_end' => '2026-12-02'])
            ->assertRedirect();
        $deal = Deal::firstOrFail();
        $this->assertSame('D-'.now()->year.'-0001', $deal->number);
        $this->assertSame(123450, $deal->value_cents);
        $this->assertSame($sales->id, $deal->owner_id);
        $this->assertSame('new', $deal->stage);
        $this->assertSame(1, $deal->activities()->count());
    }

    public function test_a_deal_can_only_move_on_when_qualified(): void
    {
        $sales = $this->as('sales');
        $deal = $this->deal(['owner_id' => $sales->id]);

        $this->actingAs($sales)->from('/sales/deals/'.$deal->id)->post('/sales/deals/'.$deal->id.'/move', ['stage' => 'proposal'])->assertSessionHasErrors('stage');
        $this->assertSame('new', $deal->fresh()->stage);

        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/checks', ['check_date' => 1, 'check_budget' => 1, 'check_decider' => 1]);
        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/move', ['stage' => 'proposal'])->assertSessionHasNoErrors();
        $this->assertSame('proposal', $deal->fresh()->stage);
        $this->assertSame(50, $deal->fresh()->probability());
        $this->assertTrue($deal->activities()->where('kind', 'stage')->exists());
    }

    public function test_win_makes_the_company_a_customer(): void
    {
        $sales = $this->as('sales');
        $supplier = Company::create(['name' => 'Supplier BV', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'nl']);
        $deal = $this->deal(['owner_id' => $sales->id, 'company_id' => $supplier->id]);

        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/win', ['note' => 'Signed'])->assertRedirect();
        $deal->refresh();
        $this->assertSame('won', $deal->stage);
        $this->assertNotNull($deal->closed_at);
        $this->assertSame($sales->id, $deal->closed_by_id);
        $this->assertTrue($supplier->fresh()->is_customer);
        $this->assertTrue($supplier->fresh()->is_supplier);
    }

    public function test_lost_needs_a_reason_and_a_competitor_name_when_it_went_to_a_competitor(): void
    {
        $sales = $this->as('sales');
        $deal = $this->deal(['owner_id' => $sales->id]);

        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/lose', [])->assertSessionHasErrors('reason');
        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/lose', ['reason' => 'competitor'])->assertSessionHasErrors('competitor');
        $this->assertTrue($deal->fresh()->isOpen());

        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/lose', ['reason' => 'competitor', 'competitor' => 'Rent-it'])->assertSessionHasNoErrors();
        $deal->refresh();
        $this->assertSame('lost', $deal->stage);
        $this->assertSame('Rent-it', $deal->competitor);

        // Reopen clears the result.
        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/reopen')->assertRedirect();
        $deal->refresh();
        $this->assertSame('new', $deal->stage);
        $this->assertNull($deal->lost_reason);
        $this->assertNull($deal->closed_at);
    }

    public function test_disqualify_only_early_in_the_pipeline(): void
    {
        $sales = $this->as('sales');
        $early = $this->deal(['owner_id' => $sales->id]);
        $late = $this->deal(['owner_id' => $sales->id, 'stage' => 'proposal', 'check_date' => true, 'check_budget' => true, 'check_decider' => true]);

        $this->actingAs($sales)->post('/sales/deals/'.$late->id.'/disqualify')->assertNotFound();
        $this->actingAs($sales)->post('/sales/deals/'.$early->id.'/disqualify', ['note' => 'Spam'])->assertRedirect();
        $this->assertSame('disqualified', $early->fresh()->stage);
    }

    public function test_notes_and_quick_contact(): void
    {
        $sales = $this->as('sales');
        $company = Company::create(['name' => 'Acme', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $deal = $this->deal(['owner_id' => $sales->id, 'company_id' => $company->id]);

        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/notes', ['kind' => 'call', 'body' => 'Wants 4 tops'])->assertRedirect();
        $this->assertSame('Wants 4 tops', $deal->activities()->where('kind', 'call')->value('body'));

        $this->actingAs($sales)->post('/sales/deals/'.$deal->id.'/contact', ['first_name' => 'Jan', 'last_name' => 'Peeters', 'email' => 'Jan@Acme.be'])->assertRedirect();
        $contact = Contact::where('email', 'jan@acme.be')->firstOrFail();
        $this->assertSame($contact->id, $deal->fresh()->contact_id);
        $this->assertTrue($company->contacts()->whereKey($contact->id)->exists());
    }

    public function test_incoming_from_a_known_contact_is_matched(): void
    {
        $company = Company::create(['name' => 'Acme', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $contact = Contact::create(['first_name' => 'Jan', 'email' => 'jan@acme.be']);
        $company->contacts()->attach($contact->id);

        $this->actingAs($this->as('sales'))->post('/sales/incoming', ['channel' => 'phone', 'subject' => 'Spots for Friday', 'from_email' => 'JAN@acme.be'])->assertRedirect();
        $item = IncomingMessage::firstOrFail();
        $this->assertSame($contact->id, $item->contact_id);
        $this->assertSame($company->id, $item->company_id);
    }

    public function test_convert_incoming_to_a_deal_with_a_new_company(): void
    {
        $sales = $this->as('sales');
        $item = IncomingMessage::create(['channel' => 'website', 'subject' => 'Party tent', 'from_name' => 'Els Claes', 'from_email' => 'els@newco.be', 'company_name' => 'NewCo', 'status' => 'new', 'received_at' => now()]);

        $this->actingAs($sales)->post('/sales/incoming/'.$item->id.'/convert', ['title' => 'Party tent', 'module' => 'factory', 'company' => 'new', 'new_company' => 'NewCo', 'value' => '500'])->assertRedirect();

        $deal = Deal::firstOrFail();
        $company = Company::where('name', 'NewCo')->firstOrFail();
        $this->assertTrue($company->is_customer);
        $this->assertSame($company->id, $deal->company_id);
        $this->assertSame('Els', $deal->contact->first_name);
        $this->assertSame('website', $deal->source);
        $this->assertSame(50000, $deal->value_cents);
        $this->assertSame($item->id, $deal->incoming_id);
        $this->assertSame('converted', $item->fresh()->status);
    }

    public function test_convert_incoming_for_a_private_person_and_an_existing_company(): void
    {
        $sales = $this->as('sales');
        $private = IncomingMessage::create(['channel' => 'instagram', 'subject' => 'Wedding lights', 'from_name' => 'Tom', 'status' => 'new', 'received_at' => now()]);
        $this->actingAs($sales)->post('/sales/incoming/'.$private->id.'/convert', ['title' => 'Wedding', 'module' => 'dryhire', 'company' => 'private'])->assertRedirect();
        $this->assertNull(Deal::where('title', 'Wedding')->value('company_id'));

        $existing = Company::create(['name' => 'Acme', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $item = IncomingMessage::create(['channel' => 'email', 'subject' => 'Desk', 'status' => 'new', 'received_at' => now()]);
        $this->actingAs($sales)->post('/sales/incoming/'.$item->id.'/convert', ['title' => 'Desk', 'module' => 'afrit6', 'company' => (string) $existing->id])->assertRedirect();
        $this->assertSame($existing->id, Deal::where('title', 'Desk')->value('company_id'));
        $this->assertSame(1, Company::count());

        // New company without a name is refused.
        $other = IncomingMessage::create(['channel' => 'email', 'subject' => 'X', 'status' => 'new', 'received_at' => now()]);
        $this->actingAs($sales)->post('/sales/incoming/'.$other->id.'/convert', ['title' => 'X', 'module' => 'dryhire', 'company' => 'new'])->assertSessionHasErrors('new_company');
    }

    public function test_link_ask_and_dismiss(): void
    {
        $sales = $this->as('sales');
        $deal = $this->deal();
        $a = IncomingMessage::create(['channel' => 'email', 'subject' => 'Extra mic please', 'status' => 'new', 'received_at' => now()]);
        $b = IncomingMessage::create(['channel' => 'whatsapp', 'subject' => 'Price?', 'status' => 'new', 'received_at' => now()]);
        $c = IncomingMessage::create(['channel' => 'website', 'subject' => 'Buy cheap pills', 'status' => 'new', 'received_at' => now()]);

        $this->actingAs($sales)->post('/sales/incoming/'.$a->id.'/link', ['deal_id' => $deal->id])->assertRedirect('/sales/deals/'.$deal->id);
        $this->assertSame('linked', $a->fresh()->status);
        $this->assertStringContainsString('Extra mic please', $deal->activities()->latest('id')->value('body'));

        $this->actingAs($sales)->post('/sales/incoming/'.$b->id.'/ask', ['note' => 'Asked which date by WhatsApp'])->assertRedirect();
        $this->assertSame('asked', $b->fresh()->status);

        $this->actingAs($sales)->post('/sales/incoming/'.$c->id.'/dismiss', ['note' => 'Spam'])->assertRedirect();
        $this->assertSame('dismissed', $c->fresh()->status);

        // "Asked" stays in the list to handle, the others move to Handled.
        $this->actingAs($sales)->get('/sales/incoming')->assertSee('Price?')->assertDontSee('Buy cheap pills');
        $this->actingAs($sales)->get('/sales/incoming?status=handled')->assertSee('Buy cheap pills')->assertSee('Extra mic please');
    }

    public function test_read_only_cannot_handle_incoming(): void
    {
        $item = IncomingMessage::create(['channel' => 'email', 'subject' => 'Hi', 'status' => 'new', 'received_at' => now()]);
        $reader = $this->as('read-only');
        $this->actingAs($reader)->get('/sales/incoming')->assertOk();
        $this->actingAs($reader)->post('/sales/incoming/'.$item->id.'/dismiss')->assertForbidden();
    }

    public function test_website_intake_needs_the_token(): void
    {
        $payload = ['subject' => 'Quote for LED wall', 'name' => 'Ann', 'email' => 'ann@x.be', 'message' => '3 days in March', 'module' => 'dryhire'];

        config(['services.intake.token' => null]);
        $this->postJson('/intake', $payload)->assertForbidden();

        config(['services.intake.token' => 'secret-token-123']);
        $this->postJson('/intake', $payload, ['X-Intake-Token' => 'wrong'])->assertForbidden();
        $this->postJson('/intake', $payload, ['X-Intake-Token' => 'secret-token-123'])->assertCreated();

        $item = IncomingMessage::firstOrFail();
        $this->assertSame('website', $item->channel);
        $this->assertSame('new', $item->status);
        $this->assertSame('ann@x.be', $item->from_email);
    }
}
