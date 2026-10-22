<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StaffLoginCode;
use App\Models\User;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return User::factory()->create($a + ['email' => 'jens@cluk.live', 'email_login' => true, 'role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
    }

    private function codeFor(User $u): string
    {
        $code = null;
        Notification::assertSentTo($u, Confirmation::class, function ($n) use (&$code) {
            $code = array_values($n->details)[0];

            return true;
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
        $this->actingAs($admin)->post('/settings/users', ['first_name' => 'Free', 'last_name' => 'Lancer', 'email' => 'free@cluk.live', 'locale' => 'en', 'role_id' => $role, 'email_login' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'free@cluk.live')->value('email_login'));
    }
}
