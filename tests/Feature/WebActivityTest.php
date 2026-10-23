<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use App\Models\Role;
use App\Models\Sales\IncomingMessage;
use App\Models\User;
use App\Services\Website\WebActivity;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Website statistics shown read-only in the platform, and deleted after 13 months. */
class WebActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $co;

    private Contact $jo;

    private Item $ks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $this->co = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $this->jo = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@theatre.example', 'language' => 'en']);
        $this->co->contacts()->attach($this->jo->id);
        $this->ks = new Item(['number' => 'KS28X', 'kind' => 'item', 'name' => 'L-Acoustics KS28 subwoofer', 'unit' => 'pcs', 'serial' => false, 'day_price_cents' => 9500, 'active' => true]);
        $this->ks->placeIn(Subcategory::findOrFail(self::sub('SUB', 'AUD')));
        $this->ks->save();
    }

    private function visitor(array $v = []): int
    {
        return DB::table('web_visitors')->insertGetId(array_merge(['uuid' => (string) Str::uuid(), 'device' => 'desktop', 'referrer' => 'https://www.google.com/', 'utm_source' => null,
            'first_seen_at' => now()->subDays(3), 'last_seen_at' => now(), 'created_at' => now()->subDays(3), 'updated_at' => now()], $v));
    }

    private function event(int $visitor, string $type, array $e = [], $at = null): void
    {
        DB::table('web_events')->insert(['visitor_id' => $visitor, 'type' => $type, 'section' => $e['section'] ?? 'dryhire', 'path' => $e['path'] ?? null, 'label' => $e['label'] ?? null,
            'item_id' => $e['item_id'] ?? null, 'meta' => isset($e['meta']) ? json_encode($e['meta']) : null, 'created_at' => $at ?? now()]);
    }

    public function test_before_request_cards_and_management(): void
    {
        $v = $this->visitor(['email' => 'jo@theatre.example', 'utm_source' => 'newsletter', 'utm_medium' => 'email']);
        $this->event($v, 'view', ['path' => '/dry-hire'], now()->subDays(3));
        $this->event($v, 'search', ['label' => 'KS28', 'meta' => ['results' => 2]], now()->subDays(3)->addMinute());
        $this->event($v, 'search', ['label' => 'flux capacitor', 'meta' => ['results' => 0]], now()->subDays(3)->addMinutes(2));
        $this->event($v, 'product', ['item_id' => $this->ks->id, 'label' => 'KS28'], now()->subMinutes(20));
        $this->event($v, 'add', ['item_id' => $this->ks->id, 'meta' => ['qty' => 4]], now()->subMinutes(15));
        $msg = IncomingMessage::create(['channel' => 'website', 'subject' => 'Website · dry-hire request', 'from_name' => 'Jo', 'from_email' => 'jo@theatre.example', 'module' => 'dryhire', 'received_at' => now()->subMinutes(10)]);
        $this->event($v, 'request', ['label' => 'W-'.$msg->id, 'meta' => ['incoming_id' => $msg->id]], now()->subMinutes(10));
        $other = $this->visitor(['referrer' => 'https://www.linkedin.com/feed']);
        $this->event($other, 'view', ['path' => '/'], now()->subDay());

        // Incoming: what the visitor did before the request.
        $this->actingAs($this->admin)->get('/sales/incoming?open='.$msg->id)->assertOk()
            ->assertSee('Before this request')->assertSee('2 visits')->assertSee('newsletter / email')->assertSee('flux capacitor')->assertSee('no results')
            ->assertSee('L-Acoustics KS28 subwoofer')->assertSee('Sent the request');

        // Company card and contact page.
        $this->actingAs($this->admin)->get('/companies/'.$this->co->id)->assertOk()->assertSee('Website activity')->assertSee('ks28')->assertSee('2 visits')->assertDontSee('No website visits known for this one');
        $s = WebActivity::summary(WebActivity::visitorsOfContact($this->jo));
        $this->assertSame(2, $s['visits']);
        $this->assertSame(1, $s['requests']);
        $this->assertSame('L-Acoustics KS28 subwoofer', $s['products'][0]['name']);
        $this->assertSame(['Audio' => 2], $s['categories']);
        $this->actingAs($this->admin)->get('/companies/'.$this->co->id.'/contacts/'.$this->jo->id.'/edit')->assertOk()->assertSee('Website activity')->assertSee('ks28');

        // Management › Website.
        $this->actingAs($this->admin)->get('/management/website')->assertOk()->assertSee('Searches with no results')->assertSee('flux capacitor')
            ->assertSee('linkedin.com')->assertSee('L-Acoustics KS28 subwoofer');
        $o = WebActivity::overview(30);
        $this->assertSame(2, $o['visitors']);
        $this->assertSame(['newsletter / email' => 1], $o['request_sources']);
        $this->actingAs($this->admin)->get('/management')->assertOk()->assertSee(route('management.website'), false);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id')]))->get('/management/website')->assertForbidden();
    }

    public function test_no_data_and_retention(): void
    {
        $this->actingAs($this->admin)->get('/companies/'.$this->co->id)->assertOk()->assertSee('No website visits known for this one');
        $old = $this->visitor(['last_seen_at' => now()->subMonths(14), 'created_at' => now()->subMonths(15)]);
        $this->event($old, 'view', [], now()->subMonths(14));
        $recent = $this->visitor();
        $this->event($recent, 'view', [], now()->subMonths(14)); // an old event of a visitor who came back
        $this->event($recent, 'view', [], now()->subDay());
        $r = WebActivity::prune();
        $this->assertSame(['events' => 2, 'visitors' => 1], $r);
        $this->assertSame(1, DB::table('web_visitors')->count());
        $this->assertSame(1, DB::table('web_events')->count());
    }
}
