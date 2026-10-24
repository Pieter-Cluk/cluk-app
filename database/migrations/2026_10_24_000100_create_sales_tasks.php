<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial tasks for the salespeople, made by the app: customer cards that need
 * attention, quotes open too long, customers active on the website / via social media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 12);                    // card, quote, web
            $t->string('key', 120)->unique();          // one task per thing, e.g. card:12, quote:44, web:12:2026-10-24
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('quote_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('level', 8);                    // red, orange, yellow, blue
            $t->json('data')->nullable();               // what is missing, days open, pages seen…
            $t->string('status', 10)->default('open');  // open, snoozed, done, dismissed, resolved
            $t->timestamp('snoozed_until')->nullable();
            $t->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_tasks');
    }
};
