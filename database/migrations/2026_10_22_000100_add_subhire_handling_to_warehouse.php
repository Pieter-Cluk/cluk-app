<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sub-hire on the phone: counted and photographed when it comes in from the partner and when it goes back. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subhire_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('subhire_requests', 'received_at')) {
                $table->dateTime('received_at')->nullable();
                $table->foreignId('received_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('in_photos')->nullable();
                $table->text('in_note')->nullable();
                $table->dateTime('handed_back_at')->nullable();
                $table->foreignId('handed_back_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('out_photos')->nullable();
                $table->text('out_note')->nullable();
            }
        });
        Schema::table('subhire_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('subhire_lines', 'received_qty')) {
                $table->unsignedInteger('received_qty')->default(0);
                $table->unsignedInteger('back_qty')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subhire_lines', fn (Blueprint $t) => $t->dropColumn(['received_qty', 'back_qty']));
        Schema::table('subhire_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_id');
            $table->dropConstrainedForeignId('handed_back_by_id');
            $table->dropColumn(['received_at', 'in_photos', 'in_note', 'handed_back_at', 'out_photos', 'out_note']);
        });
    }
};
