<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** My preferences: look, start page, which emails, a daily summary, and who takes over while away. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('theme', 10)->default('auto');
            $t->string('density', 12)->default('comfortable');
            $t->string('start_page', 20)->nullable();
            $t->json('notify')->nullable();
            $t->string('daily_at', 5)->nullable();
            $t->date('daily_sent_on')->nullable();
            $t->date('away_from')->nullable();
            $t->date('away_until')->nullable();
            $t->foreignId('away_delegate_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('away_delegate_id');
            $t->dropColumn(['theme', 'density', 'start_page', 'notify', 'daily_at', 'daily_sent_on', 'away_from', 'away_until']);
        });
    }
};
