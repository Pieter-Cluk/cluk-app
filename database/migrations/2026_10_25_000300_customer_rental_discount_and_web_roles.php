<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer conditions and website logins:
 * - a general dry-hire discount per customer;
 * - the discount an account owner may give on his own (above it: the approval right);
 * - a role per website login: main contact (manages colleagues), may order, or look only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->unsignedSmallInteger('rental_discount_bp')->default(0);
        });
        Schema::table('company_details', function (Blueprint $t) {
            $t->unsignedSmallInteger('discount_limit_bp')->default(1000);
        });
        Schema::table('customer_accounts', function (Blueprint $t) {
            $t->string('role', 8)->default('order'); // main, order, view
            $t->foreignId('invited_by_id')->nullable()->constrained('customer_accounts')->nullOnDelete();
        });
        // The first active login of each company becomes its main contact.
        $firsts = DB::table('customer_accounts')->where('status', 'active')->whereNotNull('company_id')
            ->selectRaw('company_id, min(id) as id')->groupBy('company_id')->pluck('id');
        DB::table('customer_accounts')->whereIn('id', $firsts)->update(['role' => 'main']);
    }

    public function down(): void
    {
        Schema::table('customer_accounts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('invited_by_id');
            $t->dropColumn('role');
        });
        Schema::table('company_details', fn (Blueprint $t) => $t->dropColumn('discount_limit_bp'));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('rental_discount_bp'));
    }
};
