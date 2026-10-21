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
        // The office wall screens. Each has a secret display link (no login on the screen PC).
        Schema::create('wall_screens', function (Blueprint $table) {
            $table->id();
            $table->string('view', 20)->unique();   // planning, today
            $table->string('token', 64);
            $table->char('locale', 2)->default('nl');
            $table->string('location', 120)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });
        $now = now();
        foreach (['planning' => 'Office screen left', 'today' => 'Office screen right'] as $view => $where) {
            DB::table('wall_screens')->insert(['view' => $view, 'token' => Str::random(40), 'locale' => 'nl', 'location' => $where, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wall_screens');
    }
};
