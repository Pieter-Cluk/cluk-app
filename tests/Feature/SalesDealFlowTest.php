<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductSales\SalesQuote;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Services\ProductSales\SalesFlow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** A deal of the kind "sales" makes a sales quote; Sales products sits under Operations; commercial tasks under My tasks. */
class SalesDealFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $this->sales = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
    }

    private function deal(string $module, ?Company $co): Deal
    {
        return Deal::create(['number' => Deal::nextNumber(), 'title' => 'Mics for the theatre', 'module' => $module, 'stage' => 'qualified', 'company_id' => $co?->id, 'owner_id' => $this->sales->id]);
    }

    public function test_a_sales_deal_makes_a_sales_quote_and_moves_on_when_accepted(): void
    {
        $co = Company::create(['name' => 'Demo Theatre vzw', 'country' => 'BE', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $deal = $this->deal('sales', $co);
        $this->actingAs($this->sales)->post(route('quotes.store', $deal))->assertRedirect();
        $q = SalesQuote::firstOrFail();
        $this->assertSame([$deal->id, $co->id, 'Mics for the theatre', 'nl'], [$q->deal_id, $q->company_id, $q->title, $q->language]);
        $this->assertSame(0, Quote::count()); // no rental quote
        $this->actingAs($this->sales)->get(route('deals.show', $deal))->assertOk()->assertSee($q->code());
        $this->actingAs($this->sales)->get(route('psales.show', $q))->assertOk()->assertSee('SALES QUOTES');

        SalesFlow::accept($q, 'Jo', 'PO-1', $this->sales);
        $this->assertSame('won', $deal->fresh()->stage);

        // A rental deal still makes a rental quote.
        $this->actingAs($this->sales)->post(route('quotes.store', $this->deal('dryhire', $co)))->assertRedirect();
        $this->assertSame(1, Quote::count());
    }

    public function test_a_sales_deal_needs_a_customer_and_new_sales_quote_starts_a_sales_deal(): void
    {
        $this->actingAs($this->sales)->post(route('quotes.store', $this->deal('sales', null)))->assertSessionHasErrors('company_id');
        $this->assertSame(0, SalesQuote::count());
        $this->actingAs($this->sales)->get('/sales/sales-quotes/new')->assertRedirect(route('deals.create', ['module' => 'sales']));
        $this->actingAs($this->sales)->get(route('deals.create', ['module' => 'sales']))->assertOk()->assertSee('<option value="sales" selected', false);
    }

    public function test_menu_old_links_and_tabs(): void
    {
        $html = $this->actingAs($this->sales)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.url('/commercial-tasks').'"', $html);
        $ops = substr($html, strpos($html, '/dry-hire'));
        $this->assertLessThan(strpos($ops, '/warehouse') ?: PHP_INT_MAX, strpos($ops, '/sales-products')); // right under Dry-hire
        $this->actingAs($this->sales)->get('/sales-products')->assertOk()->assertSee('Sales products');
        $this->actingAs($this->sales)->get('/sales/sales-quotes')->assertOk()->assertSee('Rental quotes')->assertSee('Sales quotes');
        $this->actingAs($this->sales)->get('/tasks')->assertOk()->assertSee('Commercial');
        $this->actingAs($this->sales)->get('/commercial-tasks')->assertOk()->assertSee('Commercial tasks');
        $this->actingAs($this->sales)->get('/product-sales/quotes/7?x=1')->assertRedirect('/sales/sales-quotes/7?x=1');
        $this->actingAs($this->sales)->get('/product-sales/items')->assertRedirect('/sales-products');
    }
}
