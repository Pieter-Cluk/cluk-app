<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Each test street takes the same categories as the pre-check before it (e.g. Light and Lighting). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['LIGHT-ST' => 'PRE-LIGHT', 'AUDIO-ST' => 'PRE-SOUND', 'VIDEO-ST' => 'PRE-VIDEO'] as $street => $pre) {
            $cats = DB::table('warehouse_zones')->where('code', $pre)->value('categories');
            if ($cats) {
                DB::table('warehouse_zones')->where('code', $street)->update(['categories' => $cats, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
