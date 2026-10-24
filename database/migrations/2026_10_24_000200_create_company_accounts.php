<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Annual accounts a company filed at the National Bank (NBB/BNB), one row per financial year. Money in cents. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->date('period_end');
            $t->string('reference', 40)->nullable();
            $t->string('model', 20)->nullable();
            $t->date('deposited_on')->nullable();
            $t->string('currency', 3)->default('EUR');
            $t->bigInteger('turnover')->nullable();
            $t->bigInteger('gross_margin')->nullable();
            $t->bigInteger('operating_result')->nullable();
            $t->bigInteger('profit')->nullable();
            $t->bigInteger('equity')->nullable();
            $t->bigInteger('total_assets')->nullable();
            $t->decimal('employees', 8, 1)->nullable();
            $t->string('period', 4)->default('N'); // N = from that year's own filing, NM1 = the comparison column of the next year
            $t->timestamps();
            $t->unique(['company_id', 'year']);
        });
        Schema::table('companies', function (Blueprint $t) {
            $t->timestamp('accounts_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_accounts');
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('accounts_checked_at');
        });
    }
};
