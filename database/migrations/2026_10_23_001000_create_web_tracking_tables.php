<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website visits, only for visitors who accepted statistics cookies.
 * A visitor is a random id in a first-party cookie; no IP address is kept.
 * Once the visitor sends a request, orders or signs in, the visitor is linked
 * to that email, contact, company or customer login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_visitors', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('locale', 2)->nullable();
            $t->string('device', 10)->nullable();          // mobile, tablet, desktop
            $t->string('referrer', 255)->nullable();
            $t->string('utm_source', 100)->nullable();
            $t->string('utm_medium', 100)->nullable();
            $t->string('utm_campaign', 100)->nullable();
            $t->string('landing', 255)->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->string('email', 190)->nullable();
            $t->foreignId('customer_account_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->index('email');
            $t->index('last_seen_at');
        });
        Schema::create('web_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visitor_id')->constrained('web_visitors')->cascadeOnDelete();
            $t->string('type', 16);                         // view, search, product, add, request, order, login, register
            $t->string('section', 10)->nullable();          // home, dryhire, sales, account
            $t->string('path', 255)->nullable();
            $t->string('label', 255)->nullable();           // search words, product name, reference
            $t->unsignedBigInteger('item_id')->nullable();  // dry-hire item or sales product
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['visitor_id', 'created_at']);
            $t->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_events');
        Schema::dropIfExists('web_visitors');
    }
};
