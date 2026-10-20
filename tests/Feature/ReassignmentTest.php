<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rule from the mockups: a task can be handed to someone with the same
 * role and job title, or to someone who reports to you (directly or further
 * down). Only the assignee or someone above them may reassign.
 */
class ReassignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $role = fn (string $slug) => Role::where('slug', $slug)->value('id');

        $make = function (string $key, string $first, string $title, string $roleSlug, ?string $boss) use ($role) {
            $this->p[$key] = User::factory()->create([
                'first_name' => $first, 'title' => $title, 'role_id' => $role($roleSlug),
                'reports_to_id' => $boss ? $this->p[$boss]->id : null,
            ]);
        };

        $make('pieter', 'Pieter', 'Managing director', 'admin', null);
        $make('ruben', 'Ruben', 'Production manager', 'manager', 'pieter');
        $make('lotte', 'Lotte', 'Afrit 6 host', 'workspace-host', 'ruben');
        $make('sofie', 'Sofie', 'Afrit 6 host', 'workspace-host', 'ruben');
        $make('emma', 'Emma', 'Afrit 6 cleaner', 'workspace-host', 'lotte');
        $make('jens', 'Jens', 'Warehouse manager', 'warehouse', 'ruben');
        $make('noah', 'Noah', 'Warehouse crew', 'warehouse', 'jens');
        $make('lars', 'Lars', 'Warehouse crew', 'warehouse', 'jens');
    }

    public function test_same_role_and_title_is_allowed(): void
    {
        $this->assertTrue($this->p['lotte']->canHandTaskTo($this->p['sofie']));
        $this->assertTrue($this->p['noah']->canHandTaskTo($this->p['lars']));
    }

    public function test_someone_who_reports_to_you_is_allowed(): void
    {
        $this->assertTrue($this->p['lotte']->canHandTaskTo($this->p['emma']));
        $this->assertTrue($this->p['pieter']->canHandTaskTo($this->p['noah']), 'further down the line counts too');
    }

    public function test_other_title_and_not_reporting_is_refused(): void
    {
        $this->assertFalse($this->p['lotte']->canHandTaskTo($this->p['jens']));
        $this->assertFalse($this->p['lotte']->canHandTaskTo($this->p['noah']));
        $this->assertFalse($this->p['noah']->canHandTaskTo($this->p['jens']), 'not upwards');
        $this->assertFalse($this->p['emma']->canHandTaskTo($this->p['lotte']));
    }

    public function test_same_title_but_other_role_is_refused(): void
    {
        $this->p['sofie']->update(['role_id' => Role::where('slug', 'sales')->value('id')]);

        $this->assertFalse($this->p['lotte']->canHandTaskTo($this->p['sofie']->fresh()));
    }

    public function test_only_the_assignee_or_someone_above_may_reassign(): void
    {
        $this->assertTrue($this->p['lotte']->canReassignFrom($this->p['lotte']));
        $this->assertTrue($this->p['ruben']->canReassignFrom($this->p['lotte']));
        $this->assertTrue($this->p['pieter']->canReassignFrom($this->p['emma']));
        $this->assertFalse($this->p['noah']->canReassignFrom($this->p['lotte']));
        $this->assertFalse($this->p['sofie']->canReassignFrom($this->p['lotte']));
    }

    public function test_disabled_people_cannot_receive_tasks(): void
    {
        $this->p['sofie']->update(['status' => User::STATUS_DISABLED]);

        $this->assertFalse($this->p['lotte']->canHandTaskTo($this->p['sofie']->fresh()));
    }

    public function test_a_loop_in_the_reporting_line_does_not_hang(): void
    {
        $this->p['ruben']->update(['reports_to_id' => $this->p['lotte']->id]); // wrong data: a loop

        $chain = $this->p['lotte']->fresh()->managerChainIds();

        $this->assertContains($this->p['ruben']->id, $chain);
        $this->assertFalse($this->p['lotte']->fresh()->reportsToUser($this->p['lars']));
    }
}
