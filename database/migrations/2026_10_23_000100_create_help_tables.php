<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Help & shortcuts: articles to search, forms to download, questions for the admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('topic', 20)->index();
            $t->json('title');
            $t->json('intro')->nullable();
            $t->json('body')->nullable();
            $t->text('keywords')->nullable();
            $t->string('link_path', 255)->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->integer('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('help_files', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->string('note', 255)->nullable();
            $t->string('topic', 20)->nullable();
            $t->string('path', 255);
            $t->string('original_name', 255);
            $t->unsignedInteger('size')->default(0);
            $t->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('help_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('question');
            $t->string('page', 255)->nullable();
            $t->string('status', 12)->default('open')->index();
            $t->text('answer')->nullable();
            $t->foreignId('answered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('answered_at')->nullable();
            $t->foreignId('article_id')->nullable()->constrained('help_articles')->nullOnDelete();
            $t->timestamps();
        });

        // The first articles; admins add and change them in the app from here on.
        $now = now();
        foreach (require database_path('data/help_articles.php') as $i => $a) {
            DB::table('help_articles')->insert([
                'slug' => $a['slug'], 'topic' => $a['topic'], 'title' => json_encode($a['title'], JSON_UNESCAPED_UNICODE),
                'intro' => json_encode($a['intro'], JSON_UNESCAPED_UNICODE), 'body' => json_encode($a['body'], JSON_UNESCAPED_UNICODE),
                'keywords' => $a['keywords'] ?? null, 'link_path' => $a['link'] ?? null, 'sort' => $i, 'active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_questions');
        Schema::dropIfExists('help_files');
        Schema::dropIfExists('help_articles');
    }
};
