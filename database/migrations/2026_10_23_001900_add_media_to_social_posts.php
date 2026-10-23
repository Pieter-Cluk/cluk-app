<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Website: posts keep their own copy of the picture and video (Meta links expire) and can be hidden from the site. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $t) {
            $t->string('video_url', 1000)->nullable()->after('thumb_url');
            $t->string('thumb_path', 300)->nullable()->after('video_url');
            $t->string('video_path', 300)->nullable()->after('thumb_path');
            $t->boolean('on_website')->default(true)->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', fn (Blueprint $t) => $t->dropColumn(['video_url', 'thumb_path', 'video_path', 'on_website']));
    }
};
