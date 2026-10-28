<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The exact shelf or pallet places of a member's storage (one storage request can use several places).
        Schema::create('afrit6_storage_places', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('afrit6_requests')->cascadeOnDelete();
            $t->foreignId('position_id')->constrained('warehouse_positions')->cascadeOnDelete();
            $t->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['request_id', 'position_id']);
        });

        // Places already scanned on storage tasks (phone) become the member's places.
        $now = now();
        foreach (DB::table('afrit6_requests')->whereIn('kind', ['shelf', 'pallet'])->whereNotNull('scans')->get(['id', 'scans']) as $r) {
            foreach ((array) json_decode((string) $r->scans, true) as $scan) {
                $pos = DB::table('warehouse_positions')->where('code', strtoupper((string) ($scan['code'] ?? '')))->where('use', 'afrit6')->value('id');
                if ($pos && ! DB::table('afrit6_storage_places')->where('request_id', $r->id)->where('position_id', $pos)->exists()) {
                    DB::table('afrit6_storage_places')->insert(['request_id' => $r->id, 'position_id' => $pos, 'assigned_by_id' => $scan['by'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('afrit6_storage_places');
    }
};
