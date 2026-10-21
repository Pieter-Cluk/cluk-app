<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products we sell. No assets: stock is a number.
        Schema::create('sales_items', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();             // S-100001, given by the system
            $table->string('brand', 80)->nullable();
            $table->string('type', 120);
            $table->string('description', 300)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('unit', 12)->default('piece');
            $table->unsignedInteger('list_price_cents')->default(0);
            $table->integer('stock_qty')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('photo_path')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['category', 'active']);
        });

        // Suppliers of an item: purchase discount on the list price and lead time.
        Schema::create('sales_item_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('discount_bp')->default(0);
            $table->unsignedSmallInteger('lead_days')->default(5);
            $table->boolean('preferred')->default(false);
            $table->timestamps();
            $table->unique(['sales_item_id', 'company_id']);
        });

        // Manuals, certificates, CE declarations, datasheets (PDF).
        Schema::create('sales_item_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_item_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                          // manual, certificate, ce, datasheet
            $table->string('name', 200);
            $table->string('path');
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // A sales quote that becomes the order confirmation (S-QUO → S-OC).
        Schema::create('sales_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();              // S-QUO-2026-0001
            $table->string('status', 12)->default('draft');      // draft, sent, accepted, confirmed, delivered, declined, cancelled
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->char('language', 2)->default('nl');
            $table->text('intro')->nullable();
            $table->text('notes')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('delivery_mode', 10)->default('together');  // together | asap
            $table->string('vat_code', 10)->nullable();
            $table->unsignedSmallInteger('vat_rate_bp')->default(0);
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('net_cents')->default(0);
            $table->unsignedInteger('vat_cents')->default(0);
            $table->unsignedInteger('gross_cents')->default(0);
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('approved_sig', 64)->nullable();
            $table->string('approval_note', 500)->nullable();
            $table->string('token', 48)->nullable()->unique();
            $table->dateTime('sent_at')->nullable();
            $table->string('sent_to', 190)->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->string('answered_name', 120)->nullable();
            $table->string('po_number', 80)->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->dateTime('confirmed_at')->nullable();       // S-OC sent
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('sales_quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 8)->default('item');          // item, charge, text
            $table->string('name', 200);
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('list_cents')->default(0);
            $table->unsignedSmallInteger('discount_bp')->default(0);
            $table->unsignedInteger('unit_cents')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);   // purchase price per unit
            $table->unsignedInteger('amount_cents')->default(0);
            $table->unsignedInteger('from_stock')->default(0);
            $table->date('delivery_date')->nullable();           // confirmed by the supplier (+1 working day)
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Price requests to suppliers (PQ): every supplier of the items gets one, we compare and choose.
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();              // PQ-2026-0001
            $table->string('status', 10)->default('draft');      // draft, sent, ordered, cancelled
            $table->foreignId('sales_quote_id')->nullable()->constrained()->nullOnDelete();
            $table->date('needed_by')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_quote_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->unsignedInteger('qty')->default(1);
            $table->string('purpose', 12)->default('sales');     // sales (stock), rental (fleet), consumables
            $table->foreignId('chosen_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_request_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('replied_at')->nullable();
            $table->timestamps();
            $table->unique(['purchase_request_id', 'company_id']);
        });

        Schema::create('purchase_request_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('net_cents')->nullable();
            $table->date('delivery_date')->nullable();
            $table->timestamps();
            $table->unique(['purchase_request_line_id', 'company_id']);
        });

        // Purchase orders (PO): one per chosen supplier.
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();              // PO-2026-0001
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained();
            $table->string('status', 10)->default('sent');       // sent, confirmed, received, cancelled
            $table->string('their_ref', 80)->nullable();
            $table->unsignedInteger('total_cents')->default(0);
            $table->unsignedInteger('invoice_cents')->nullable();
            $table->dateTime('invoice_checked_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_quote_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('unit_cents')->default(0);
            $table->unsignedInteger('amount_cents')->default(0);
            $table->date('delivery_date')->nullable();
            $table->string('purpose', 12)->default('sales');
            $table->unsignedInteger('received_qty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['purchase_order_lines', 'purchase_orders', 'purchase_request_replies', 'purchase_request_suppliers', 'purchase_request_lines', 'purchase_requests',
            'sales_quote_lines', 'sales_quotes', 'sales_item_documents', 'sales_item_suppliers', 'sales_items'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
