<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Meta's picture and video links can be longer than 1,000 characters (older Instagram videos). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $t) {
            $t->text('thumb_url')->nullable()->change();
            $t->text('video_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $t) {
            $t->string('thumb_url', 1000)->nullable()->change();
            $t->string('video_url', 1000)->nullable()->change();
        });
    }
};
