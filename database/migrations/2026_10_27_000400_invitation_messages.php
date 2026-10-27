<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media › Messages › Invitations: a mail to the contacts of our customers to register on the website, sent per country
 * in small daily batches (plan: start day, people per day, countries in order). A draft invitation is ready to plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->json('plan')->nullable();
        });
        Schema::table('message_sends', function (Blueprint $t) {
            $t->string('country', 2)->nullable();
            $t->date('planned_on')->nullable();
            $t->index(['message_id', 'status', 'planned_on']);
        });

        if (! app()->runningUnitTests()) {
            \App\Services\Media\Messages::invitationDraft();
        }
    }

    public function down(): void
    {
        DB::table('messages')->where('kind', 'invitation')->delete();
        Schema::table('message_sends', function (Blueprint $t) {
            $t->dropIndex(['message_id', 'status', 'planned_on']);
            $t->dropColumn(['country', 'planned_on']);
        });
        Schema::table('messages', fn (Blueprint $t) => $t->dropColumn('plan'));
    }
};
