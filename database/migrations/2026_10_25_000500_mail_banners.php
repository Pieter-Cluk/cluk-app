<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Email banners (Media › Messages): a link and an order per banner, and how far each person is in the rotation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->string('link_url', 500)->nullable()->after('pdf_name');
            $t->unsignedInteger('position')->default(0)->after('link_url');
        });
        Schema::create('mail_banner_turns', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->unique();
            $t->unsignedInteger('mails')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_banner_turns');
        Schema::table('messages', fn (Blueprint $t) => $t->dropColumn(['link_url', 'position']));
    }
};
