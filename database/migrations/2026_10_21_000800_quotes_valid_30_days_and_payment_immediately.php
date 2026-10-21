<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agreed 21 Oct 2026 with the reworked terms and conditions: quotes are
 * valid for 30 days, invoices are payable immediately unless CLUK agrees
 * other terms with a customer (set per company).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_details')->where('quote_valid_days', 14)->update(['quote_valid_days' => 30]);
        DB::table('company_details')->where('payment_term_days', 30)->update(['payment_term_days' => 0]);
    }

    public function down(): void
    {
        DB::table('company_details')->where('quote_valid_days', 30)->update(['quote_valid_days' => 14]);
        DB::table('company_details')->where('payment_term_days', 0)->update(['payment_term_days' => 30]);
    }
};
