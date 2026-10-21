<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MemberApproved;
use App\Notifications\MemberResetPassword;
use App\Notifications\MemberVerifyEmail;
use App\Services\Vies;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Afrit6MembersTest extends TestCase
{
    use RefreshDatabase;

    private bool|string $vies = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([Vies::URL => fn () => $this->vies === 'down'
            ? Http::response([], 503)
            : Http::response(['valid' => $this->vies, 'name' => 'DEMO STUDIO BV', 'address' => 'Stationsstraat 1, 9870 Zulte'])]);
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]);
    }

    private function details(array $override = []): array
    {
        return $override + [
            'first_name' => 'Lien', 'last_name' => 'De Smet', 'email' => 'Lien@DemoStudio.be',
            'mobile' => '+32 470 00 00 04', 'password' => 'Werkplek2026', 'terms' => '1', 'rules' => '1', 'payment_terms' => '1', 'newsletter' => '1',
        ];
    }

    // --------------------------------------------------------- registration

    public function test_registration_with_a_valid_vat_number(): void
    {
        $this->get('/afrit6')->assertOk()->assertSee(route('afrit6.register'));
        $this->get('/afrit6/register')->assertOk();

        $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => '1010.948.054'])
            ->assertRedirect('/afrit6/register/details');
        $this->get('/afrit6/register/details')->assertOk()->assertSee('DEMO STUDIO BV');

        $this->post('/afrit6/register/details', $this->details())->assertRedirect('/afrit6/check-inbox');

        $member = Member::firstOrFail();
        $this->assertSame('lien@demostudio.be', $member->email);
        $this->assertSame('BE1010948054', $member->reg_vat_number);
        $this->assertSame('valid', $member->vies_status);
        $this->assertSame('DEMO STUDIO BV', $member->reg_company_name);
        $this->assertSame(Member::PENDING, $member->status);
        $this->assertNull($member->email_verified_at);
        $this->assertTrue($member->newsletter);
        $this->assertNotNull($member->terms_accepted_at);
        $this->assertTrue(Hash::check('Werkplek2026', $member->password));
        $this->assertNotSame('Werkplek2026', $member->password, 'never stored as typed');

        Notification::assertSentTo($member, MemberVerifyEmail::class);
        $this->get('/afrit6/check-inbox')->assertOk()->assertSee('lien@demostudio.be');
    }

    public function test_without_a_valid_vat_number_name_and_address_are_asked(): void
    {
        $this->vies = false;
        $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => 'BE1010948054'])
            ->assertRedirect('/afrit6/register');
        $this->get('/afrit6/register')->assertSee('name="company_name"', false);

        $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => 'BE1010948054', 'company_name' => 'Studio Lien', 'address' => 'Stationsstraat 1, 9870 Zulte'])
            ->assertRedirect('/afrit6/register/details');
        Http::assertSentCount(1); // the same number is not asked twice

        // No VAT number at all is fine too, with a name and address.
        $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => '', 'company_name' => 'Freelance Lien', 'address' => 'Somewhere 1, Gent'])
            ->assertRedirect('/afrit6/register/details');

        // A Belgian number with wrong check digits is refused straight away.
        $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => '1010.948.055'])->assertSessionHasErrors('vat_number');
    }

    public function test_password_rules(): void
    {
        $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => 'BE1010948054']);

        foreach (['short1', 'onlyletterslong', 'Lien2026abc', '1234567890'] as $weak) {
            $this->post('/afrit6/register/details', $this->details(['password' => $weak]))->assertSessionHasErrors('password');
        }
        $this->post('/afrit6/register/details', $this->details(['terms' => null]))->assertSessionHasErrors('terms');
        $this->assertSame(0, Member::count());
    }

    public function test_the_email_link_confirms_and_logs_in(): void
    {
        $member = Member::factory()->create();

        $this->get('/afrit6/verify/'.$member->id.'/'.sha1($member->email))->assertForbidden(); // unsigned
        $this->get(MemberVerifyEmail::url($member))->assertRedirect('/afrit6/portal');

        $this->assertNotNull($member->fresh()->email_verified_at);
        $this->assertTrue(Auth::guard('member')->check());
        $this->get('/afrit6/portal')->assertOk()->assertSee(__('afrit6.status.waiting'));
    }

    // ---------------------------------------------------------------- login

    public function test_login_follows_the_account_state(): void
    {
        $unverified = Member::factory()->create(['email' => 'new@demo.be']);
        $this->post('/afrit6/login', ['email' => 'new@demo.be', 'password' => 'Cowork2026!'])->assertRedirect('/afrit6/check-inbox');
        $this->assertFalse(Auth::guard('member')->check());

        $this->post('/afrit6/login', ['email' => 'new@demo.be', 'password' => 'wrong'])->assertSessionHasErrors('email');

        Member::factory()->create(['email' => 'blocked@demo.be', 'status' => Member::BLOCKED, 'email_verified_at' => now()]);
        $this->post('/afrit6/login', ['email' => 'blocked@demo.be', 'password' => 'Cowork2026!'])->assertSessionHasErrors('email');

        $active = Member::factory()->active()->create(['email' => 'lien@demo.be']);
        $this->post('/afrit6/login', ['email' => 'Lien@Demo.be', 'password' => 'Cowork2026!'])->assertRedirect('/afrit6/portal');
        $this->get('/afrit6/portal')->assertOk()->assertSee(__('afrit6.portal.welcome', ['name' => $active->first_name]));
        $this->assertNotNull($active->fresh()->last_login_at);

        $this->post('/afrit6/logout')->assertRedirect('/afrit6');
        $this->get('/afrit6/portal')->assertRedirect('/afrit6');
    }

    public function test_too_many_wrong_passwords_are_slowed_down(): void
    {
        Member::factory()->active()->create(['email' => 'lien@demo.be']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/afrit6/login', ['email' => 'lien@demo.be', 'password' => 'wrong']);
        }
        $this->post('/afrit6/login', ['email' => 'lien@demo.be', 'password' => 'Cowork2026!'])
            ->assertSessionHasErrors('email');
        $this->assertFalse(Auth::guard('member')->check());
    }

    public function test_members_cannot_open_staff_pages(): void
    {
        $member = Member::factory()->active()->create();

        $this->actingAs($member, 'member')->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($member, 'member')->get('/afrit6-staff')->assertRedirect('/login');
    }

    public function test_staff_are_not_members(): void
    {
        $this->actingAs($this->staff('admin'), 'web')->get('/afrit6/portal')->assertRedirect('/afrit6');
    }

    public function test_forgotten_password(): void
    {
        $member = Member::factory()->active()->create(['first_name' => 'Lien', 'email' => 'lien@demo.be']);

        $this->post('/afrit6/forgot-password', ['email' => 'nobody@demo.be'])->assertSessionHas('status');
        $this->post('/afrit6/forgot-password', ['email' => 'lien@demo.be'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($member, MemberResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get('/afrit6/reset-password/'.$token.'?email=lien@demo.be')->assertOk();
        $this->post('/afrit6/reset-password', ['token' => $token, 'email' => 'lien@demo.be', 'password' => 'Lien12345678'])->assertSessionHasErrors('password');
        $this->post('/afrit6/reset-password', ['token' => $token, 'email' => 'lien@demo.be', 'password' => 'NieuwBureau77'])->assertRedirect('/afrit6');

        $this->assertTrue(Hash::check('NieuwBureau77', $member->fresh()->password));
        $this->post('/afrit6/reset-password', ['token' => $token, 'email' => 'lien@demo.be', 'password' => 'NogEenKeer88'])->assertSessionHasErrors('email');
    }

    // ---------------------------------------------------------------- staff

    public function test_staff_approve_a_registration_into_a_customer_and_contact(): void
    {
        $member = Member::factory()->verified()->create([
            'first_name' => 'Lien', 'last_name' => 'De Smet', 'email' => 'lien@demostudio.be',
            'reg_vat_number' => 'BE1010948054', 'reg_company_name' => 'Demo Studio BV', 'reg_address' => 'Stationsstraat 1, 9870 Zulte', 'vies_status' => 'valid',
        ]);
        $host = $this->staff('workspace-host');

        $this->actingAs($host)->get('/afrit6-staff')->assertOk()->assertSee('Lien De Smet');
        $this->actingAs($host)->get('/afrit6-staff/members/'.$member->id)->assertOk()->assertSee('Demo Studio BV')->assertDontSee($member->password);

        $this->actingAs($host)->post('/afrit6-staff/members/'.$member->id.'/approve')->assertRedirect();

        $member->refresh();
        $this->assertSame(Member::ACTIVE, $member->status);
        $this->assertSame($host->id, $member->decided_by_id);
        $company = $member->company;
        $this->assertSame('Demo Studio BV', $company->name);
        $this->assertSame('BE1010948054', $company->vat_number);
        $this->assertTrue($company->is_customer);
        $this->assertSame('valid', $company->vies_status);
        $this->assertTrue($company->contacts()->where('email', 'lien@demostudio.be')->exists());
        $this->assertSame(1, AuditLog::where('action', 'afrit6.member_approved')->count());
        Notification::assertSentTo($member, MemberApproved::class);
    }

    public function test_an_existing_company_with_the_same_vat_number_is_reused(): void
    {
        $existing = Company::factory()->create(['name' => 'Demo Studio', 'vat_number' => 'BE1010948054', 'is_customer' => false, 'is_supplier' => true]);
        $contact = Contact::factory()->create(['email' => 'lien@demostudio.be']);
        $member = Member::factory()->verified()->create(['email' => 'lien@demostudio.be', 'reg_vat_number' => 'BE1010948054']);

        $this->actingAs($this->staff('admin'))->post('/afrit6-staff/members/'.$member->id.'/approve');

        $this->assertSame(1, Company::count());
        $this->assertSame(1, Contact::count());
        $this->assertTrue($member->fresh()->company->is($existing));
        $this->assertTrue($existing->fresh()->is_customer && $existing->fresh()->is_supplier);
        $this->assertSame($contact->id, $member->fresh()->contact_id);
    }

    public function test_an_unconfirmed_email_needs_an_explicit_check(): void
    {
        $member = Member::factory()->create();
        $admin = $this->staff('admin');

        $this->actingAs($admin)->post('/afrit6-staff/members/'.$member->id.'/approve')->assertSessionHasErrors('email_checked');
        $this->assertSame(Member::PENDING, $member->fresh()->status);

        $this->actingAs($admin)->post('/afrit6-staff/members/'.$member->id.'/approve', ['email_checked' => '1'])->assertSessionHasNoErrors();
        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_only_people_with_the_right_can_decide_and_everything_is_logged(): void
    {
        $member = Member::factory()->verified()->create();
        $readOnly = $this->staff('read-only'); // sees Afrit 6, no approval right

        $this->actingAs($readOnly)->get('/afrit6-staff/members/'.$member->id)->assertOk()->assertDontSee(route('afrit6.staff.approve', $member));
        $this->actingAs($readOnly)->post('/afrit6-staff/members/'.$member->id.'/approve')->assertForbidden();

        $host = $this->staff('workspace-host');
        $this->actingAs($host)->post('/afrit6-staff/members/'.$member->id.'/decline', ['reason' => 'Unknown company'])->assertRedirect();
        $this->assertSame(Member::DECLINED, $member->fresh()->status);

        $this->actingAs($host)->post('/afrit6-staff/members/'.$member->id.'/reset-link');
        Notification::assertSentTo($member, MemberResetPassword::class);

        $this->actingAs($host)->post('/afrit6-staff/members/'.$member->id.'/block');
        $this->assertSame(Member::BLOCKED, $member->fresh()->status);

        $this->assertSame(
            ['afrit6.member_declined', 'afrit6.reset_link_sent', 'afrit6.member_blocked'],
            AuditLog::orderBy('id')->pluck('action')->all()
        );
    }

    public function test_a_blocked_member_is_signed_out_on_the_next_click(): void
    {
        $member = Member::factory()->active()->create();
        $this->actingAs($member, 'member')->get('/afrit6/portal')->assertOk();

        $member->forceFill(['status' => Member::BLOCKED])->save();

        $this->get('/afrit6/portal')->assertRedirect('/afrit6');
        $this->assertFalse(Auth::guard('member')->check());
    }

    public function test_the_pages_render_in_every_language(): void
    {
        $member = Member::factory()->verified()->create(['reg_vat_number' => 'BE1010948054', 'vies_status' => 'valid']);
        $active = Member::factory()->active()->create();
        $admin = $this->staff('admin');
        $pattern = '/\b(afrit6|companies|users|nav|login)\.[a-z_]+(\.[a-z_]+)?\b/';

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $this->withSession(['locale' => $locale]);
            foreach (['/afrit6', '/afrit6/register', '/afrit6/forgot-password', '/afrit6/reset-password/abc'] as $url) {
                $html = $this->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
            $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => 'BE1010948054']);
            $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($this->get('/afrit6/register/details')->getContent()));

            $member->forceFill(['locale' => $locale])->save();
            $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($this->actingAs($member, 'member')->get('/afrit6/portal')->assertOk()->getContent()));
            $active->forceFill(['locale' => $locale])->save();
            $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($this->actingAs($active, 'member')->get('/afrit6/portal')->assertOk()->getContent()));

            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/afrit6-staff', '/afrit6-staff?tab=active', '/afrit6-staff/members/'.$member->id] as $url) {
                $html = $this->actingAs($admin->fresh(), 'web')->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
            $this->flushSession();
            $this->app['auth']->forgetGuards();
        }
    }

    // ------------------------------------------------------- rules & payment terms

    public function test_registration_needs_the_rules_and_payment_terms_and_stores_them(): void
    {
        $this->post('/afrit6/register', ['country' => 'BE', 'vat_number' => '1010.948.054']);
        $this->get('/afrit6/register/details')->assertOk()
            ->assertSee(route('afrit6.terms', 'rules'), false)->assertSee(route('afrit6.terms', 'payment'), false)->assertSee('8%');

        $this->post('/afrit6/register/details', $this->details(['rules' => null]))->assertSessionHasErrors('rules');
        $this->post('/afrit6/register/details', $this->details(['payment_terms' => null]))->assertSessionHasErrors('payment_terms');
        $this->post('/afrit6/register/details', $this->details())->assertRedirect('/afrit6/check-inbox');

        $m = Member::firstOrFail();
        $this->assertNotNull($m->rules_accepted_at);
        $this->assertNotNull($m->payment_terms_accepted_at);
        $this->assertSame(config('afrit6.terms_version'), $m->terms_version);
    }

    public function test_the_terms_pages_are_public_and_translated(): void
    {
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $this->withSession(['locale' => $locale]);
            foreach (['rules', 'payment'] as $doc) {
                $html = $this->get('/afrit6/terms/'.$doc)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\bterms\.[a-z_]+/', strip_tags($html));
            }
        }
        $this->withSession(['locale' => 'en'])->get('/afrit6/terms/payment')->assertSee('8%')->assertSee('30 days');
        $this->get('/afrit6/terms/other')->assertNotFound();
    }

    public function test_existing_members_accept_the_terms_before_using_the_portal(): void
    {
        $m = Member::factory()->active()->withoutTerms()->create();

        $this->actingAs($m, 'member')->get('/afrit6/portal')->assertRedirect('/afrit6/accept');
        $this->actingAs($m, 'member')->get('/afrit6/portal/desks')->assertRedirect('/afrit6/accept');
        $this->actingAs($m, 'member')->post('/afrit6/accept', ['rules' => '1'])->assertSessionHasErrors('payment_terms');
        $this->actingAs($m, 'member')->post('/afrit6/accept', ['rules' => '1', 'payment_terms' => '1'])->assertRedirect('/afrit6/portal');

        $this->assertTrue($m->fresh()->hasCurrentTerms());
        $this->assertTrue(AuditLog::where('action', 'afrit6.terms_accepted')->where('subject_id', $m->id)->exists());
        $this->actingAs($m->fresh(), 'member')->get('/afrit6/portal')->assertOk();

        // A new version of the texts asks again.
        config(['afrit6.terms_version' => '2027-01']);
        $this->actingAs($m->fresh(), 'member')->get('/afrit6/portal')->assertRedirect('/afrit6/accept');
    }
}
