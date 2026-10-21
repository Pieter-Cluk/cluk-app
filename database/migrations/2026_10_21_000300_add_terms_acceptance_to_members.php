<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proof of what the member agreed to: house & booking rules and payment terms, with version.
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('rules_accepted_at')->nullable()->after('terms_accepted_at');
            $table->timestamp('payment_terms_accepted_at')->nullable()->after('rules_accepted_at');
            $table->string('terms_version', 20)->nullable()->after('payment_terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['rules_accepted_at', 'payment_terms_accepted_at', 'terms_version']);
        });
    }
};
