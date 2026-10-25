<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices made by the app: one numbered series, an e-invoice (Peppol UBL) per
 * invoice, emailed to CLUK's OkiOki address; OkiOki books it and sends it via Peppol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_details', function (Blueprint $t) {
            $t->boolean('okioki_send')->default(false);
            $t->string('okioki_inbox', 190)->nullable();
            $t->string('invoice_prefix', 20)->nullable();     // {year} becomes the year, e.g. "{year}-" → 2026-0001
            $t->unsignedInteger('invoice_next')->default(1);
            $t->unsignedSmallInteger('invoice_year')->nullable();
        });
        Schema::table('pre_invoices', function (Blueprint $t) {
            $t->string('ubl_path', 255)->nullable();
            $t->timestamp('okioki_sent_at')->nullable();
            $t->string('okioki_error', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_details', function (Blueprint $t) {
            $t->dropColumn(['okioki_send', 'okioki_inbox', 'invoice_prefix', 'invoice_next', 'invoice_year']);
        });
        Schema::table('pre_invoices', function (Blueprint $t) {
            $t->dropColumn(['ubl_path', 'okioki_sent_at', 'okioki_error']);
        });
    }
};
