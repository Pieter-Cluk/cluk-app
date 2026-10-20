<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_install_creates_roles_and_the_first_admin_once(): void
    {
        $this->artisan('cluk:first-install')->assertSuccessful();

        $this->assertSame(8, Role::count());
        $pieter = User::where('email', 'pieter@cluk.live')->first();
        $this->assertNotNull($pieter);
        $this->assertSame('admin', $pieter->role->slug);
        $this->assertNull($pieter->password);

        // A later change in the app must survive the next deploy.
        Role::where('slug', 'sales')->first()->setLevels(['warehouse' => 2]);
        $this->artisan('cluk:first-install')->expectsOutputToContain('nothing to do')->assertSuccessful();

        $this->assertSame(2, Role::where('slug', 'sales')->first()->levelFor('warehouse'));
    }
}
