<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A download can also be a link (a web tool such as the dB meter) instead of a file. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_downloads', function (Blueprint $t) {
            $t->string('url', 500)->nullable();
            $t->string('path')->nullable()->change();
            $t->string('original_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('web_downloads', fn (Blueprint $t) => $t->dropColumn('url'));
    }
};
