<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // The first admin. Signs in with Microsoft 365 (no password stored).
        User::updateOrCreate(['email' => 'pieter@cluk.live'], [
            'first_name' => 'Pieter',
            'last_name' => 'Van de Velde',
            'title' => 'Managing director',
            'department' => 'Management',
            'locale' => 'nl',
            'status' => User::STATUS_ACTIVE,
            'is_commercial' => true,
            'role_id' => Role::where('slug', 'admin')->value('id'),
        ]);
    }
}
