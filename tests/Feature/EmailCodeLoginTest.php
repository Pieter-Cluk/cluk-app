<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StaffLoginCode;
use App\Models\User;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailCodeLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    private function freelancer(array $a = []): User
    {
        return User::factory()->create($a + ['email' => 'jens@cluk.live', 'email_login' => true, 'code_email' => 'jens.private@gmail.com', 'role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
    }

    private function codeFor(User $u): string
    {
        $code = null;
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, function ($n, $c, $to) use (&$code, $u) {
            if ($to->routes['mail'] === $u->code_email && str_contains($n->subject, 'sign-in code')) {
                $code = array_values($n->details)[0];
            }

            return $code !== null;
        });

        return (string) $code;
    }

    public function test_a_freelancer_signs_in_with_a_code_by_email(): void
    {
        $u = $this->freelancer();
        $this->get('/login')->assertSee('Sign in with a code by email');
        $this->post('/login/code', ['email' => 'Jens@cluk.live'])->assertRedirect('/login/code');
        $this->get('/login/code')->assertSee('a code is on its way');
        $code = $this->codeFor($u);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, StaffLoginCode::first()->code_hash, 'only a hash is kept');

        $this->post('/login/code/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/login/code/verify', ['code' => $code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout');
        // Used once only.
        $this->withSession(['code_email' => 'jens@cluk.live'])->post('/login/code/verify', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_only_allowed_cluk_live_people_get_a_code_and_codes_run_out(): void
    {
        $this->post('/login/code', ['email' => 'jens@gmail.com'])->assertSessionHasErrors('email');
        $m365 = User::factory()->create(['email' => 'pieter@cluk.live', 'email_login' => false, 'role_id' => Role::where('slug', 'admin')->value('id')]);
        $this->post('/login/code', ['email' => 'pieter@cluk.live'])->assertRedirect('/login/code');
        $this->post('/login/code', ['email' => 'nobody@cluk.live'])->assertRedirect('/login/code');
        Notification::assertNothingSent();

        $u = $this->freelancer();
        $this->post('/login/code', ['email' => 'jens@cluk.live']);
        foreach (range(1, 5) as $i) {
            $this->post('/login/code/verify', ['code' => '111111']);
        }
        $this->post('/login/code/verify', ['code' => $this->codeFor($u)])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->freelancer(['email' => 'off@cluk.live', 'status' => 'disabled']);
        $this->post('/login/code', ['email' => 'off@cluk.live']);
        Notification::assertSentTimes(Confirmation::class, 1);
    }

    public function test_admin_ticks_email_login_only_for_company_addresses(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $this->actingAs($admin)->get('/settings/users/new')->assertOk()->assertSee('May sign in with a code by email');
        $role = Role::where('slug', 'warehouse')->value('id');
        $this->actingAs($admin)->post('/settings/users', ['first_name' => 'Free', 'last_name' => 'Lancer', 'email' => 'free@gmail.com', 'locale' => 'en', 'role_id' => $role, 'email_login' => '1'])->assertSessionHasErrors('email_login');
        $this->actingAs($admin)->post('/settings/users', ['first_name' => 'Free', 'last_name' => 'Lancer', 'email' => 'free@cluk.live', 'locale' => 'en', 'role_id' => $role, 'email_login' => '1'])->assertSessionHasErrors('code_email');
        $this->actingAs($admin)->post('/settings/users', ['first_name' => 'Free', 'last_name' => 'Lancer', 'email' => 'free@cluk.live', 'locale' => 'en', 'role_id' => $role, 'email_login' => '1', 'code_email' => 'Free.Lancer@Gmail.com'])->assertSessionHasNoErrors();
        $free = User::where('email', 'free@cluk.live')->firstOrFail();
        $this->assertTrue($free->email_login);
        $this->assertSame('free.lancer@gmail.com', $free->code_email);

        // Sign-in instructions, as often as needed, to the private address.
        $this->actingAs($admin)->get('/settings/users/'.$free->id)->assertSee('Send sign-in instructions');
        $this->actingAs($admin)->post('/settings/users/'.$free->id.'/instructions')->assertSessionHas('status', 'Sign-in instructions sent to free.lancer@gmail.com.');
        $this->actingAs($admin)->post('/settings/users/'.$free->id.'/instructions');
        Notification::assertSentTimes(Confirmation::class, 2);
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, fn ($n, $c, $to) => $to->routes['mail'] === 'free.lancer@gmail.com' && $n->details['Type this address'] === 'free@cluk.live');
    }
}
