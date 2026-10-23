<?php

namespace Tests\Feature;

use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use App\Models\Web\CustomerAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Website › visitor statistics: only with consent, no IP, linked to who sends a request or signs in. */
class WebsiteTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const VID = '6f1c2b8e-3a4d-4e5f-9a6b-7c8d9e0f1a2b';

    public function test_nothing_is_kept_without_consent(): void
    {
        $this->postJson('/site/t', ['type' => 'view', 'path' => '/site/dry-hire'])->assertNoContent();
        $this->withCredentials()->withUnencryptedCookies(['cluk_consent' => 'necessary', 'cluk_vid' => self::VID])->postJson('/site/t', ['type' => 'view'])->assertNoContent();
        $this->assertSame(0, DB::table('web_visitors')->count());
        $this->get('/site')->assertOk()->assertSee('Accept statistics')->assertSee('Cookie settings');
    }

    public function test_visits_are_linked_to_the_request_that_follows(): void
    {
        Notification::fake();
        $k2 = new Item(['number' => 'K2', 'kind' => 'item', 'name' => 'L-Acoustics K2', 'unit' => 'pcs', 'serial' => false, 'stock_qty' => 24, 'day_price_cents' => 14500, 'active' => true]);
        $k2->placeIn(Subcategory::findOrFail(self::sub('SPK', 'AUD')));
        $k2->save();
        $c = ['cluk_consent' => 'all', 'cluk_vid' => self::VID];

        $this->withCredentials()->withUnencryptedCookies($c)->postJson('/site/t', ['type' => 'view', 'section' => 'dryhire', 'path' => '/site/dry-hire?utm_source=linkedin', 'referrer' => 'https://www.linkedin.com/', 'utm' => ['source' => 'linkedin']])->assertNoContent();
        $this->withCredentials()->withUnencryptedCookies($c)->postJson('/site/t', ['type' => 'search', 'section' => 'dryhire', 'label' => 'k2'])->assertNoContent();
        $this->withCredentials()->withUnencryptedCookies($c)->postJson('/site/t', ['type' => 'product', 'section' => 'dryhire', 'item_id' => $k2->id, 'label' => 'L-Acoustics K2'])->assertNoContent();
        $this->withCredentials()->withUnencryptedCookies($c)->postJson('/site/t', ['type' => 'order'])->assertStatus(422); // only the server records orders

        $this->withCredentials()->withUnencryptedCookies($c)->postJson('/site/dry-hire/request', ['name' => 'Lotte Peeters', 'email' => 'lotte@example.be', 'first_day' => now()->addDay()->toDateString(), 'last_day' => now()->addDays(2)->toDateString(),
            'lines' => [['id' => $k2->id, 'qty' => 4]]])->assertOk();

        $v = DB::table('web_visitors')->where('uuid', self::VID)->first();
        $this->assertSame(['lotte@example.be', 'linkedin', 'https://www.linkedin.com/', 1], [$v->email, $v->utm_source, $v->referrer, (int) $v->views]);
        $this->assertSame(['view', 'search', 'product', 'request'], DB::table('web_events')->where('visitor_id', $v->id)->orderBy('id')->pluck('type')->all());
        $this->assertNotNull(DB::table('web_events')->where('type', 'request')->value('meta'));
    }

    public function test_signing_in_links_the_customer(): void
    {
        $a = CustomerAccount::create(['email' => 'jo@theatre.example', 'password' => 'secret-pass-1', 'first_name' => 'Jo', 'last_name' => 'Peeters', 'status' => 'active', 'email_verified_at' => now(), 'locale' => 'en']);
        $this->withUnencryptedCookies(['cluk_consent' => 'all', 'cluk_vid' => self::VID])->post('/site/account/login', ['email' => 'jo@theatre.example', 'password' => 'secret-pass-1'])->assertRedirect();
        $this->assertSame($a->id, (int) DB::table('web_visitors')->where('uuid', self::VID)->value('customer_account_id'));
    }
}
