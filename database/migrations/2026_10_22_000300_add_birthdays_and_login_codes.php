<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sales products import fields, contact birthdays (with a card for ticked contacts), and email sign-in codes for staff without Microsoft 365. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'birth_date')) {
                $table->date('birth_date')->nullable();
                $table->boolean('birthday_card')->default(false);
                $table->unsignedSmallInteger('birthday_sent_year')->nullable();
            }
        });
        if (! Schema::hasTable('staff_login_codes')) {
            Schema::create('staff_login_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('code_hash', 100);
                $table->unsignedTinyInteger('tries')->default(0);
                $table->dateTime('expires_at');
                $table->dateTime('used_at')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }
        // Sales products import: barcode, and the supplier's own article number.
        Schema::table('sales_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_items', 'ean')) {
                $table->string('ean', 32)->nullable();
            }
        });
        Schema::table('sales_item_suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_item_suppliers', 'supplier_ref')) {
                $table->string('supplier_ref', 60)->nullable();
            }
        });
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_login')) {
                $table->boolean('email_login')->default(false); // may sign in with a code by email (no Microsoft 365)
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('email_login'));
        Schema::table('sales_item_suppliers', fn (Blueprint $t) => $t->dropColumn('supplier_ref'));
        Schema::table('sales_items', fn (Blueprint $t) => $t->dropColumn('ean'));
        Schema::dropIfExists('staff_login_codes');
        Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn(['birth_date', 'birthday_card', 'birthday_sent_year']));
    }
};
