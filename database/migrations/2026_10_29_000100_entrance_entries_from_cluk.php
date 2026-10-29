<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The entrance TV agenda can also be filled by the CLUK team (Media › Messages › Entrance screen):
        // guests CLUK expects (no member) and short notices (e.g. "Lunch & learn 12:30").
        Schema::table('afrit6_guests', function (Blueprint $t) {
            $t->string('kind', 8)->default('guest')->after('id');   // guest | notice
            $t->foreignId('member_id')->nullable()->change();
            $t->foreignId('user_id')->nullable()->after('member_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('afrit6_guests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn('kind');
        });
    }
};
