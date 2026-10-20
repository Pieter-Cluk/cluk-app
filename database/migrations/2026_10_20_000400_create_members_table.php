<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Afrit 6 (co-working) members: their own login with a password,
        // separate from staff. Passwords are hashed; nobody at CLUK can read them.
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 190)->unique();
            $table->string('mobile', 40)->nullable();
            $table->string('password');
            $table->string('locale', 5)->default('nl');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 12)->default('pending'); // pending, active, declined, blocked
            // What the member entered when registering, until CLUK links a company.
            $table->char('reg_country', 2)->default('BE');
            $table->string('reg_vat_number', 20)->nullable();
            $table->string('reg_company_name', 160)->nullable();
            $table->string('reg_address', 255)->nullable();
            $table->string('vies_status', 12)->nullable();
            $table->boolean('newsletter')->default(false);
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('member_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_password_reset_tokens');
        Schema::dropIfExists('members');
    }
};
