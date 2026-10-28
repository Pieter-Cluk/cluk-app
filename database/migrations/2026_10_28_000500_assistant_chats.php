<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clukie for the team in the platform: each colleague's conversations (kept 3 months). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_chats', function (Blueprint $t) {
            $t->id();
            $t->string('token', 48)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5)->default('en');
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('assistant_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_id')->constrained('assistant_chats')->cascadeOnDelete();
            $t->string('role', 8);
            $t->text('body');
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_chats');
    }
};
