<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Website slides: an optional separate picture for phones (shaped for the phone frame). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_slides', fn (Blueprint $t) => $t->string('mobile_path')->nullable());
    }

    public function down(): void
    {
        Schema::table('web_slides', fn (Blueprint $t) => $t->dropColumn('mobile_path'));
    }
};
