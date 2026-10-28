<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Wall screens became their own right (it came with the warehouse module). Off for everyone, except the admin role.
        foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $roleId) {
            DB::table('role_rights')->insertOrIgnore(['role_id' => $roleId, 'right' => 'wall.screens']);
        }
    }

    public function down(): void
    {
        DB::table('role_rights')->where('right', 'wall.screens')->delete();
        DB::table('user_rights')->where('right', 'wall.screens')->delete();
    }
};
