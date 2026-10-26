<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keep the whole text of a post: hashtags often stand at the end of a long post. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', fn (Blueprint $t) => $t->text('text')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('social_posts', fn (Blueprint $t) => $t->string('text', 1000)->nullable()->change());
    }
};
