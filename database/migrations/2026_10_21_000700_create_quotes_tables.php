<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A rental quote. Each new version is a new row with the same number;
        // the earlier version becomes "replaced". Prices and the VAT rate are
        // frozen once a version is sent.
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24);                    // R-QUO-2026-0001
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 20)->default('draft');  // draft, sent, accepted, declined, change, replaced
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->char('language', 2)->default('nl');
            $table->dateTime('out_from');                    // leaves the warehouse (availability)
            $table->dateTime('out_until');                   // back in the warehouse
            $table->date('use_from');                        // the customer uses it (price)
            $table->date('use_until');
            $table->string('location', 200)->nullable();
            $table->text('intro')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('project_discount_bp')->default(0);
            $table->string('vat_code', 4)->nullable();
            $table->unsignedSmallInteger('vat_rate_bp')->default(0);
            $table->integer('subtotal_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('net_cents')->default(0);
            $table->integer('vat_cents')->default(0);
            $table->integer('gross_cents')->default(0);
            $table->string('token', 64)->nullable()->unique(); // customer link
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sent_to', 190)->nullable();
            $table->date('valid_until')->nullable();
            $table->dateTime('reserved_until')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->string('answered_name', 120)->nullable();
            $table->string('po_number', 80)->nullable();
            $table->string('answered_ip', 45)->nullable();
            $table->string('decline_reason', 40)->nullable();
            $table->text('answer_note')->nullable();
            $table->timestamps();
            $table->unique(['number', 'version']);
            $table->index(['status', 'out_from', 'out_until']);
        });

        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('kind', 12)->default('item');     // item, charge, text
            $table->foreignId('item_id')->nullable()->constrained('equipment_items')->nullOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('name', 200);
            $table->decimal('qty', 10, 2)->default(1);
            $table->integer('unit_price_cents')->default(0);
            $table->decimal('factor', 6, 2)->nullable();     // null = the quote's default factor
            $table->unsignedSmallInteger('discount_bp')->default(0);
            $table->integer('amount_cents')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');
    }
};
