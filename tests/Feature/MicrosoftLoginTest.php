<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as MicrosoftAccount;
use Mockery;
use Tests\TestCase;

class MicrosoftLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function microsoftReturns(string $id, string $email): void
    {
        $account = (new MicrosoftAccount)->map(['id' => $id, 'email' => $email, 'name' => 'Test Person']);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($account);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);
    }

    private function staff(array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'email' => 'lotte@cluk.live',
            'password' => null,
            'role_id' => Role::where('slug', 'workspace-host')->value('id'),
        ]);
    }

    public function test_the_button_sends_you_to_microsoft(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://login.microsoftonline.com/example'));
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $this->get('/auth/microsoft/redirect')->assertRedirect('https://login.microsoftonline.com/example');
    }

    public function test_a_known_colleague_gets_in_and_the_microsoft_id_is_remembered(): void
    {
        $user = $this->staff();
        $this->microsoftReturns('ms-123', 'Lotte@Cluk.live');

        $this->get('/auth/microsoft/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('ms-123', $user->fresh()->microsoft_id);
        $this->assertNotNull($user->fresh()->last_seen_at);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('subject_id', $user->id)->exists());
    }

    public function test_the_microsoft_id_is_used_even_if_the_email_changed(): void
    {
        $user = $this->staff(['microsoft_id' => 'ms-123', 'email' => 'old@cluk.live']);
        $this->microsoftReturns('ms-123', 'new@cluk.live');

        $this->get('/auth/microsoft/callback');

        $this->assertAuthenticatedAs($user);
    }

    public function test_someone_not_added_as_user_is_refused(): void
    {
        $this->microsoftReturns('ms-999', 'femke@cluk.live');

        $this->get('/auth/microsoft/callback')
            ->assertRedirect('/login')
            ->assertSessionHas('login_error', 'not_listed');

        $this->assertGuest();
        $this->get('/login')->assertSee('femke@cluk.live');
    }

    public function test_a_disabled_colleague_is_refused(): void
    {
        $this->staff(['status' => User::STATUS_DISABLED]);
        $this->microsoftReturns('ms-123', 'lotte@cluk.live');

        $this->get('/auth/microsoft/callback')->assertSessionHas('login_error', 'disabled');

        $this->assertGuest();
    }

    public function test_a_failed_microsoft_answer_shows_a_friendly_error(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('invalid state'));
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $this->get('/auth/microsoft/callback')->assertSessionHas('login_error', 'failed');
        $this->assertGuest();
    }

    public function test_the_dashboard_shows_only_your_modules_and_you_can_log_out(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Cluk Workspace (Afrit 6)')
            ->assertDontSee('Pre-invoicing');

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_the_login_page_follows_the_chosen_language(): void
    {
        $this->post('/locale/nl');

        $this->get('/login')->assertSee('Aanmelden met Microsoft');
    }
}
