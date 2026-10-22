<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Proposed product photos found on the web, per product (group of items), to approve before they are used. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('equipment_photo_suggestions');
        Schema::create('equipment_photo_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 255)->unique();
            $table->string('title', 255);
            $table->json('item_numbers');
            $table->json('candidates');           // web addresses, best first
            $table->json('files')->nullable();    // downloaded copies, same order (null = could not be fetched)
            $table->string('page', 500)->nullable();
            $table->string('source', 20)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 12)->default('new'); // new (to fetch), pending (to review), approved, rejected, failed
            $table->unsignedTinyInteger('chosen')->nullable();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_photo_suggestions');
    }
};
