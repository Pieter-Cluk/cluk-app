<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Changes after a quote is won: new times, reopening the order, and adjustments sent to sub-hire partners. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'reopened_at')) {
                $table->dateTime('reopened_at')->nullable();
                $table->foreignId('reopened_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reopen_reason', 500)->nullable();
            }
        });
        Schema::table('subhire_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('subhire_requests', 'confirmed_snapshot')) {
                $table->json('confirmed_snapshot')->nullable(); // what the partner last confirmed: dates and quantities
                $table->dateTime('adjusted_at')->nullable();
                $table->unsignedSmallInteger('adjust_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subhire_requests', fn (Blueprint $t) => $t->dropColumn(['confirmed_snapshot', 'adjusted_at', 'adjust_count']));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by_id');
            $table->dropColumn(['reopened_at', 'reopen_reason']);
        });
    }
};
