<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Downloads: who may download (open / name and email first / customers with a login), and who downloaded what. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_downloads', function (Blueprint $t) {
            $t->string('access', 10)->default('open')->after('locale');
        });
        Schema::create('web_download_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('download_id')->constrained('web_downloads')->cascadeOnDelete();
            $t->foreignId('customer_account_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->unsignedBigInteger('visitor_id')->nullable()->index();
            $t->string('name', 120)->nullable();
            $t->string('email', 190)->nullable()->index();
            $t->string('company_name', 190)->nullable();
            $t->string('locale', 5)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_download_logs');
        Schema::table('web_downloads', fn (Blueprint $t) => $t->dropColumn('access'));
    }
};
