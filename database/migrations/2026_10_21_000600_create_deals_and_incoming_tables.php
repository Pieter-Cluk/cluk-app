<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Requests coming in from the website, email, social media, WhatsApp,
        // phone or a visit, before they become (or join) a deal.
        Schema::create('incoming_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 12);              // website, email, linkedin, facebook, instagram, whatsapp, phone, visit, other
            $table->string('subject', 200);
            $table->text('body')->nullable();
            $table->string('from_name', 160)->nullable();
            $table->string('from_email', 190)->nullable();
            $table->string('from_phone', 40)->nullable();
            $table->string('company_name', 160)->nullable();
            $table->string('module', 12)->nullable();   // suggested: dryhire, factory, sales, afrit6
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('new'); // new, converted, linked, asked, dismissed
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('handled_note', 500)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
            $table->index(['status', 'received_at']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();     // D-2026-0001
            $table->string('title', 200);
            $table->string('module', 12);               // dryhire, factory, sales, afrit6
            $table->string('stage', 14)->default('new'); // new, qualified, proposal, negotiation, won, lost, disqualified
            $table->string('source', 12)->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('event_start')->nullable();
            $table->date('event_end')->nullable();
            $table->string('location', 160)->nullable();
            $table->unsignedInteger('value_cents')->default(0);
            $table->boolean('check_date')->default(false);     // date and location known
            $table->boolean('check_budget')->default(false);   // budget roughly clear
            $table->boolean('check_decider')->default(false);  // we know who decides
            $table->string('lost_reason', 30)->nullable();
            $table->string('competitor', 120)->nullable();
            $table->string('close_note', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('incoming_id')->nullable()->constrained('incoming_messages')->nullOnDelete();
            $table->timestamps();
            $table->index(['stage', 'module']);
        });

        Schema::create('deal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10)->default('note'); // note, call, email, meeting, stage, system
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_activities');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('incoming_messages');
    }
};
