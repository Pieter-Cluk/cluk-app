<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A team browser (someone signed in to the platform there): left out of the website statistics.
        Schema::table('web_visitors', function (Blueprint $t) {
            $t->boolean('internal')->default(false)->after('device');
        });
    }

    public function down(): void
    {
        Schema::table('web_visitors', fn (Blueprint $t) => $t->dropColumn('internal'));
    }
};
