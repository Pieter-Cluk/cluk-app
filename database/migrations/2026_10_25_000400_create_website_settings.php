<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media › Website: what the homepage, dry-hire and sales pages show.
 * web_slides: pictures and videos per place (one = static, more = slideshow), each with where a click goes.
 * web_settings: page texts per language, the homepage tiles, popular searches, social links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_slides', function (Blueprint $t) {
            $t->id();
            $t->string('place', 20);                 // home_hero, dryhire_hero, sales_slider
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('kind', 8)->default('image'); // image, video
            $t->string('path', 255);
            $t->string('poster_path', 255)->nullable();
            $t->string('link_type', 16)->default('none');
            $t->string('link_value', 255)->nullable();
            $t->json('texts')->nullable();            // {en: {eyebrow, title, text, cta}, nl: …}
            $t->string('alt', 160)->nullable();
            $t->boolean('active')->default(true);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['place', 'active', 'position']);
        });
        Schema::create('web_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->json('value')->nullable();
            $t->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_settings');
        Schema::dropIfExists('web_slides');
    }
};
