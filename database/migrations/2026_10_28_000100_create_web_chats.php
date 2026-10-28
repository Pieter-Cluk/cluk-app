<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clukie: chats on the sales pages of the website.
 * web_chats.status: bot (Clukie answers), waiting (handed to the team), staff (a team member answers), closed.
 * web_chat_messages.role: visitor, clukie, staff, note (a line from the system, e.g. "handed to the team").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_chats', function (Blueprint $t) {
            $t->id();
            $t->string('token', 48)->unique();
            $t->string('visitor_uuid', 36)->nullable()->index();
            $t->unsignedBigInteger('customer_account_id')->nullable()->index();
            $t->string('locale', 5)->default('en');
            $t->string('status', 10)->default('bot')->index();
            $t->string('name', 160)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('phone', 60)->nullable();
            $t->string('page', 255)->nullable();
            $t->foreignId('incoming_message_id')->nullable()->constrained('incoming_messages')->nullOnDelete();
            $t->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('handed_over_at')->nullable();
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('web_chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_id')->constrained('web_chats')->cascadeOnDelete();
            $t->string('role', 8);
            $t->text('body');
            $t->json('meta')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index(['chat_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_chat_messages');
        Schema::dropIfExists('web_chats');
    }
};
