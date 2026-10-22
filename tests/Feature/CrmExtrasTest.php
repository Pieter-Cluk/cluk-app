<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ProductSales\SalesItem;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Crm\Birthdays;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmExtrasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
    }

    private function company(array $a = []): Company
    {
        return Company::create($a + ['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl', 'country' => 'BE']);
    }

    public function test_logo_in_the_company_form_and_internal_notes_on_the_page(): void
    {
        $c = $this->company();
        $this->actingAs($this->admin)->get('/companies/'.$c->id)->assertOk()->assertSee('Internal notes')->assertSee('only staff see this');
        $this->actingAs($this->admin)->post('/companies/'.$c->id.'/notes', ['notes' => 'Always call Jo before delivery'])->assertRedirect();
        $this->assertSame('Always call Jo before delivery', $c->fresh()->notes);

        $this->actingAs($this->admin)->get('/companies/'.$c->id.'/edit')->assertOk()->assertSee('name="logo"', false);
        $this->actingAs($this->admin)->put('/companies/'.$c->id, ['name' => 'Demo Agency BV', 'is_customer' => '1', 'country' => 'BE', 'language' => 'nl', 'vat_regime' => 'auto',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 120)])->assertSessionHasNoErrors();
        $this->assertNotNull($c->fresh()->logo_path);
    }

    public function test_birthday_on_the_contact_and_a_card_on_the_day_for_ticked_contacts(): void
    {
        $c = $this->company();
        $this->actingAs($this->admin)->post('/companies/'.$c->id.'/contacts', ['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@demo.example', 'birth_date' => '1985-03-14', 'birthday_card' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/companies/'.$c->id.'/contacts', ['first_name' => 'An', 'email' => 'an@demo.example', 'birth_date' => '1990-03-14'])->assertSessionHasNoErrors();
        $jo = Contact::where('first_name', 'Jo')->firstOrFail();
        $this->assertSame('1985-03-14', $jo->birth_date->toDateString());
        $this->assertTrue($jo->birthday_card);

        $this->assertSame(0, Birthdays::sendToday(Carbon::parse('2027-03-13')));
        $this->assertSame(1, Birthdays::sendToday(Carbon::parse('2027-03-14')), 'only the ticked contact');
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $ch, $to) => $to->routes['mail'] === 'jo@demo.example' && $n->subject === 'Gelukkige verjaardag, Jo! 🎉');
        $this->assertSame(0, Birthdays::sendToday(Carbon::parse('2027-03-14')), 'once a year');

        $this->assertTrue(Birthdays::isBirthday(Carbon::parse('2000-02-29'), Carbon::parse('2027-02-28')));
        $this->assertFalse(Birthdays::isBirthday(Carbon::parse('2000-02-29'), Carbon::parse('2028-02-28')));
        $this->artisan('cluk:birthdays')->assertSuccessful();
    }

    public function test_sales_products_import_with_suppliers(): void
    {
        $supplier = $this->company(['name' => 'Audio Distribution BV', 'is_customer' => false, 'is_supplier' => true]);
        $csv = "Item number;Brand;Type;Description;Category;Unit;List price;Stock;EAN / barcode;Supplier 1;Supplier 1 article no.;Supplier 1 discount %;Supplier 1 lead time (working days);Supplier 2\n"
            .";Shure;SM58-LCE;Dynamic vocal microphone;audio;piece;119,00;6;042406051836;Audio Distribution BV;SH-58;35;3;Unknown Supplier\n"
            .";Le Mark;GAF50BK;Gaffer tape 50 mm;Consumables;roll;12,5;48;;;;;;\n";
        $preview = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => UploadedFile::fake()->createWithContent('products.csv', $csv)])->assertOk();
        $this->assertSame(2, $preview->viewData('reports')['salesitems']['created']);
        $preview->assertSee('Unknown Supplier');
        $this->actingAs($this->admin)->post('/settings/import/run', ['token' => $preview->viewData('token')])->assertOk();

        $mic = SalesItem::where('type', 'SM58-LCE')->firstOrFail();
        $this->assertSame([11900, 6, 'Audio', '042406051836'], [$mic->list_price_cents, $mic->stock_qty, $mic->category, $mic->ean]);
        $link = $mic->suppliers()->first();
        $this->assertTrue($link->is($supplier));
        $this->assertSame([3500, 3, true, 'SH-58'], [$link->pivot->discount_bp, $link->pivot->lead_days, (bool) $link->pivot->preferred, $link->pivot->supplier_ref]);
        $this->assertSame(7735, $mic->fresh('suppliers')->costCents());

        // The template's example products are skipped.
        $file = new UploadedFile(resource_path('templates/CLUK_Import_Templates.xlsx'), 'CLUK_Import_Templates.xlsx', null, null, true);
        $reports = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $file])->assertOk()->viewData('reports');
        $this->assertSame(0, $reports['salesitems']['created']);
        $this->assertGreaterThan(0, $reports['salesitems']['skipped']);
    }

    public function test_media_group_in_the_menu(): void
    {
        $this->actingAs($this->admin)->get('/media/messages')->assertOk()->assertSee('Messages')->assertSee('Social media')->assertSee('Communication');
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('>Media<', false);
    }

    public function test_contacts_list_has_an_edit_button_that_comes_back_to_the_list(): void
    {
        $c = $this->company();
        $this->actingAs($this->admin)->post('/companies/'.$c->id.'/contacts', ['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@demo.example', 'job_title' => 'Producer']);
        $jo = Contact::firstOrFail();
        $list = url('/companies?view=contacts&q=jo');
        $edit = route('companies.contacts.edit', [$c, $jo, 'back' => $list]);
        $this->actingAs($this->admin)->get('/companies?view=contacts&q=jo')->assertOk()->assertSee('/companies/'.$c->id.'/contacts/'.$jo->id.'/edit?back=', false);
        $this->actingAs($this->admin)->get($edit)->assertOk()->assertSee('value="Producer"', false);
        $this->actingAs($this->admin)->put('/companies/'.$c->id.'/contacts/'.$jo->id, ['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@demo.example', 'job_title' => 'Head of production', 'back' => $list])
            ->assertRedirect($list);
        $this->assertSame('Head of production', $c->contacts()->first()->pivot->job_title);
        // Only a page of the app is a way back.
        $this->actingAs($this->admin)->put('/companies/'.$c->id.'/contacts/'.$jo->id, ['first_name' => 'Jo', 'back' => 'https://evil.example/'])->assertRedirect(route('companies.show', $c));
        $viewer = User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
        $this->actingAs($viewer)->get('/companies?view=contacts')->assertDontSee('/contacts/'.$jo->id.'/edit');
    }
}
