<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Late payments: the days counted and the interest proforma made from the accountant's decision. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_invoice_reminders', function (Blueprint $t) {
            $t->unsignedInteger('days')->default(0);
            $t->foreignId('pre_invoice_made_id')->nullable()->constrained('pre_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pre_invoice_reminders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pre_invoice_made_id');
            $t->dropColumn('days');
        });
    }
};
