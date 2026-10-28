<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Guests an Afrit 6 member expects: welcomed by name on the entrance screen (only if the member allows it).
        Schema::create('afrit6_guests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $t->date('date');
            $t->string('time', 5)->nullable();          // HH:MM, expected arrival
            $t->string('name', 120);
            $t->string('company', 120)->nullable();
            $t->boolean('on_screen')->default(true);   // false: the screen only says "a guest for <member company>"
            $t->string('note', 300)->nullable();        // for the member only, never on the screen
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['date', 'cancelled_at']);
        });

        // The third wall screen: the big TV at the entrance of the co-working space.
        if (! DB::table('wall_screens')->where('view', 'entrance')->exists()) {
            DB::table('wall_screens')->insert(['view' => 'entrance', 'token' => Str::random(40), 'locale' => 'nl', 'location' => 'Afrit 6 · entrance TV', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('afrit6_guests');
        DB::table('wall_screens')->where('view', 'entrance')->delete();
    }
};
