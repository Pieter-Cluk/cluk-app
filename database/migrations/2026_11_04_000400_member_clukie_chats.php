<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clukie for Afrit 6 members: one conversation per member session (only the member sees it; kept 3 months). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_chats', function (Blueprint $t) {
            $t->id();
            $t->string('token', 48)->unique();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->string('locale', 5)->default('nl');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });
        Schema::create('member_chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_id')->constrained('member_chats')->cascadeOnDelete();
            $t->string('role', 10); // visitor (the member) or clukie
            $t->text('body');
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_chat_messages');
        Schema::dropIfExists('member_chats');
    }
};
