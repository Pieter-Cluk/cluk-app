<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The @cluk.live address stays the sign-in name, but cluk.live mail is delivered
 * to Microsoft 365, so people without a Microsoft mailbox get their sign-in code
 * at another address (their own).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'code_email')) {
                $table->string('code_email', 190)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('code_email'));
    }
};
