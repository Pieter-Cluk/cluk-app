<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A product-sales deal speaks of a deadline and a delivery address, not of an event; its quote button makes a sales quote. */
class SalesDealWordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_deal_uses_deadline_and_sales_quote(): void
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id'), 'locale' => 'en']);
        $c = Company::create(['name' => 'Demo BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);

        $this->actingAs($u)->get(route('deals.create', ['module' => 'sales']))->assertOk()->assertSee('Deadline')->assertSee('Delivery address')->assertSee('When does the customer need the goods?');
        $this->actingAs($u)->get(route('deals.create', ['module' => 'dryhire']))->assertOk()->assertSee('Event from');

        $this->actingAs($u)->post(route('deals.store'), ['title' => '4 moving heads', 'module' => 'sales', 'company_id' => $c->id, 'event_start' => today()->addWeek()->toDateString(), 'event_end' => today()->addWeeks(3)->toDateString()])->assertRedirect();
        $deal = Deal::firstOrFail();
        $this->assertSame($deal->event_start->toDateString(), $deal->event_end->toDateString());

        $html = $this->actingAs($u)->get(route('deals.show', $deal))->assertOk()->getContent();
        $this->assertStringContainsString('New sales quote', $html);
        $this->assertStringNotContainsString('New rental quote', $html);
        $this->assertStringContainsString('Deadline and delivery address known', $html);
    }
}
