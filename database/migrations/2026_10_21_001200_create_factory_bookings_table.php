<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A Cluk Factory booking: from a request on the booking page (or made by staff)
        // to option, confirmed and done. The quote and deal follow the normal sales flow.
        Schema::create('factory_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();            // F-2026-0001
            $table->string('status', 20)->default('request');  // request, option, confirmed, done, declined, cancelled
            $table->string('source', 10)->default('web');      // web, staff
            $table->string('title', 200);
            $table->string('event_type', 20);
            $table->date('start_date');
            $table->unsignedTinyInteger('days')->default(1);
            $table->unsignedTinyInteger('buildup_days')->default(0);
            $table->unsignedTinyInteger('breakdown_days')->default(0);
            $table->string('size', 4);
            $table->string('level', 10)->default('basic');
            $table->unsignedSmallInteger('guests')->default(0);
            $table->json('extras')->nullable();
            $table->text('note')->nullable();
            $table->char('language', 2)->default('en');
            $table->string('req_company', 160)->nullable();
            $table->string('req_vat', 30)->nullable();
            $table->string('req_name', 160)->nullable();
            $table->string('req_email', 190)->nullable();
            $table->string('req_phone', 40)->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('option_until')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->json('checks')->nullable();                 // contract, deposit, catering, timing, security
            $table->timestamps();
            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_bookings');
    }
};
