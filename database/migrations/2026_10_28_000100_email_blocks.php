<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email addresses that bounced (undeliverable): no more mails go to them until someone checks the card and unblocks
 * the address or changes it. The record stays (cleared_at) as history.
 * Also: the invitation plan goes to 200 people a day (Pieter, 28/10/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_blocks', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->index();
            $t->string('reason', 20)->default('bounce'); // bounce
            $t->text('detail')->nullable();               // the reason in the bounce message
            $t->string('subject', 255)->nullable();       // subject of the mail that bounced
            $t->dateTime('bounced_at')->nullable();
            $t->foreignId('notify_user_id')->nullable()->constrained('users')->nullOnDelete(); // task for this person when the company has no account owner
            $t->dateTime('cleared_at')->nullable();
            $t->foreignId('cleared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        DB::table('messages')->where('kind', 'invitation')->where('status', 'draft')->get(['id', 'plan'])->each(function ($m) {
            $plan = json_decode((string) $m->plan, true) ?: [];
            if (($plan['per_day'] ?? 100) === 100) {
                $plan['per_day'] = 200;
                DB::table('messages')->where('id', $m->id)->update(['plan' => json_encode($plan)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_blocks');
    }
};
