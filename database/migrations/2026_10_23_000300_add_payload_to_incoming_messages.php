<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Website requests keep their structured content (lines, dates, language) next to the readable text. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_messages', fn (Blueprint $t) => $t->json('payload')->nullable()->after('body'));
    }

    public function down(): void
    {
        Schema::table('incoming_messages', fn (Blueprint $t) => $t->dropColumn('payload'));
    }
};
