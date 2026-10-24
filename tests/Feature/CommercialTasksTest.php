<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Sales\Quote;
use App\Models\Sales\SalesTask;
use App\Models\User;
use App\Services\Sales\CommercialTasks;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Commercial tasks made by the app: customer cards, open quotes, website and social activity, with a colour. */
class CommercialTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $elke;

    private Company $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->elke = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en', 'is_commercial' => true, 'first_name' => 'Elke']);
        $this->customer = Company::create(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en',
            'account_owner_id' => $this->elke->id, 'country' => 'BE', 'email' => 'info@demo.example', 'phone' => '+32 9 000 00 00', 'street' => 'Street 1', 'postcode' => '9870', 'city' => 'Zulte', 'vat_number' => 'BE0123456789']);
        $contact = Contact::create(['first_name' => 'Jan', 'last_name' => 'Peeters', 'email' => 'jan@demo.example']);
        $this->customer->contacts()->attach($contact->id);
    }

    private function quote(int $daysAgo, array $extra = []): Quote
    {
        return Quote::create(array_merge(['number' => 'R-QUO-2026-0'.random_int(100, 999), 'version' => 1, 'status' => 'sent', 'company_id' => $this->customer->id, 'owner_id' => $this->elke->id,
            'title' => 'Festival rig', 'language' => 'en', 'net_cents' => 450000, 'sent_at' => now()->subDays($daysAgo),
            'out_from' => now()->addDays(20), 'out_until' => now()->addDays(23), 'use_from' => today()->addDays(21), 'use_until' => today()->addDays(22)], $extra));
    }

    public function test_quotes_get_colours_by_age_and_close_by_themselves(): void
    {
        $q = $this->quote(2);
        CommercialTasks::refresh();
        $this->assertSame(0, SalesTask::where('kind', 'quote')->count());
        foreach ([[6, 'yellow'], [12, 'orange'], [25, 'red']] as [$days, $level]) {
            $q->update(['sent_at' => now()->subDays($days)]);
            CommercialTasks::refresh();
            $this->assertSame($level, SalesTask::where('key', 'quote:'.$q->id)->value('level'));
        }
        $this->assertSame($this->elke->id, SalesTask::where('key', 'quote:'.$q->id)->value('owner_id'));
        // Accepted: the task closes by itself.
        $q->update(['status' => 'accepted']);
        CommercialTasks::refresh();
        $this->assertSame('resolved', SalesTask::where('key', 'quote:'.$q->id)->value('status'));
    }

    public function test_customer_cards_that_need_attention(): void
    {
        $this->quote(1); // a customer we work with
        CommercialTasks::refresh();
        $this->assertSame(0, SalesTask::where('kind', 'card')->count()); // complete card
        $this->customer->update(['vat_number' => null, 'phone' => null]);
        CommercialTasks::refresh();
        $t = SalesTask::where('key', 'card:'.$this->customer->id)->firstOrFail();
        $this->assertSame('orange', $t->level); // missing invoice data while a quote is running
        $this->assertEqualsCanonicalizing(['no_phone', 'no_vat'], $t->data['problems']);
        $this->customer->forceFill(['vat_number' => 'BE0123456789', 'vies_status' => 'invalid'])->save();
        CommercialTasks::refresh();
        $this->assertSame('red', $t->fresh()->level);
        // Companies we never worked with get no tasks.
        Company::create(['name' => 'Unknown BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        CommercialTasks::refresh();
        $this->assertSame(1, SalesTask::where('kind', 'card')->count());
    }

    public function test_website_and_social_activity_and_the_list(): void
    {
        $v = DB::table('web_visitors')->insertGetId(['uuid' => (string) Str::uuid(), 'device' => 'mobile', 'referrer' => 'https://l.instagram.com/', 'utm_source' => 'instagram', 'email' => 'JAN@demo.example',
            'first_seen_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('web_events')->insert(['visitor_id' => $v, 'type' => 'search', 'section' => 'dryhire', 'label' => 'blinder', 'created_at' => now()]);
        CommercialTasks::refresh();
        $t = SalesTask::where('kind', 'web')->firstOrFail();
        $this->assertSame([$this->customer->id, 'blue', ['Instagram'], ['blinder']], [$t->company_id, $t->level, $t->data['social'], $t->data['searches']]);
        DB::table('web_events')->insert(['visitor_id' => $v, 'type' => 'add', 'section' => 'dryhire', 'item_id' => 1, 'created_at' => now()]);
        CommercialTasks::refresh();
        $this->assertSame('orange', $t->fresh()->level); // something in the basket

        $q = $this->quote(25);
        CommercialTasks::refresh();
        $r = $this->actingAs($this->elke)->get('/commercial-tasks')->assertOk();
        $r->assertSee('Demo Agency BV')->assertSee('came via Instagram')->assertSee('open for 25 days')->assertSee('Act now');
        $this->actingAs($this->elke)->get('/dashboard')->assertOk()->assertSee('Commercial tasks (red/orange)');
        // Done: gone until it gets worse; snooze: back later.
        $qt = SalesTask::where('key', 'quote:'.$q->id)->firstOrFail();
        $this->actingAs($this->elke)->post(route('salestasks.done', $qt))->assertRedirect();
        CommercialTasks::refresh();
        $this->assertSame('done', $qt->fresh()->status);
        $this->actingAs($this->elke)->post(route('salestasks.snooze', $t), ['days' => 3])->assertRedirect();
        $this->assertSame(0, SalesTask::open()->where('id', $t->id)->count());
        $this->travel(4)->days();
        $this->assertSame(1, SalesTask::open()->where('id', $t->id)->count());
        // Only for people with sales level 2.
        $crew = User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
        $this->actingAs($crew)->get('/commercial-tasks')->assertForbidden();
        foreach (['nl' => 'Commerciële taken', 'fr' => 'Tâches commerciales', 'es' => 'Tareas comerciales'] as $loc => $title) {
            $this->elke->update(['locale' => $loc]);
            $this->actingAs($this->elke->fresh())->get('/commercial-tasks')->assertOk()->assertSee($title);
        }
    }
}
