<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A Clukie chat can be linked (by the team, by hand) to the company and contact it is about. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_chats', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('web_chats', function (Blueprint $t) {
            $t->dropConstrainedForeignId('company_id');
            $t->dropConstrainedForeignId('contact_id');
        });
    }
};
