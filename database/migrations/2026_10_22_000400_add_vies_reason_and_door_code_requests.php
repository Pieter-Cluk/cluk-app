<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Why VIES said what it said, and the VIES name for members; door-code requests from members. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'vies_reason')) {
                $table->string('vies_reason', 24)->nullable();
            }
        });
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'vies_reason')) {
                $table->string('vies_reason', 24)->nullable();
                $table->string('vies_name', 200)->nullable();
                $table->dateTime('vies_checked_at')->nullable();
            }
            if (! Schema::hasColumn('members', 'door_requested_at')) {
                $table->dateTime('door_requested_at')->nullable();
                $table->string('door_request_note', 300)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['vies_reason', 'vies_name', 'vies_checked_at', 'door_requested_at', 'door_request_note']));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('vies_reason'));
    }
};
