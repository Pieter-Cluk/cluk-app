<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Desks and the meeting room of Afrit 6.
        Schema::create('afrit6_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();      // D-04, A-1, ROOM
            $table->string('kind', 10);                 // desk | room
            $table->string('floor', 10)->default('up'); // up | ground
            $table->string('zone', 20)->nullable();     // window row, island A, island B
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // A desk rented by a member, per contract month. No automatic renewal:
        // ends_on is the last day; empty means "until further notice".
        Schema::create('afrit6_desk_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained('afrit6_spaces');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('monthly_price_cents');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['space_id', 'starts_on']);
        });

        // Meeting room bookings, per half hour (slot 0 = 08:00).
        Schema::create('afrit6_room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained('afrit6_spaces');
            $table->date('date');
            $table->unsignedTinyInteger('start_slot');
            $table->unsignedTinyInteger('slots');
            $table->string('setup', 12)->default('meeting'); // meeting | classroom
            $table->string('note', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['space_id', 'date']);
        });

        // Everything a member asks for (and staff tasks like room resets).
        Schema::create('afrit6_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);       // shelf, pallet, packing, receive, send, desk_add, desk_swap, room_reset
            $table->string('status', 12)->default('pending'); // pending, confirmed, waiting, done, declined
            $table->string('status_before_wait', 12)->nullable();
            $table->date('date');              // from / ready by / expected on / pick-up on
            $table->date('ends_on')->nullable(); // storage: last day
            $table->unsignedSmallInteger('qty')->default(0);     // shelves or pallet places
            $table->unsignedSmallInteger('colis')->default(0);
            $table->unsignedSmallInteger('pallets')->default(0);
            $table->string('receive_mode', 10)->nullable(); // shelf24 | store
            $table->boolean('then_send')->default(false);
            $table->string('from_text', 160)->nullable();
            $table->string('carrier', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('space_id')->nullable()->constrained('afrit6_spaces');
            $table->foreignId('from_space_id')->nullable()->constrained('afrit6_spaces');
            $table->foreignId('room_booking_id')->nullable()->constrained('afrit6_room_bookings')->nullOnDelete();
            $table->integer('amount_cents')->default(0);
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'date']);
            $table->index(['member_id', 'kind']);
        });

        Schema::create('afrit6_request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('afrit6_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        // Monthly statement per member and contract month, saved as a proforma.
        Schema::create('afrit6_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('lines');
            $table->integer('total_cents');
            $table->string('status', 12)->default('proforma');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['member_id', 'period_start']);
        });

        Schema::table('members', function (Blueprint $table) {
            // Net2 door code, stored encrypted; members see their own.
            $table->text('door_code')->nullable()->after('mobile');
        });

        // The current layout: 8 desks upstairs, islands A and B on the
        // ground floor, one meeting room (meeting or classroom setup).
        $now = now();
        $rows = [];
        foreach (range(1, 8) as $i) {
            $rows[] = ['code' => sprintf('D-%02d', $i), 'kind' => 'desk', 'floor' => 'up', 'zone' => 'window', 'sort' => $i];
        }
        foreach (range(1, 4) as $i) {
            $rows[] = ['code' => 'A-'.$i, 'kind' => 'desk', 'floor' => 'ground', 'zone' => 'island_a', 'sort' => 20 + $i];
        }
        foreach (range(1, 2) as $i) {
            $rows[] = ['code' => 'B-'.$i, 'kind' => 'desk', 'floor' => 'ground', 'zone' => 'island_b', 'sort' => 30 + $i];
        }
        $rows[] = ['code' => 'ROOM', 'kind' => 'room', 'floor' => 'ground', 'zone' => null, 'sort' => 90];
        DB::table('afrit6_spaces')->insert(array_map(fn ($r) => $r + ['active' => true, 'created_at' => $now, 'updated_at' => $now], $rows));
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('door_code');
        });
        Schema::dropIfExists('afrit6_statements');
        Schema::dropIfExists('afrit6_request_messages');
        Schema::dropIfExists('afrit6_requests');
        Schema::dropIfExists('afrit6_room_bookings');
        Schema::dropIfExists('afrit6_desk_rentals');
        Schema::dropIfExists('afrit6_spaces');
    }
};
