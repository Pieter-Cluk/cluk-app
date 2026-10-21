<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The timing of the day and the link to the equipment (a rental quote that becomes an order).
        Schema::table('factory_bookings', function (Blueprint $table) {
            $table->string('load_in_time', 5)->default('08:00')->after('breakdown_days');
            $table->string('doors_time', 5)->default('18:00')->after('load_in_time');
            $table->string('end_time', 5)->default('01:00')->after('doors_time');
            $table->string('load_out_time', 5)->nullable()->after('end_time');
            $table->foreignId('gear_quote_id')->nullable()->after('quote_id')->constrained('quotes')->nullOnDelete();
        });

        // Crew tasks for a booking: the standard ones are made when it is confirmed, staff can add more.
        Schema::create('factory_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('factory_bookings')->cascadeOnDelete();
            $table->string('kind', 20)->nullable();         // blackbox, rigging, gear, hospitality, cleaning, reset; null = added by hand
            $table->string('title', 200)->nullable();       // only for tasks added by hand
            $table->dateTime('due_at');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['done_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_tasks');
        Schema::table('factory_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gear_quote_id');
            $table->dropColumn(['load_in_time', 'doors_time', 'end_time', 'load_out_time']);
        });
    }
};
