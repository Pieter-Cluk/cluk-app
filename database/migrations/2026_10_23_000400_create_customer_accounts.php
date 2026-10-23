<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website logins for customers. One login = two baskets (dry-hire request,
 * sales order). A registration waits until staff approve it on the customer
 * card and link it to a contact (and company). Passwords are hashed; staff
 * never see them. Customers sign in on the website (guard "customer").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->unique();
            $t->string('password');
            $t->string('first_name', 80);
            $t->string('last_name', 80)->nullable();
            $t->string('company_name', 160)->nullable();
            $t->string('vat_number', 30)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('locale', 5)->default('en');
            $t->string('status', 12)->default('pending')->index(); // pending, active, blocked
            $t->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('blocked_reason', 255)->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('customer_password_resets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        // One basket per kind per login: dryhire (a request to the team) and sales (an order on invoice).
        Schema::create('customer_baskets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('customer_accounts')->cascadeOnDelete();
            $t->string('kind', 10); // dryhire, sales
            $t->date('first_day')->nullable();
            $t->date('last_day')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'kind']);
        });
        Schema::create('customer_basket_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('basket_id')->constrained('customer_baskets')->cascadeOnDelete();
            $t->foreignId('item_id')->nullable()->constrained('equipment_items')->cascadeOnDelete();
            $t->foreignId('sales_item_id')->nullable()->constrained('sales_items')->cascadeOnDelete();
            $t->unsignedInteger('qty');
            $t->timestamps();
            $t->unique(['basket_id', 'item_id']);
            $t->unique(['basket_id', 'sales_item_id']);
        });
        Schema::table('sales_quotes', function (Blueprint $t) {
            $t->foreignId('customer_account_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
        });
        Schema::table('incoming_messages', function (Blueprint $t) {
            $t->foreignId('customer_account_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incoming_messages', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_account_id'));
        Schema::table('sales_quotes', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_account_id'));
        Schema::dropIfExists('customer_basket_lines');
        Schema::dropIfExists('customer_baskets');
        Schema::dropIfExists('customer_password_resets');
        Schema::dropIfExists('customer_accounts');
    }
};
