<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A free desk booked for a morning, an afternoon or a whole day.
        Schema::create('afrit6_desk_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained('afrit6_spaces');
            $table->date('date');
            $table->string('part', 3); // am | pm | day
            $table->unsignedInteger('price_cents');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['space_id', 'date']);
        });

        // The private office upstairs: rented long term, price on request.
        if (! DB::table('afrit6_spaces')->where('code', 'OFFICE')->exists()) {
            DB::table('afrit6_spaces')->insert([
                'code' => 'OFFICE', 'kind' => 'office', 'floor' => 'up', 'zone' => 'office', 'sort' => 80,
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('afrit6_desk_bookings');
        DB::table('afrit6_spaces')->where('code', 'OFFICE')->delete();
    }
};
