<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some customers ask a PO number on every invoice.
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('po_required')->default(false)->after('customer_payment_days');
        });

        // Every proforma passes here: the accountant checks it, then it goes to OkiOki or back to its maker.
        Schema::create('pre_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();            // PF-2026-0001
            $table->string('source_type', 12);                  // rental, charges, factory, sales, afrit6
            $table->unsignedBigInteger('source_id');
            $table->string('source_code', 40)->nullable();      // R-PF-…, S-PF-…, F-PF-…, R-CH-…, A6S
            $table->string('module', 12);                       // dryhire, factory, sales, afrit6, warehouse
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('made_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // salesperson credited
            $table->string('description', 200);
            $table->string('status', 10)->default('check');     // check, back, sent, paid, cancelled
            $table->json('lines');
            $table->string('vat_code', 10)->nullable();
            $table->unsignedSmallInteger('vat_rate_bp')->default(0);
            $table->integer('net_cents')->default(0);
            $table->integer('cost_cents')->default(0);          // purchases and rent-in, for the margin
            $table->integer('vat_cents')->default(0);
            $table->integer('gross_cents')->default(0);
            $table->string('po_number', 80)->nullable();
            $table->unsignedSmallInteger('payment_days')->default(0);
            $table->date('service_date')->nullable();            // when the revenue counts
            $table->json('checks')->nullable();
            $table->foreignId('override_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('back_note', 1000)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number', 40)->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'created_at']);
        });

        // Late payment: one reminder per unpaid invoice; the accountant decides.
        Schema::create('pre_invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('decision', 12)->nullable();          // now, on_payment, skip
            $table->string('reason', 500)->nullable();
            $table->integer('interest_cents')->default(0);
            $table->integer('fixed_cents')->default(0);
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_invoice_reminders');
        Schema::dropIfExists('pre_invoices');
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('po_required'));
    }
};
