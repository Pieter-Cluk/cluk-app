<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A rental order (R-OC): made when the customer accepts a quote.
        // Asset management covers every line (own stock or sub-hire), then approves.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();           // R-OC-2026-0001
            $table->string('status', 20)->default('allocation'); // allocation, approved, cancelled
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->char('language', 2)->default('nl');
            $table->dateTime('out_from');
            $table->dateTime('out_until');
            $table->date('use_from');
            $table->date('use_until');
            $table->string('location', 200)->nullable();
            $table->string('po_number', 80)->nullable();
            $table->string('confirmed_name', 120)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->boolean('confirmed_online')->default(false);
            $table->string('customer_email', 190)->nullable();
            $table->foreignId('handed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->unsignedSmallInteger('project_discount_bp')->default(0);
            $table->string('vat_code', 4)->nullable();
            $table->unsignedSmallInteger('vat_rate_bp')->default(0);
            $table->integer('subtotal_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('net_cents')->default(0);
            $table->integer('vat_cents')->default(0);
            $table->integer('gross_cents')->default(0);
            $table->timestamps();
            $table->index(['status', 'out_from', 'out_until']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('kind', 12)->default('item');      // item, charge, text
            $table->foreignId('item_id')->nullable()->constrained('equipment_items')->nullOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('name', 200);
            $table->decimal('qty', 10, 2)->default(1);
            $table->integer('unit_price_cents')->default(0);
            $table->decimal('factor', 6, 2)->default(1);
            $table->unsignedSmallInteger('discount_bp')->default(0);
            $table->integer('amount_cents')->default(0);
            $table->unsignedInteger('own_qty')->default(0);   // counted items and packages: pieces reserved from stock
            $table->timestamps();
        });

        // Serial numbers booked for an order line.
        Schema::create('order_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('equipment_assets')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['order_line_id', 'asset_id']);
        });

        // Sub-hire: one price request (SHQ) per partner per order; accepted it becomes the confirmation (SHC).
        Schema::create('subhire_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();           // SHQ-2026-0001
            $table->string('confirm_number', 24)->nullable()->unique(); // SHC-2026-0001
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('companies');
            $table->string('status', 20)->default('draft');   // draft, sent, accepted, not_available, cancelled
            $table->dateTime('delivery_at')->nullable();
            $table->dateTime('return_at')->nullable();
            $table->string('sent_to', 190)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('their_ref', 80)->nullable();
            $table->text('note')->nullable();
            $table->integer('extra_cents')->default(0);       // transport, insurance, crew…
            $table->string('extra_text', 200)->nullable();
            $table->integer('total_cents')->default(0);
            $table->dateTime('answered_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subhire_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('subhire_requests')->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('equipment_items')->nullOnDelete();
            $table->string('name', 200);
            $table->unsignedInteger('qty');
            $table->integer('last_price_cents')->nullable();  // what this partner charged last time, per piece per day
            $table->integer('price_cents')->nullable();       // their price now, per piece per day
            $table->decimal('factor', 6, 2)->default(1);
            $table->unsignedSmallInteger('discount_bp')->default(0);
            $table->integer('amount_cents')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subhire_lines');
        Schema::dropIfExists('subhire_requests');
        Schema::dropIfExists('order_assets');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
