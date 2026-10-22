<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Media › Messages: birthday card, newsletters, promotions and the quote banner, with who got what. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('message_sends');
        Schema::dropIfExists('messages');
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);                      // birthday, newsletter, promotion, banner
            $table->string('title', 160);                    // internal name
            $table->json('subject')->nullable();             // {en, nl, fr, es}
            $table->json('body')->nullable();                // {en, nl, fr, es}
            $table->string('source_hash', 64)->nullable();   // the English text the translations were made from
            $table->string('image_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_name', 190)->nullable();
            $table->string('status', 12)->default('draft');  // draft, scheduled, sending, sent
            $table->dateTime('send_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->boolean('active')->default(false);       // birthday card and banner in use
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'status']);
        });
        Schema::create('message_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('email', 190);
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 5)->default('en');
            $table->string('status', 10)->default('queued'); // queued, sent, failed
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['message_id', 'email'], 'msg_sends_message_email_unique');
            $table->index(['message_id', 'status']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'promo_opt_out')) {
                $table->boolean('promo_opt_out')->default(false);
            }
        });
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'promo_opt_out')) {
                $table->boolean('promo_opt_out')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn('promo_opt_out'));
        Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn('promo_opt_out'));
        Schema::dropIfExists('message_sends');
        Schema::dropIfExists('messages');
    }
};
