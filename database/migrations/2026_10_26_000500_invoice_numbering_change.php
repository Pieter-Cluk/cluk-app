<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice numbers: a planned change of the series (e.g. INV-1129… until the end of the year,
 * then 2027/0001 from 1 January). Set to what Pieter asked: OkiOki's last invoice is INV-1128.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_details', function (Blueprint $t) {
            $t->date('invoice_change_on')->nullable()->after('invoice_year');
            $t->string('invoice_change_prefix', 20)->nullable()->after('invoice_change_on');
            $t->unsignedInteger('invoice_change_next')->default(1)->after('invoice_change_prefix');
        });
        // Only while the app has not numbered an invoice itself yet.
        if (! DB::table('pre_invoices')->whereNotNull('invoice_number')->exists()) {
            DB::table('company_details')->update([
                'invoice_prefix' => 'INV-', 'invoice_next' => 1129, 'invoice_year' => (int) now()->year,
                'invoice_change_on' => '2027-01-01', 'invoice_change_prefix' => '{year}/', 'invoice_change_next' => 1,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('company_details', fn (Blueprint $t) => $t->dropColumn(['invoice_change_on', 'invoice_change_prefix', 'invoice_change_next']));
    }
};
