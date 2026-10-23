<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media › Social media: the connected accounts (Facebook page, Instagram,
 * LinkedIn page, Google Business Profiles, YouTube…), their daily figures and
 * posts (read from the platforms), Google reviews, and posts planned in CLUK
 * and published to one or more accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 12);                  // facebook, instagram, linkedin, google, youtube, tiktok, threads
            $t->string('external_id', 191);
            $t->string('name', 191);
            $t->string('handle', 191)->nullable();
            $t->string('url', 255)->nullable();
            $t->string('avatar_url', 1000)->nullable();
            $t->text('token')->nullable();               // encrypted
            $t->text('refresh_token')->nullable();       // encrypted
            $t->timestamp('token_expires_at')->nullable();
            $t->json('meta')->nullable();
            $t->string('status', 12)->default('connected'); // connected, error, off
            $t->string('last_error', 500)->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamp('posts_synced_at')->nullable();
            $t->foreignId('connected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['platform', 'external_id']);
        });
        Schema::create('social_daily', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('social_accounts')->cascadeOnDelete();
            $t->date('date');
            $t->unsignedInteger('followers')->nullable();
            $t->unsignedInteger('reach')->nullable();       // people reached
            $t->unsignedInteger('views')->nullable();       // views / impressions
            $t->unsignedInteger('engagements')->nullable(); // likes, comments, shares, saves, clicks together
            $t->unsignedInteger('clicks')->nullable();      // link / website clicks
            $t->unique(['account_id', 'date']);
        });
        Schema::create('social_plans', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);                     // internal name, also the utm_campaign
            $t->text('body');
            $t->string('link_url', 500)->nullable();
            $t->json('media')->nullable();                // [{path, name, type: image|video}]
            $t->timestamp('scheduled_at')->nullable();
            $t->string('status', 12)->default('draft');   // draft, scheduled, publishing, done, partly, failed
            $t->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('scheduled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'scheduled_at']);
        });
        Schema::create('social_plan_targets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained('social_plans')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('social_accounts')->cascadeOnDelete();
            $t->text('body')->nullable();                 // own text for this account (else the plan's)
            $t->string('status', 12)->default('pending'); // pending, processing, published, failed
            $t->string('container_id', 191)->nullable();  // Instagram video: waiting for processing
            $t->string('external_id', 191)->nullable();
            $t->string('permalink', 500)->nullable();
            $t->string('error', 500)->nullable();
            $t->unsignedTinyInteger('tries')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['plan_id', 'account_id']);
        });
        Schema::create('social_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('social_accounts')->cascadeOnDelete();
            $t->string('external_id', 191);
            $t->timestamp('published_at')->nullable();
            $t->string('kind', 20)->nullable();           // post, photo, video, reel, carousel, article…
            $t->string('text', 1000)->nullable();
            $t->string('permalink', 500)->nullable();
            $t->string('thumb_url', 1000)->nullable();
            $t->unsignedInteger('views')->nullable();
            $t->unsignedInteger('reach')->nullable();
            $t->unsignedInteger('likes')->nullable();
            $t->unsignedInteger('comments')->nullable();
            $t->unsignedInteger('shares')->nullable();
            $t->unsignedInteger('saves')->nullable();
            $t->unsignedInteger('clicks')->nullable();
            $t->foreignId('plan_id')->nullable()->constrained('social_plans')->nullOnDelete();
            $t->timestamps();
            $t->unique(['account_id', 'external_id']);
            $t->index('published_at');
        });
        Schema::create('social_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('social_accounts')->cascadeOnDelete();
            $t->string('external_id', 191);
            $t->string('author', 191)->nullable();
            $t->unsignedTinyInteger('rating')->nullable();
            $t->text('text')->nullable();
            $t->text('reply')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_reviews');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_plan_targets');
        Schema::dropIfExists('social_plans');
        Schema::dropIfExists('social_daily');
        Schema::dropIfExists('social_accounts');
    }
};
