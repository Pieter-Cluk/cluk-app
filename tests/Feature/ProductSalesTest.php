<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuote;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Storage::fake('local');
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function supplier(string $name, string $email): Company
    {
        return Company::create(['name' => $name, 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en', 'email' => $email]);
    }

    /** Shure SM58 (list 119, 6 in stock) with two suppliers: 35% and 32% discount. */
    private function catalogue(User $admin): array
    {
        $a = $this->supplier('Demo Audio Distribution BV', 'orders@dad.example');
        $b = $this->supplier('Demo Pro Supply GmbH', 'sales@dps.example');
        $this->actingAs($admin)->post('/sales-products', ['brand' => 'Shure', 'type' => 'SM58-LCE', 'description' => 'Dynamic vocal microphone', 'category' => 'Audio', 'unit' => 'piece', 'list_price' => '119', 'stock_qty' => 6])->assertRedirect();
        $item = SalesItem::firstOrFail();
        $this->assertSame('S-100001', $item->number);
        $this->actingAs($admin)->post('/sales-products/'.$item->id.'/suppliers', ['company_id' => $a->id, 'discount' => '35', 'lead_days' => 3, 'preferred' => '1']);
        $this->actingAs($admin)->post('/sales-products/'.$item->id.'/suppliers', ['company_id' => $b->id, 'discount' => '32', 'lead_days' => 5]);

        return [$item->fresh('suppliers'), $a, $b];
    }

    public function test_items_suppliers_documents_and_rights(): void
    {
        $admin = $this->as('admin');
        [$item, $a] = $this->catalogue($admin);
        $this->assertSame(7735, $item->costCents()); // 119 − 35%
        $this->actingAs($this->as('sales'))->post('/sales-products', ['type' => 'X', 'unit' => 'piece', 'list_price' => 1])->assertForbidden();

        $this->actingAs($admin)->post('/sales-products/'.$item->id.'/documents', ['kind' => 'manual', 'files' => [UploadedFile::fake()->create('SM58 user guide.pdf', 200, 'application/pdf')]])->assertRedirect();
        $doc = $item->documents()->firstOrFail();
        $this->actingAs($admin)->get('/sales-products/'.$item->id.'/documents/'.$doc->id)->assertOk();

        $customer = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $jo = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@theatre.example', 'language' => 'nl']);
        $customer->contacts()->attach($jo->id);
        $this->actingAs($this->as('sales'))->post('/sales-products/'.$item->id.'/send', ['docs' => [$doc->id], 'contact_id' => $jo->id])->assertSessionHasNoErrors()->assertRedirect();
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'jo@theatre.example' && count($n->attachFiles) === 1);
    }

    public function test_from_quote_with_low_margin_to_purchase_order_and_delivery(): void
    {
        $admin = $this->as('admin');
        [$item, $a, $b] = $this->catalogue($admin);
        $customer = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $elke = $this->as('sales');

        $this->actingAs($elke)->post('/sales/sales-quotes', ['company_id' => $customer->id, 'title' => 'Wireless & stage kit'])->assertRedirect();
        $q = SalesQuote::firstOrFail();
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/lines', ['kind' => 'item', 'item_id' => $item->id, 'qty' => 10]);
        $line = $q->lines()->firstOrFail();
        // 40% discount: 71.40 a piece against a purchase price of 77.35 → under 10% margin.
        $this->actingAs($elke)->put('/sales/sales-quotes/'.$q->id, ['title' => $q->title, 'language' => 'en', 'delivery_mode' => 'together', 'lines' => [$line->id => ['qty' => 10, 'discount' => 40]]]);
        $q->refresh()->load('lines');
        $this->assertTrue($q->needsApproval());
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/send', ['to' => 'jo@theatre.example'])->assertSessionHasErrors('lines');
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/approve')->assertForbidden();
        $this->actingAs($this->as('manager'))->post('/sales/sales-quotes/'.$q->id.'/approve', ['note' => 'Big season customer']);
        $this->assertFalse($q->fresh('lines')->needsApproval());
        // A change after the approval needs a new approval.
        $this->actingAs($elke)->put('/sales/sales-quotes/'.$q->id, ['title' => $q->title, 'language' => 'en', 'delivery_mode' => 'together', 'lines' => [$line->id => ['qty' => 10, 'discount' => 45]]]);
        $this->assertTrue($q->fresh('lines')->needsApproval());
        $this->actingAs($elke)->put('/sales/sales-quotes/'.$q->id, ['title' => $q->title, 'language' => 'en', 'delivery_mode' => 'together', 'lines' => [$line->id => ['qty' => 10, 'discount' => 15]]]);
        $this->assertFalse($q->fresh('lines')->needsApproval()); // 101.15 vs 77.35: 23.5% margin

        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/send', ['to' => 'jo@theatre.example'])->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame('sent', $q->status);
        $this->assertSame(101150, $q->net_cents);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'jo@theatre.example' && str_contains($n->subject, 'Wireless & stage kit'));

        // The customer accepts online.
        auth('web')->logout();
        $this->get('/sq/'.$q->token)->assertOk()->assertSee('Accept and order');
        $this->post('/sq/'.$q->token.'/accept', ['name' => 'Jo Peeters', 'po_number' => '2026-118'])->assertSessionHasErrors('terms');
        $this->post('/sq/'.$q->token.'/accept', ['name' => 'Jo Peeters', 'po_number' => '2026-118', 'terms' => '1'])->assertRedirect();
        $q->refresh();
        $this->assertSame(['accepted', '2026-118'], [$q->status, $q->po_number]);
        $this->assertSame(6, $line->fresh()->from_stock);

        // A price request for the 4 missing pieces went to both suppliers.
        $pq = PurchaseRequest::firstOrFail();
        $this->assertSame(['sent', 4], [$pq->status, $pq->lines()->value('qty')]);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'sales@dps.example' && str_contains($n->subject, $pq->number));
        $pqLine = $pq->lines()->firstOrFail();

        $buyer = $this->as('asset-manager');
        $buyer->forceFill(['role_id' => Role::where('slug', 'admin')->value('id')])->save();
        // The sent request opens (sent dates on the suppliers), and the task lists show the work.
        $this->actingAs($buyer)->get('/purchasing/requests/'.$pq->id)->assertOk();
        $this->actingAs($elke)->get('/dashboard')->assertOk()->assertSee('Sales orders to buy in and confirm');
        // Supplier A: 35% (77.35), on the 29th; supplier B: net 80.00, on the 27th.
        $this->actingAs($buyer)->post('/purchasing/requests/'.$pq->id.'/replies', ['r' => [$pqLine->id => [
            $a->id => ['disc' => '35', 'date' => today()->addDays(8)->toDateString()],
            $b->id => ['net' => '80.00', 'date' => today()->addDays(6)->toDateString()],
        ]]])->assertSessionHasNoErrors();
        $this->assertSame($a->id, $pqLine->fresh()->chosen_company_id); // cheapest
        $this->actingAs($buyer)->get('/purchasing/requests/'.$pq->id)->assertOk();
        $this->actingAs($buyer)->get('/dashboard?who=all')->assertOk()->assertSee('Supplier replies to compare');
        $this->actingAs($buyer)->post('/purchasing/requests/'.$pq->id.'/replies', ['order' => '1'])->assertSessionHasNoErrors();
        $po = PurchaseOrder::firstOrFail();
        $this->assertSame([$a->id, 30940, 'sent'], [$po->company_id, $po->total_cents, $po->status]);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'orders@dad.example' && str_contains($n->subject, $po->number));

        // Not confirmed yet: no order confirmation possible.
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/confirm')->assertSessionHasErrors('confirm');
        $poLine = $po->lines()->firstOrFail();
        $this->actingAs($buyer)->post('/purchasing/orders/'.$po->id.'/confirm', ['their_ref' => 'SO-77120', 'dates' => [$poLine->id => today()->addDays(7)->toDateString()]])->assertRedirect();
        $this->assertNotNull($line->fresh()->delivery_date);
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/confirm')->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $q->fresh()->status);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n) => str_contains($n->subject, 'S-OC-'));

        // Goods in: stock 6 + 4; delivered: stock 0.
        $this->actingAs($buyer)->post('/purchasing/orders/'.$po->id.'/receive', ['qty' => [$poLine->id => 4]]);
        $this->assertSame([10, 'received'], [$item->fresh()->stock_qty, $po->fresh()->status]);
        $this->actingAs($buyer)->post('/purchasing/orders/'.$po->id.'/invoice', ['amount' => '314.00']);
        $this->assertTrue($po->fresh()->invoiceDiffers());
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/deliver');
        $this->assertSame([0, 'delivered'], [$item->fresh()->stock_qty, $q->fresh()->status]);
    }

    public function test_supplier_prices_before_the_customer_accepts(): void
    {
        $admin = $this->as('admin');
        [$item] = $this->catalogue($admin);
        $customer = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $elke = $this->as('sales');
        $this->actingAs($elke)->post('/sales/sales-quotes', ['company_id' => $customer->id, 'title' => 'Early prices'])->assertRedirect();
        $q = SalesQuote::firstOrFail();
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/lines', ['kind' => 'item', 'item_id' => $item->id, 'qty' => 10]);
        $this->actingAs($elke)->get(route('psales.show', $q))->assertOk()->assertSee('Request prices from suppliers');

        $this->actingAs($elke)->post(route('psales.prices', $q))->assertRedirect();
        $pq = PurchaseRequest::firstOrFail();
        $this->assertSame(['sent', $q->id, 4], [$pq->status, $pq->sales_quote_id, $pq->lines()->value('qty')]); // 6 in stock
        $this->actingAs($elke)->get(route('psales.show', $q))->assertDontSee('Request prices from suppliers')->assertSee($pq->number);

        // The customer accepts: the early request is used, no second one.
        $this->actingAs($elke)->post('/sales/sales-quotes/'.$q->id.'/send', ['to' => 'jo@theatre.example']);
        $this->post('/sq/'.$q->fresh()->token.'/accept', ['name' => 'Jo Peeters', 'terms' => '1'])->assertRedirect();
        $this->assertSame('accepted', $q->fresh()->status);
        $this->assertSame(1, PurchaseRequest::count());
    }

    public function test_pages_in_every_language(): void
    {
        $admin = $this->as('admin');
        [$item, $a] = $this->catalogue($admin);
        $customer = Company::create(['name' => 'Demo Theatre vzw', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $this->actingAs($admin)->post('/sales/sales-quotes', ['company_id' => $customer->id, 'title' => 'Kit']);
        $q = SalesQuote::firstOrFail();
        $this->actingAs($admin)->post('/sales/sales-quotes/'.$q->id.'/lines', ['kind' => 'item', 'item_id' => $item->id, 'qty' => 2]);
        $this->actingAs($admin)->post('/sales/sales-quotes/'.$q->id.'/lines', ['kind' => 'charge', 'name' => 'Delivery']);
        $this->actingAs($admin)->post('/purchasing/requests', ['qty' => [$item->id => 5], 'purpose' => [$item->id => 'rental'], 'send' => '0'])->assertRedirect();
        $pq = PurchaseRequest::firstOrFail();
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            $urls = ['/sales/sales-quotes', '/sales/sales-quotes?tab=approval', '/sales-products', '/sales-products?new=1', '/sales/sales-quotes/'.$q->id,
                '/sales/sales-quotes/'.$q->id.'/print/quo', '/sales/sales-quotes/'.$q->id.'/print/pick', '/sales/sales-quotes/'.$q->id.'/print/pf',
                '/purchasing', '/purchasing?tab=po', '/purchasing/requests/new', '/purchasing/requests/'.$pq->id, '/purchasing/requests/'.$pq->id.'/print', '/dashboard', '/settings/documents?doc=S-QUO'];
            foreach ($urls as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(psales|purchasing|documents)\.[a-z_]+(\.[-A-Za-z_0-9]+)*\b/', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html)), "{$url} ({$locale})");
            }
        }
    }
}
