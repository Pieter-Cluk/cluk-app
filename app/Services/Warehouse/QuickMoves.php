<?php

namespace App\Services\Warehouse;

use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\User;
use App\Models\Warehouse\Check;
use App\Models\Warehouse\Move;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quick changes for pieces that never come through the warehouse, from the item card in bulk:
 * equipment that comes back and goes straight out again, or a rental that is prolonged
 * on a second order. Every change is a move in the piece's history and in the audit log.
 */
class QuickMoves
{
    /**
     * Back in the warehouse and rentable without the return scan and the check:
     * out (closes it on its order), or waiting for a check. Back on its last place in the rack.
     */
    public static function setAvailable(Asset $asset, User $by): void
    {
        if ($asset->status === Asset::REPAIR) {
            throw ValidationException::withMessages(['quick' => __('equipment.quick.errors.repair', ['code' => $asset->code])]);
        }
        if ($asset->status === Asset::RETIRED || ($asset->status === Asset::AVAILABLE && $asset->location !== Moves::OUT)) {
            return;
        }
        if ($asset->location === 'DISPATCH' && $asset->status !== Asset::OUT) {
            throw ValidationException::withMessages(['quick' => __('equipment.quick.errors.picked', ['code' => $asset->code])]);
        }
        DB::transaction(function () use ($asset, $by) {
            $order = $asset->status === Asset::OUT ? $asset->out_order_id : null;
            $from = $asset->location;
            $place = $from === Moves::OUT || $from === null ? self::lastPlace($asset) : $from;
            $asset->update(['status' => Asset::AVAILABLE, 'location' => $place, 'out_order_id' => null, 'case_id' => null]);
            Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $from, 'to_code' => $place, 'kind' => $order ? 'return' : 'move',
                'order_id' => $order, 'user_id' => $by->id, 'note' => __('equipment.quick.note_available')]);
            Check::where('asset_id', $asset->id)->whereNull('finished_at')->update(['finished_at' => now(), 'result' => 'skipped', 'checked_by_id' => $by->id]);
            if ($order) {
                Moves::maybeReturned(Order::find($order));
            }
        });
    }

    /** Back from one job and straight out with the next (prolonged or re-rented), on paper only. */
    public static function handOver(Asset $asset, Order $to, User $by): void
    {
        $fail = fn (string $key) => throw ValidationException::withMessages(['quick' => __('equipment.quick.errors.'.$key, ['code' => $asset->code, 'order' => $to->number])]);
        if (in_array($asset->status, [Asset::REPAIR, Asset::RETIRED], true)) {
            $fail('not_rentable');
        }
        if ($asset->status === Asset::OUT && $asset->out_order_id === $to->id) {
            return;
        }
        if (in_array($to->status, ['cancelled', 'replaced'], true) || $to->returned_at) {
            $fail('order_closed');
        }
        DB::transaction(function () use ($asset, $to, $by, $fail) {
            $line = $to->lines()->with(['item', 'assets.asset'])->where('item_id', $asset->item_id)->get()->first(fn ($l) => $l->needsStock() && $l->bySerial());
            if (! $line) {
                $fail('not_on_order');
            }
            if (! $to->assets()->where('asset_id', $asset->id)->exists()) {
                // Takes the place of a piece booked but still in the warehouse, or a free place on the line.
                $swap = $line->assets->first(fn ($oa) => $oa->asset && $oa->asset->status !== Asset::OUT && $oa->asset->location !== 'DISPATCH');
                if ($swap) {
                    $swap->update(['asset_id' => $asset->id]);
                } elseif ($line->ownAllocated() < $line->ownNeeded()) {
                    OrderAsset::create(['order_id' => $to->id, 'order_line_id' => $line->id, 'asset_id' => $asset->id]);
                } else {
                    $fail('order_full');
                }
            }
            $fromOrder = $asset->status === Asset::OUT ? $asset->out_order_id : null;
            $from = $asset->location;
            if ($fromOrder) {
                Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => Moves::OUT, 'to_code' => Moves::OUT, 'kind' => 'return',
                    'order_id' => $fromOrder, 'user_id' => $by->id, 'note' => __('equipment.quick.note_handover', ['order' => $to->number])]);
            }
            $asset->update(['status' => Asset::OUT, 'location' => Moves::OUT, 'out_order_id' => $to->id, 'case_id' => null]);
            Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $fromOrder ? Moves::OUT : $from, 'to_code' => Moves::OUT, 'kind' => 'load',
                'order_id' => $to->id, 'user_id' => $by->id, 'note' => __('equipment.quick.note_handover', ['order' => $to->number])]);
            Check::where('asset_id', $asset->id)->whereNull('finished_at')->update(['finished_at' => now(), 'result' => 'skipped', 'checked_by_id' => $by->id]);
            if ($fromOrder) {
                Moves::maybeReturned(Order::find($fromOrder));
            }
        });
    }

    /** Needs a look before it goes out again (status Check). */
    public static function toCheck(Asset $asset, User $by): void
    {
        if (! in_array($asset->status, [Asset::AVAILABLE], true) || $asset->location === 'DISPATCH') {
            throw ValidationException::withMessages(['quick' => __('equipment.quick.errors.not_in', ['code' => $asset->code])]);
        }
        $asset->update(['status' => Asset::CHECK]);
        Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $asset->location, 'to_code' => $asset->location, 'kind' => 'move',
            'user_id' => $by->id, 'note' => __('equipment.quick.note_check')]);
    }

    /** The rack place it came from before it went out (else none). */
    private static function lastPlace(Asset $asset): ?string
    {
        $code = Move::where('asset_id', $asset->id)->where('kind', 'pick')->latest('id')->value('from_code');

        return $code && ! in_array($code, [Moves::OUT, 'DISPATCH', 'INBOUND'], true) ? $code : null;
    }

    /** Orders a piece of this item can be handed over to: not back yet, with a serial line for it. */
    public static function targets(int $itemId)
    {
        return Order::with('company')->whereNull('returned_at')->whereNotIn('status', ['cancelled', 'replaced'])
            ->whereHas('lines', fn ($q) => $q->where('item_id', $itemId))
            ->where('out_until', '>=', now()->subDays(2))->orderBy('out_from')->take(40)->get();
    }

    public static function audit(string $what, $item, array $codes, User $by, array $extra = []): void
    {
        AuditLog::record('equipment.quick_'.$what, $item, ['codes' => implode(', ', $codes)] + $extra, $by);
    }
}
