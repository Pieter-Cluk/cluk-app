<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CLUK has no test benches but three test areas: Light street, Audio street and Video street.
 * Test bench 1 becomes Light street, Test bench 2 Audio street, Video street is added; each
 * pre-check leads to its own street. Pieces standing on a bench move with it.
 */
return new class extends Migration
{
    private const STREETS = [
        'LIGHT-ST' => ['from' => 'BENCH-1', 'categories' => ['Light'], 'pre' => 'PRE-LIGHT'],
        'AUDIO-ST' => ['from' => 'BENCH-2', 'categories' => ['Audio', 'Sound'], 'pre' => 'PRE-SOUND'],
        'VIDEO-ST' => ['from' => null, 'categories' => ['Video'], 'pre' => 'PRE-VIDEO'],
    ];

    public function up(): void
    {
        if (DB::table('warehouse_zones')->where('code', 'VIDEO-ST')->exists()) {
            return; // done before
        }
        $sort = (int) (DB::table('warehouse_zones')->where('code', 'BENCH-1')->value('sort') ?? DB::table('warehouse_zones')->where('code', 'POST-CHECK')->value('sort') ?? 50);
        // Make room for one more zone after the streets.
        DB::table('warehouse_zones')->whereNotIn('code', ['BENCH-1', 'BENCH-2'])->where('sort', '>', $sort + 1)->increment('sort');
        DB::table('warehouse_zones')->where('code', 'POST-CHECK')->where('sort', '<=', $sort + 2)->update(['sort' => $sort + 3]);
        $i = 0;
        foreach (self::STREETS as $code => $s) {
            $values = ['type' => 'bench', 'categories' => json_encode($s['categories']), 'next' => json_encode(['POST-CHECK', 'REPAIR']), 'active' => true, 'sort' => $sort + $i++, 'updated_at' => now()];
            if ($s['from'] && DB::table('warehouse_zones')->where('code', $s['from'])->exists()) {
                DB::table('warehouse_zones')->where('code', $s['from'])->update($values + ['code' => $code, 'name' => null]);
                DB::table('equipment_assets')->where('location', $s['from'])->update(['location' => $code]);
            } elseif (! DB::table('warehouse_zones')->where('code', $code)->exists()) {
                DB::table('warehouse_zones')->insert($values + ['code' => $code, 'created_at' => now()]);
            }
            // Each pre-check leads to its own street.
            DB::table('warehouse_zones')->where('code', $s['pre'])->update(['next' => json_encode([$code]), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('warehouse_zones')->where('code', 'LIGHT-ST')->update(['code' => 'BENCH-1', 'categories' => null]);
        DB::table('warehouse_zones')->where('code', 'AUDIO-ST')->update(['code' => 'BENCH-2', 'categories' => null]);
        DB::table('equipment_assets')->where('location', 'LIGHT-ST')->update(['location' => 'BENCH-1']);
        DB::table('equipment_assets')->whereIn('location', ['AUDIO-ST', 'VIDEO-ST'])->update(['location' => 'BENCH-2']);
        DB::table('warehouse_zones')->where('code', 'VIDEO-ST')->delete();
        DB::table('warehouse_zones')->whereIn('code', ['PRE-LIGHT', 'PRE-SOUND', 'PRE-VIDEO'])->update(['next' => json_encode(['BENCH-1', 'BENCH-2'])]);
    }
};
