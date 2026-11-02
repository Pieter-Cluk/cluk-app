<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The team Clukie starts with an empty chat in every new login session. */
class StaffClukieSessionTest extends TestCase
{
    use RefreshDatabase;

    private function store(User $u): string
    {
        $html = $this->actingAs($u, 'web')->get('/dashboard')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/"prefix":"clukie-staff-'.$u->id.'"/', $html);
        $this->assertStringContainsString('"max":2000', $html);
        preg_match('/"store":"(clukie-staff-'.$u->id.'-[0-9a-f]{10})"/', $html, $m);
        $this->assertNotEmpty($m, 'the chat key carries the login session');

        return $m[1];
    }

    public function test_a_new_login_session_gets_a_new_empty_chat(): void
    {
        config(['services.anthropic.key' => 'test-key', 'clukie.enabled' => true]);
        $u = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
        $first = $this->store($u);
        $this->assertSame($first, $this->store($u));      // same session: the chat stays while you move around

        $this->flushSession();
        $this->app['session']->driver()->setId(str_repeat('a', 40));
        $this->assertNotSame($first, $this->store($u));   // a new session: a new, empty chat
    }
}
