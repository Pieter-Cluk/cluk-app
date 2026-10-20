<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customers and suppliers (a company can be both).
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('legal_form', 20)->nullable();
            $table->boolean('is_customer')->default(true);
            $table->boolean('is_supplier')->default(false);
            $table->string('vat_number', 20)->nullable()->index();
            $table->char('country', 2)->default('BE');
            $table->string('street', 160)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 190)->nullable();
            $table->string('language', 5)->default('nl'); // language of the documents we send
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->nullOnDelete();
            // 'auto' = chosen from country + VIES; otherwise an OkiOki VAT code.
            $table->string('vat_regime', 10)->default('auto');
            $table->string('vies_status', 12)->nullable(); // valid, invalid, unavailable
            $table->timestamp('vies_checked_at')->nullable();
            $table->string('vies_name', 190)->nullable();
            $table->string('vies_address', 255)->nullable();
            $table->unsignedSmallInteger('customer_payment_days')->nullable();
            $table->unsignedSmallInteger('supplier_payment_days')->nullable();
            $table->json('supplies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('company_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('street', 160);
            $table->string('postcode', 20)->nullable();
            $table->string('city', 80);
            $table->char('country', 2)->default('BE');
            $table->timestamps();
        });

        // People. One person can work for (or with) more than one company.
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('email', 190)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('language', 5)->nullable();
            $table->boolean('newsletter')->default(false);
            $table->timestamp('newsletter_changed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('company_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('job_title', 120)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_billing')->default(false);
            $table->unique(['company_id', 'contact_id']);
        });

        // New right: change the VAT treatment of a company (Admin and Finance).
        foreach (DB::table('roles')->whereIn('slug', ['admin', 'finance'])->pluck('id') as $roleId) {
            DB::table('role_rights')->insertOrIgnore(['role_id' => $roleId, 'right' => 'finance.vat_treatment']);
        }
    }

    public function down(): void
    {
        DB::table('role_rights')->where('right', 'finance.vat_treatment')->delete();
        Schema::dropIfExists('company_contact');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('company_addresses');
        Schema::dropIfExists('companies');
    }
};
