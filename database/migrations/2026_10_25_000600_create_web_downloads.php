<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents customers can download from the website (footer › Downloads):
 * price lists, technical sheets, forms, brochures… Files stay on the local disk
 * (folder web-downloads) and are served by the website, never as a public folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_downloads', function (Blueprint $t) {
            $t->id();
            $t->string('category', 20)->default('other');   // see config website.download_categories
            $t->json('title');                               // en, nl, fr, es (English as fallback)
            $t->json('description')->nullable();
            $t->string('path');                              // on the local disk
            $t->string('original_name', 200);
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->char('locale', 2)->nullable();               // language of the document; null = any
            $t->integer('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->unsignedInteger('downloads')->default(0);
            $t->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['active', 'category', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_downloads');
    }
};
