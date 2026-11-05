<?php

namespace App\Services\Warehouse;

use App\Models\AuditLog;
use App\Models\Equipment\CaseItem;
use App\Models\Equipment\Item;
use App\Models\Equipment\ItemPlace;
use App\Models\User;
use App\Models\Warehouse\Move;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Counted items on rack places: "this many lie here". Setting a count replaces the count of that place; 0 takes the
 * place away. Places plus dollies/boxes can never hold more than the stock. The place with the most is the "kept at"
 * place shown on the pick list.
 */
class CountedPlaces
{
    public static function set(Item $item, string $code, int $qty, ?User $by = null, string $field = 'qty'): ItemPlace|null
    {
        $qty = max(0, $qty);
        $others = (int) $item->places()->where('position_code', '!=', $code)->sum('qty') + (int) CaseItem::where('item_id', $item->id)->sum('qty');
        if ($others + $qty > (int) $item->stock_qty) {
            throw ValidationException::withMessages([$field => __('warehouse.errors.counted_too_many', ['n' => max(0, (int) $item->stock_qty - $others), 'stock' => (int) $item->stock_qty, 'place' => $code])]);
        }

        return DB::transaction(function () use ($item, $code, $qty, $by) {
            $row = ItemPlace::where('item_id', $item->id)->where('position_code', $code)->first();
            $before = (int) $row?->qty;
            if ($qty === 0) {
                $row?->delete();
                $row = null;
            } else {
                $row = ItemPlace::updateOrCreate(['item_id' => $item->id, 'position_code' => $code], ['qty' => $qty]);
            }
            // "Kept at" (pick list) is where they lie in the warehouse: not the venue, where they are in use.
            $main = $item->places()->whereNotIn('position_code', \App\Models\Equipment\Asset::venueCodes())->first()?->position_code;
            $item->forceFill(['position_code' => $main])->save();
            Move::create(['item_id' => $item->id, 'qty' => $qty, 'to_code' => $code, 'kind' => 'move', 'user_id' => $by?->id, 'note' => "count {$before} → {$qty}"]);
            AuditLog::record('equipment.place_count', $item, ['place' => $code, 'from' => $before, 'to' => $qty], $by);

            return $row;
        });
    }
}
