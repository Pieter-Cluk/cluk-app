<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Media\Message;
use App\Models\Media\MessageSend;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MessageMail;
use App\Services\Media\Messages;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Media › Messages › Invitations: customers' contacts invited to register on the website, per country, in daily batches. */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
    }

    private function customer(string $name, string $country, array $emails, bool $customer = true, bool $vat = true): void
    {
        $co = Company::create(['name' => $name, 'is_customer' => $customer, 'is_supplier' => ! $customer, 'vat_regime' => 'auto', 'country' => $country, 'language' => $country === 'FR' ? 'fr' : 'nl', 'vat_number' => $vat ? $country.'0'.random_int(100000000, 999999999) : null]);
        foreach ($emails as $i => $e) {
            $co->contacts()->attach(Contact::create(['first_name' => 'P'.$i, 'email' => $e])->id);
        }
    }

    private function people(): void
    {
        $this->customer('Fr Co', 'FR', ['a@fr.example']);
        $this->customer('Be Co', 'BE', ['a@be.example', 'b@be.example', 'c@be.example']);
        $this->customer('Nl Co', 'NL', ['a@nl.example']);
        $this->customer('It Co', 'IT', ['a@it.example']);
        $this->customer('Supplier', 'BE', ['s@sup.example'], false);
        $this->customer('Uk Co', 'UK', ['a@uk.example']);
        $this->customer('No Vat Co', 'BE', ['a@novat.example'], true, false); // no VAT number: not invited
        Contact::find(DB::table('contacts')->where('email', 'c@be.example')->value('id'))->update(['promo_opt_out' => true]);
        DB::table('customer_accounts')->insert(['email' => 'b@be.example', 'password' => 'x', 'first_name' => 'B', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_draft_invitation_is_ready_in_four_languages(): void
    {
        $m = Messages::invitationDraft();
        $this->assertSame('draft', $m->status);
        foreach (['en', 'nl', 'fr', 'es'] as $l) {
            $this->assertNotSame('', $m->text('body', $l));
        }
        $this->assertFalse($m->translationsOutdated());
    }

    public function test_only_customers_contacts_without_an_account_country_by_country(): void
    {
        $this->people();
        $list = Messages::invitees();
        $this->assertSame(['a@fr.example', 'a@be.example', 'a@nl.example', 'a@it.example', 'a@uk.example'], array_keys($list));
        $this->assertSame('GB', $list['a@uk.example']['country']);

        $plan = Messages::plan($list, Carbon::parse('2026-11-06'), 10); // a Friday
        $this->assertSame(['BE', 'NL', 'FR', 'GB', 'IT'], array_keys($plan['countries']));
        $this->assertSame('2026-11-06', $plan['countries']['BE']['from']);
        $this->assertSame('2026-11-09', $plan['countries']['NL']['from']); // the weekend is skipped
        $this->assertSame('2026-11-10', $plan['countries']['FR']['from']); // each country on a new day
    }

    public function test_planned_invitations_go_out_day_by_day_in_office_hours(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->people();
        $m = Messages::invitationDraft();
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00', 'Europe/Brussels'));

        $this->actingAs($admin, 'web')->get(route('messages.edit', $m))->assertOk()->assertSee('Plan the sending');
        $this->actingAs($admin, 'web')->post(route('messages.send', $m))->assertStatus(409); // never all at once
        $this->actingAs($admin, 'web')->post(route('messages.plan', $m), ['start' => '2026-11-06', 'per_day' => 10, 'countries' => 'BE, NL, LU, FR, UK, DE'])->assertSessionHasNoErrors();
        $m->refresh();
        $this->assertSame('scheduled', $m->status);
        $this->assertSame(['BE', 'NL', 'LU', 'FR', 'GB', 'DE'], $m->plan['countries']);

        Carbon::setTestNow(Carbon::parse('2026-11-06 07:30', 'Europe/Brussels'));
        Messages::work();
        $this->assertSame('scheduled', $m->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-06 09:05', 'Europe/Brussels'));
        Messages::work();
        $this->assertSame('sending', $m->fresh()->status);
        $this->assertSame(['a@be.example'], MessageSend::where('status', 'sent')->pluck('email')->all());
        Notification::assertSentTo(new AnonymousNotifiable, MessageMail::class, fn (MessageMail $n) => str_contains($n->button[1] ?? '', 'email=a%40be.example') && $n->button[0] === 'Nu registreren');

        // Someone registers before their day: skipped.
        DB::table('customer_accounts')->insert(['email' => 'a@nl.example', 'password' => 'x', 'first_name' => 'A', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin, 'web')->post(route('messages.pause', $m))->assertRedirect();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00', 'Europe/Brussels'));
        Messages::work();
        $this->assertSame(1, MessageSend::where('status', 'sent')->count()); // paused
        $this->actingAs($admin, 'web')->post(route('messages.pause', $m))->assertRedirect();
        Messages::work();
        $this->assertSame('skipped', MessageSend::where('email', 'a@nl.example')->value('status'));
        $this->assertSame('queued', MessageSend::where('email', 'a@fr.example')->value('status')); // its day is tomorrow

        Carbon::setTestNow(Carbon::parse('2026-11-12 15:59', 'Europe/Brussels'));
        Messages::work();
        $this->assertSame('sent', $m->fresh()->status);
        $this->assertSame(4, MessageSend::where('status', 'sent')->count());
        $this->actingAs($admin, 'web')->get(route('messages.edit', $m))->assertOk()->assertSee('1 already registered');
        Carbon::setTestNow();
    }
}
