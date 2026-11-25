<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\Sales\Deal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    public function test_the_top_bar_search_finds_jobs_companies_contacts_and_equipment(): void
    {
        $sales = $this->as('sales');
        $company = Company::create(['name' => 'Demo Agency BV', 'vat_number' => 'BE0123456789', 'country' => 'BE', 'is_customer' => true, 'vat_regime' => 'auto', 'language' => 'en']);
        $contact = Contact::create(['first_name' => 'Sarah', 'last_name' => 'Peeters', 'email' => 'sarah@demo.be']);
        $company->contacts()->attach($contact->id);
        $deal = Deal::create(['number' => Deal::nextNumber(), 'title' => 'Company evening', 'module' => 'dryhire', 'stage' => 'proposal', 'company_id' => $company->id, 'owner_id' => $sales->id]);
        $item = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'serial' => true, 'day_price_cents' => 6500, 'price_unit' => 'day']);
        $item->assets()->create(['code' => 'LT-0001', 'serial_number' => '3021-0142', 'status' => 'available']);

        // The bar is a real search form on every page.
        $this->actingAs($sales)->get('/dashboard')->assertOk()->assertSee('action="'.route('search').'"', false)->assertSee('name="q"', false);

        $groups = fn (User $u, string $q) => collect($this->actingAs($u)->getJson('/search/suggest?q='.urlencode($q))->assertOk()->json('groups'))->keyBy('key');
        $this->assertSame(route('companies.show', $company), $groups($sales, 'BE0123')['companies']['items'][0]['url']);
        $this->assertSame('Sarah Peeters', $groups($sales, 'sarah peet')['contacts']['items'][0]['title']);
        $this->assertSame(route('deals.show', $deal), $groups($sales, 'evening')['deals']['items'][0]['url']);
        $this->assertSame(route('equipment.show', $item), $groups($sales, 'spiider')['items']['items'][0]['url']);
        $this->assertStringContainsString('LT-0001', $groups($sales, '3021-0142')['assets']['items'][0]['title']);
        $this->assertSame([], $this->actingAs($sales)->getJson('/search/suggest?q=x')->json('groups'), 'two letters at least');
        $this->assertSame([], $this->actingAs($sales)->getJson('/search/suggest?q='.urlencode('100%'))->json('groups'));

        // Only the modules someone may open.
        $host = User::factory()->create(['role_id' => Role::where('slug', 'workspace-host')->value('id'), 'locale' => 'en']);
        $this->assertFalse($groups($host, 'evening')->has('deals'));
        $this->assertFalse($groups($host, 'spiider')->has('items'));
        $this->assertTrue($groups($host, 'demo agency')->has('companies'));

        // The result page.
        $this->actingAs($sales)->get('/search?q=spiider')->assertOk()->assertSee('LT-SPIIDER · Robe Spiider')->assertSee(__('search.groups.items'));
        $this->actingAs($sales)->get('/search?q=nothing-like-this')->assertOk()->assertSee(__('search.none'));
        $this->actingAs($sales)->get('/search')->assertOk()->assertSee(__('search.hint'));
    }
}
