<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zones of the warehouse and the route equipment must follow. "next" lists the
        // zones an item may be scanned into from here; RACKS means any storage position.
        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();          // on the QR label, e.g. CLEAN
            $table->string('name', 80)->nullable();        // empty: the standard name in the reader's language
            $table->string('type', 20);                    // inbound, cleaning, precheck, bench, postcheck, repair, storage, dispatch
            $table->json('categories')->nullable();        // only for these item categories; empty = all
            $table->json('next')->nullable();
            $table->string('note', 200)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Pallet and shelf places: floor (G / M) · row · rack · layer · spot, e.g. GA1A1.
        Schema::create('warehouse_positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->char('floor', 1);
            $table->char('row', 1);
            $table->unsignedTinyInteger('rack');
            $table->char('layer', 1);
            $table->unsignedTinyInteger('spot');
            $table->string('kind', 8)->default('shelf');   // pallet | shelf
            $table->string('use', 8)->default('rental');   // rental | afrit6 | sales | spare
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['floor', 'row', 'rack']);
        });

        Schema::table('equipment_assets', function (Blueprint $table) {
            $table->foreignId('out_order_id')->nullable()->after('location')->constrained('orders')->nullOnDelete();
            $table->timestamp('repair_released_at')->nullable()->after('out_order_id');
        });
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->string('position_code', 12)->nullable()->after('stock_qty'); // where counted items are kept
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('loaded_at')->nullable()->after('approved_by_id');
            $table->foreignId('loaded_by_id')->nullable()->after('loaded_at')->constrained('users')->nullOnDelete();
            $table->dateTime('returned_at')->nullable()->after('loaded_by_id');
        });
        Schema::table('order_lines', function (Blueprint $table) {
            $table->unsignedInteger('picked_qty')->default(0);
            $table->unsignedInteger('returned_qty')->default(0);
        });

        // Every scan that moves something: the movement log.
        Schema::create('warehouse_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('equipment_assets')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('equipment_items')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->string('from_code', 30)->nullable();
            $table->string('to_code', 30)->nullable();
            $table->string('kind', 12)->default('move');   // move, pick, unpick, load, return, release, lost
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'id']);
        });

        // Cleaning and the check on the test bench of one piece after a job.
        Schema::create('warehouse_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('equipment_assets')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('dirt')->nullable();
            $table->dateTime('cleaned_at')->nullable();
            $table->foreignId('cleaned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tests')->nullable();
            $table->json('missing')->nullable();           // [{item_id, qty}]
            $table->json('damages')->nullable();           // [{type, note}]
            $table->json('photos')->nullable();            // [{path, stage}]
            $table->boolean('reset')->default(false);
            $table->string('result', 8)->nullable();       // ok | repair
            $table->dateTime('finished_at')->nullable();
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Costs for the customer found at the check: nothing goes out before the office approves.
        Schema::create('warehouse_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('equipment_assets')->nullOnDelete();
            $table->foreignId('check_id')->nullable()->constrained('warehouse_checks')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('equipment_items')->nullOnDelete();
            $table->string('kind', 10);                    // cleaning, missing, damage
            $table->string('label', 200);
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('amount_cents')->nullable();   // null: the office sets the price
            $table->unsignedInteger('original_cents')->nullable();
            $table->string('status', 10)->default('pending');      // pending, approved, waived
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'order_id']);
        });

        $now = now();
        $zones = [
            // code, type, categories, next
            ['INBOUND', 'inbound', null, ['CLEAN']],
            ['UNKNOWN', 'inbound', null, []],
            ['CLEAN', 'cleaning', null, ['PRE-LIGHT', 'PRE-SOUND', 'PRE-VIDEO']],
            ['PRE-LIGHT', 'precheck', ['Light'], ['BENCH-1', 'BENCH-2']],
            ['PRE-SOUND', 'precheck', ['Audio', 'Sound'], ['BENCH-1', 'BENCH-2']],
            ['PRE-VIDEO', 'precheck', ['Video'], ['BENCH-1', 'BENCH-2']],
            ['BENCH-1', 'bench', null, ['POST-CHECK', 'REPAIR']],
            ['BENCH-2', 'bench', null, ['POST-CHECK', 'REPAIR']],
            ['POST-CHECK', 'postcheck', null, ['RACKS']],
            ['REPAIR', 'repair', null, ['PRE-LIGHT', 'PRE-SOUND', 'PRE-VIDEO']],
            ['RACKS', 'storage', null, ['DISPATCH']],
            ['DISPATCH', 'dispatch', null, ['RACKS']],
        ];
        foreach ($zones as $i => [$code, $type, $cats, $next]) {
            DB::table('warehouse_zones')->insert(['code' => $code, 'type' => $type, 'categories' => $cats ? json_encode($cats) : null,
                'next' => json_encode($next), 'sort' => ($i + 1) * 10, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Ground floor: rows A–F, racks 1–6; layer A on the floor = 2 pallet places, B–D = 3 shelf places.
        // Mezzanine: rows A–L, 2 floor places per rack (3 + 2 for rows A–H, 2 + 2 for I–L), layer A only.
        $rows = [];
        foreach (str_split('ABCDEF') as $row) {
            for ($rack = 1; $rack <= 6; $rack++) {
                foreach (['A', 'B', 'C', 'D'] as $layer) {
                    $pallet = $layer === 'A';
                    for ($spot = 1; $spot <= ($pallet ? 2 : 3); $spot++) {
                        $rows[] = ['G', $row, $rack, $layer, $spot, $pallet ? 'pallet' : 'shelf'];
                    }
                }
            }
        }
        foreach (str_split('ABCDEFGHIJKL') as $row) {
            foreach ([1 => in_array($row, ['I', 'J', 'K', 'L'], true) ? 2 : 3, 2 => 2] as $rack => $spots) {
                for ($spot = 1; $spot <= $spots; $spot++) {
                    $rows[] = ['M', $row, $rack, 'A', $spot, 'pallet'];
                }
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('warehouse_positions')->insert(array_map(fn ($r) => [
                'code' => $r[0].$r[1].$r[2].$r[3].$r[4], 'floor' => $r[0], 'row' => $r[1], 'rack' => $r[2], 'layer' => $r[3], 'spot' => $r[4],
                'kind' => $r[5], 'use' => 'rental', 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ], $chunk));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_charges');
        Schema::dropIfExists('warehouse_checks');
        Schema::dropIfExists('warehouse_moves');
        Schema::table('order_lines', fn (Blueprint $t) => $t->dropColumn(['picked_qty', 'returned_qty']));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('loaded_by_id');
            $t->dropColumn(['loaded_at', 'returned_at']);
        });
        Schema::table('equipment_items', fn (Blueprint $t) => $t->dropColumn('position_code'));
        Schema::table('equipment_assets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('out_order_id');
            $t->dropColumn('repair_released_at');
        });
        Schema::dropIfExists('warehouse_positions');
        Schema::dropIfExists('warehouse_zones');
    }
};
