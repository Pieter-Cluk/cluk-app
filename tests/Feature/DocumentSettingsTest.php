<?php

namespace Tests\Feature;

use App\Http\Controllers\Settings\DocumentController;
use App\Models\Company;
use App\Models\DocumentSetting;
use App\Models\Role;
use App\Models\Sales\Quote;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSettingsTest extends TestCase
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

    public function test_notes_and_seasonal_message_appear_on_customer_documents_in_their_language(): void
    {
        $admin = $this->as('admin');
        $this->actingAs($admin)->put('/settings/documents/note/R-QUO', ['on' => '1', 'text' => ['en' => 'Book before 31 Oct for free delivery.', 'nl' => 'Boek vóór 31 okt voor gratis levering.']])->assertRedirect();
        $this->actingAs($admin)->put('/settings/documents/season', ['on' => '1', 'from' => today()->subDay()->toDateString(), 'to' => today()->addDays(5)->toDateString(), 'text' => ['nl' => 'Prettige feesten van het hele CLUK-team!']])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put('/settings/documents/note/R-PICK', ['on' => '1'])->assertNotFound(); // internal document

        DocumentSetting::forget();
        $c = Company::create(['name' => 'Demo BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $q = Quote::create(['number' => 'R-QUO-2026-0001', 'version' => 1, 'status' => 'draft', 'company_id' => $c->id, 'owner_id' => $admin->id, 'title' => 'Gala', 'language' => 'nl',
            'out_from' => now()->addDay(), 'out_until' => now()->addDays(3), 'use_from' => today()->addDay(), 'use_until' => today()->addDays(2)]);
        $this->actingAs($admin)->get('/sales/quotes/'.$q->id.'/print')->assertOk()->assertSee('Boek vóór 31 okt')->assertSee('Prettige feesten');

        $this->actingAs($admin)->put('/settings/documents/season', ['on' => '1', 'from' => today()->addDays(10)->toDateString(), 'to' => today()->addDays(20)->toDateString(), 'text' => ['nl' => 'Later']]);
        DocumentSetting::forget();
        $this->actingAs($admin)->get('/sales/quotes/'.$q->id.'/print')->assertDontSee('Prettige feesten')->assertDontSee('Later');
    }

    public function test_page_in_every_language_and_access(): void
    {
        $this->actingAs($this->as('sales'))->get('/settings/documents')->assertForbidden();
        $admin = $this->as('admin');
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (DocumentController::all() as $type => $d) {
                $html = $this->actingAs($admin->fresh())->get('/settings/documents?doc='.$type)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\bdocuments\.[a-z_]+(\.[A-Za-z_\-]+)*\b/', strip_tags($html), "{$type} ({$locale})");
            }
        }
    }
}
