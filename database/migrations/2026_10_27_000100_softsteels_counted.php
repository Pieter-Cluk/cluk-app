<?php

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Warehouse\Position;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pieter, 27/10/2026: the three softsteel types were made with a QR per piece by mistake. They become counted
 * items (one QR for the type) with 20 in stock each, kept on the rack place where they lie. The order lines that used
 * them stay; the pieces, their QR codes, their bookings on order R-OC-2026-0002 and their scan history are removed.
 */
return new class extends Migration
{
    private const ITEMS = ['RIGHARSOFSTEEL2T1M' => 'GA2C1', 'RIGHARSOFSTEEL2T2M' => 'GA2C2', 'RIGHARSOFSTEEL2T3M' => 'GA2C3'];

    public function up(): void
    {
        foreach (self::ITEMS as $number => $place) {
            $item = Item::where('number', $number)->first();
            if (! $item || ! $item->serial) {
                continue;
            }
            $ids = DB::table('equipment_assets')->where('item_id', $item->id)->pluck('id');
            $codes = DB::table('equipment_assets')->whereIn('id', $ids)->orderBy('code')->pluck('code');
            DB::transaction(function () use ($item, $ids, $place) {
                DB::table('order_assets')->whereIn('asset_id', $ids)->delete();
                DB::table('warehouse_moves')->where(fn ($q) => $q->whereIn('asset_id', $ids)->orWhere('item_id', $item->id))->delete();
                DB::table('warehouse_checks')->whereIn('asset_id', $ids)->delete();
                DB::table('warehouse_charges')->whereIn('asset_id', $ids)->update(['asset_id' => null]);
                DB::table('equipment_assets')->whereIn('case_id', $ids)->update(['case_id' => null]);
                DB::table('equipment_assets')->whereIn('id', $ids)->delete();
                $item->forceFill([
                    'serial' => false, 'stock_qty' => 20,
                    'position_code' => Position::where('code', $place)->exists() ? $place : $item->position_code,
                ])->save();
            });
            AuditLog::record('equipment.converted_counted', $item, [
                'pieces' => 20, 'labels_removed' => $ids->count(), 'codes' => mb_strimwidth($codes->implode(', '), 0, 500, '…'), 'note' => 'Data fix asked by Pieter',
            ]);
        }
    }

    public function down(): void
    {
        // The removed pieces are not brought back.
    }
};
