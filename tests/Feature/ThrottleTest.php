<?php

namespace Tests\Feature;

use App\Models\Member;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_limits_count_per_member_and_per_action_not_per_office(): void
    {
        $this->seed(RoleSeeder::class);
        $a = Member::factory()->active()->create(['locale' => 'en']);
        $b = Member::factory()->active()->create(['locale' => 'en']);
        // Member A uses other limited actions a lot from the same office IP …
        foreach (range(1, 8) as $i) {
            $this->actingAs($a, 'member')->post('/afrit6/portal/services', []);
        }
        // … member B can still ask for a door code, and so can A.
        $this->actingAs($b, 'member')->post('/afrit6/portal/door-code', ['note' => 'x'])->assertRedirect();
        $this->actingAs($a, 'member')->post('/afrit6/portal/door-code')->assertRedirect();
        // Only the same person repeating the same action hits the limit.
        foreach (range(1, 4) as $i) {
            $this->actingAs($b, 'member')->post('/afrit6/portal/door-code');
        }
        $this->actingAs($b, 'member')->post('/afrit6/portal/door-code')->assertStatus(429);
    }
}
