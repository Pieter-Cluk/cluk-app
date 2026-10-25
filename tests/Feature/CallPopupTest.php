<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use App\Support\Phone;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phone numbers in one international format; Linkus call pop-up; WhatsApp buttons. */
class CallPopupTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->sales = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
    }

    private function company(array $extra = []): Company
    {
        return Company::create(array_merge(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'BE'], $extra));
    }

    public function test_numbers_are_written_internationally_however_they_were_typed(): void
    {
        foreach (['09/123.45.67', '0032 9 123 45 67', '+32 (0)9 123 45 67', '+32 9 123 45 67'] as $typed) {
            $this->assertSame('3291234567', Phone::key($typed), $typed);
        }
        $this->assertSame('+32 471 12 34 56', Phone::international('0471-12-34-56'));
        $this->assertSame('+31 6 12345678', Phone::international('06 12345678', 'NL'));
        $this->assertNull(Phone::key('201')); // an extension

        $co = $this->company(['phone' => '09 123 45 67']);
        $this->assertSame(['+32 9 123 45 67', '3291234567'], [$co->phone, $co->phone_key]);
        // A Dutch company: its local number gets +31.
        $nl = $this->company(['name' => 'Licht BV', 'country' => 'NL', 'phone' => '020 123 4567']);
        $this->assertSame('+31 20 123 4567', $nl->phone);
        // A new contact with a local mobile, added to the Dutch company: +31 too.
        $p = Contact::create(['first_name' => 'Anke', 'mobile' => '06 12345678']);
        $nl->contacts()->attach($p->id, ['is_main' => true]);
        $this->assertSame(['+31 6 12345678', '31612345678'], [$p->fresh()->mobile, $p->fresh()->mobile_key]);
    }

    public function test_call_popup_opens_the_card_lists_several_and_offers_to_add_unknown_numbers(): void
    {
        $this->get('/call?number=091234567')->assertRedirect(); // not signed in
        $co = $this->company(['phone' => '+32 9 123 45 67']);
        $jan = Contact::create(['first_name' => 'Jan', 'last_name' => 'Peeters', 'mobile' => '0471 12 34 56']);
        $co->contacts()->attach($jan->id, ['is_main' => true, 'job_title' => 'Event manager']);

        // Linkus sends the number as the phone system knows it.
        $this->actingAs($this->sales, 'web')->get('/call?number=0032471123456&name=Jan')
            ->assertRedirect(route('companies.show', $co).'#contact-'.$jan->id)->assertSessionHas('status', 'Call from +32 471 12 34 56 · Jan Peeters');
        $this->actingAs($this->sales, 'web')->get('/call?number=%2B3291234567')->assertRedirect(route('companies.show', $co));
        $this->actingAs($this->sales, 'web')->get(route('companies.show', $co))->assertOk()
            ->assertSee('id="contact-'.$jan->id.'"', false)->assertSee('https://wa.me/32471123456', false)->assertSee('tel:+32471123456', false);

        // Two contacts share a number: a short list.
        $piet = Contact::create(['first_name' => 'Piet', 'phone' => '+32 9 123 45 67']);
        $co->contacts()->attach($piet->id);
        $this->actingAs($this->sales, 'web')->get('/call?number=091234567')->assertOk()->assertSee('Piet')->assertSee('Call from +32 9 123 45 67');

        // Unknown: offer a new company with the number filled in.
        $r = $this->actingAs($this->sales, 'web')->get('/call?number=%2B32 2 555 66 77&name=Studio X')->assertOk()->assertSee('Unknown number');
        $r->assertSee(route('companies.create', ['phone' => '+32 2 555 66 77', 'name' => 'Studio X']));
        $this->actingAs($this->sales, 'web')->get(route('companies.create', ['phone' => '+32 2 555 66 77', 'name' => 'Studio X']))->assertOk()->assertSee('value="+32 2 555 66 77"', false)->assertSee('value="Studio X"', false);
        // No number: the Linkus set-up.
        $this->actingAs($this->sales, 'web')->get('/call')->assertOk()->assertSee('{{.CallerNumber}}', false);
        // Search the list by phone number, typed any way.
        $this->actingAs($this->sales, 'web')->get(route('companies.index', ['view' => 'contacts', 'q' => '0471/123 456']))->assertOk()->assertSee('Peeters');
        $this->actingAs($this->sales, 'web')->get(route('companies.index', ['q' => '09 123 45 67']))->assertOk()->assertSee('Demo Agency BV');
        // Only staff with access to companies; the empty Communication menu is gone.
        $this->actingAs($this->sales, 'web')->get('/dashboard')->assertDontSee('/media/communication', false);
        foreach (['nl' => 'Onbekend nummer', 'fr' => 'Numéro inconnu', 'es' => 'Número desconocido'] as $loc => $t) {
            $this->sales->update(['locale' => $loc]);
            $this->actingAs($this->sales->fresh(), 'web')->get('/call?number=025556678')->assertOk()->assertSee($t);
        }
    }
}
