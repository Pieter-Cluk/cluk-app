<?php

namespace App\Services\Warehouse;

use App\Models\Equipment\Asset;
use App\Models\Equipment\CaseItem;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\User;
use App\Models\Warehouse\Move;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cases (light dollies, storage boxes, flight cases…): pieces of a dry-hire item of the
 * kind "case". Put pieces in, move the whole case with one scan, pick it for an order
 * (a case that is not on the order goes with it at no charge) and, at the return, scan
 * the case and then every piece that goes in it. A piece moved or picked on its own
 * leaves its case.
 */
class Carriers
{
    private static function fail(string $key, array $r = []): never
    {
        throw ValidationException::withMessages(['code' => __('warehouse.errors.'.$key, $r)]);
    }

    /** Is this case with a customer (out with or picked for an order)? */
    private static function away(Asset $case): bool
    {
        return (bool) $case->out_order_id;
    }

    /** Put a piece in a case. It takes the place of the case (when the case has one). */
    public static function pack(Asset $asset, Asset $case, User $by): void
    {
        $r = ['code' => $asset->code, 'carrier' => $case->code];
        if (self::away($case)) {
            self::fail('carrier_out', $r + ['order' => $case->outOrder?->number ?? '—']);
        }
        if ($asset->isContainer()) {
            self::fail('carrier_in_carrier', $r);
        }
        if ($asset->case_id === $case->id) {
            self::fail('carrier_already', $r);
        }
        if ($asset->status === Asset::RETIRED) {
            self::fail('retired', $r);
        }
        if ($asset->status !== Asset::AVAILABLE || $asset->out_order_id) {
            self::fail('carrier_store_first', $r);
        }
        DB::transaction(function () use ($asset, $case, $by) {
            $from = $asset->location;
            $asset->update(['case_id' => $case->id, 'location' => $case->location ?? $asset->location]);
            Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $from, 'to_code' => $case->code, 'kind' => 'pack', 'user_id' => $by->id, 'case_id' => $case->id]);
        });
    }

    /** More (+n) or less (−n) of a counted item in a case. Never more in cases than in stock. */
    public static function packCount(Item $item, Asset $case, int $delta, User $by): int
    {
        $r = ['code' => $item->type ?: $item->name, 'carrier' => $case->code];
        if (self::away($case)) {
            self::fail('carrier_out', $r + ['order' => $case->outOrder?->number ?? '—']);
        }
        $row = CaseItem::firstOrNew(['case_id' => $case->id, 'item_id' => $item->id]);
        $new = (int) $row->qty + $delta;
        if ($new < 0) {
            self::fail('carrier_not_in', $r);
        }
        if ($delta > 0 && CaseItem::where('item_id', $item->id)->sum('qty') + $delta > $item->stock_qty) {
            self::fail('carrier_more_than_stock', $r + ['n' => $item->stock_qty]);
        }
        DB::transaction(function () use ($row, $new, $item, $case, $delta, $by) {
            $row->fill(['qty' => $new])->save();
            Move::create(['item_id' => $item->id, 'qty' => $delta, 'from_code' => $delta > 0 ? null : $case->code, 'to_code' => $delta > 0 ? $case->code : null,
                'kind' => $delta > 0 ? 'pack' : 'unpack', 'user_id' => $by->id, 'case_id' => $case->id]);
        });

        return $new;
    }

    /** Take a piece out of its case (it stays where the case is). */
    public static function unpack(Asset $asset, User $by): void
    {
        $case = $asset->container;
        if (! $case) {
            return;
        }
        if (self::away($case)) {
            self::fail('carrier_out', ['carrier' => $case->code, 'order' => $case->outOrder?->number ?? '—']);
        }
        $asset->update(['case_id' => null]);
        Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $case->code, 'to_code' => $asset->location, 'kind' => 'unpack', 'user_id' => $by->id, 'case_id' => $case->id]);
    }

    /** Move a case with everything in it to a rack place (or storage, inbound). Returns the number of pieces moved. */
    public static function move(Asset $case, Zone|Position $to, User $by): int
    {
        $r = ['carrier' => $case->code, 'place' => Moves::label($to->code)];
        if (self::away($case)) {
            self::fail('carrier_out', $r + ['order' => $case->outOrder?->number ?? '—']);
        }
        if ($case->status !== Asset::AVAILABLE) {
            self::fail('carrier_store_first', ['code' => $case->code]);
        }
        if ($to instanceof Zone && ! in_array($to->type, ['storage', 'inbound'], true)) {
            self::fail('carrier_to_rack', $r);
        }
        if ($to instanceof Position && ! in_array($to->use, ['rental', 'spare'], true)) {
            self::fail('not_rental_place', ['place' => $to->code]);
        }
        $pieces = $case->packed()->with('item')->get()->reject(fn (Asset $a) => $a->location === $to->code);
        // Every piece must be allowed to go there; otherwise nothing moves and the crew sees why.
        foreach ($pieces as $a) {
            Moves::check($a, $to);
        }

        return DB::transaction(function () use ($case, $to, $by, $pieces) {
            foreach ($pieces as $a) {
                Moves::move($a, $to, $by, $case);
            }
            $from = $case->location;
            $case->update(['location' => $to->code]);
            Move::create(['asset_id' => $case->id, 'item_id' => $case->item_id, 'from_code' => $from, 'to_code' => $to->code, 'kind' => 'move', 'user_id' => $by->id]);

            return $pieces->count();
        });
    }

    /**
     * Pick a case for an order: every piece in it must be on the order; they are picked
     * together. The case itself is picked when it is on the order, otherwise it goes
     * with the order at no charge. Returns the number of pieces picked.
     */
    public static function pick(Order $order, Asset $case, User $by): int
    {
        $r = ['carrier' => $case->code, 'order' => $order->number];
        if ($case->out_order_id === $order->id) {
            self::fail('carrier_picked_already', $r);
        }
        if (self::away($case)) {
            self::fail('carrier_out', $r + ['order' => $case->outOrder?->number ?? '—']);
        }
        $onOrder = $order->assets()->pluck('asset_id')->all();
        $pieces = $case->packed()->get();
        $strangers = $pieces->reject(fn (Asset $a) => in_array($a->id, $onOrder, true))->pluck('code');
        // Counted items in the case: on the order, and not more than still to pick.
        $counted = [];
        foreach ($case->counted()->with('item')->get() as $ci) {
            $line = Moves::countedLine($order, $ci->item);
            if (! $line || $line->picked_qty + $ci->qty > $line->ownAllocated()) {
                $strangers->push($ci->qty.' × '.($ci->item->type ?: $ci->item->name));
            } else {
                $counted[] = [$line, $ci->qty];
            }
        }
        if ($strangers->isNotEmpty()) {
            self::fail('carrier_not_on_order', $r + ['codes' => $strangers->implode(', ')]);
        }
        if ($case->status !== Asset::AVAILABLE) {
            self::fail('not_rentable', ['code' => $case->code]);
        }
        $dispatch = Zone::where('type', 'dispatch')->orderBy('sort')->firstOrFail();

        return DB::transaction(function () use ($order, $case, $by, $pieces, $dispatch, $onOrder, $counted) {
            $n = 0;
            foreach ($counted as [$line, $qty]) {
                Moves::count($line, 'picked_qty', $qty, $by);
                $n += $qty;
            }
            foreach ($pieces as $a) {
                if ($a->location === $dispatch->code && $a->out_order_id === $order->id) {
                    continue; // picked before it went in the case
                }
                Moves::pick($order, $a, $by, $case);
                $n++;
            }
            if (in_array($case->id, $onOrder, true)) {
                Moves::pick($order, $case, $by);
            } else {
                $from = $case->location;
                $case->update(['location' => $dispatch->code, 'out_order_id' => $order->id]);
                Move::create(['asset_id' => $case->id, 'item_id' => $case->item_id, 'from_code' => $from, 'to_code' => $dispatch->code, 'kind' => 'pick', 'order_id' => $order->id, 'user_id' => $by->id]);
            }

            return $n;
        });
    }

    /**
     * Back from a job: the case comes in through Inbound, empty. What was in it is not
     * trusted: every piece is scanned in on its own and goes in the case scanned before it
     * (pieces may change case; what counts is that every piece is back).
     */
    public static function returnIn(Order $order, Asset $case, User $by): void
    {
        if ($case->out_order_id !== $order->id || $case->status !== Asset::OUT) {
            self::fail('carrier_not_out_with', ['carrier' => $case->code, 'order' => $order->number]);
        }
        $inbound = Zone::where('type', 'inbound')->where('active', true)->orderBy('sort')->firstOrFail();
        DB::transaction(function () use ($order, $case, $by, $inbound) {
            Asset::where('case_id', $case->id)->update(['case_id' => null]);
            CaseItem::where('case_id', $case->id)->delete();
            if ($order->assets()->where('asset_id', $case->id)->exists()) {
                Moves::move($case, $inbound, $by); // a hired case goes the normal way: cleaning and the check
            } else {
                $case->update(['location' => $inbound->code, 'status' => Asset::AVAILABLE, 'out_order_id' => null]);
                Move::create(['asset_id' => $case->id, 'item_id' => $case->item_id, 'from_code' => Moves::OUT, 'to_code' => $inbound->code, 'kind' => 'return', 'order_id' => $order->id, 'user_id' => $by->id]);
            }
        });
    }

    /**
     * From the office: put a piece where it is (no scanning). The code is a rack place
     * or storage (the piece goes there) or a case (it goes in and follows the case).
     * For a case itself: the case moves there with everything in it. Returns where it is now.
     */
    public static function place(Asset $piece, string $code, User $by): string
    {
        $found = Moves::resolve($code);
        $target = $found['model'] ?? null;
        if (! $target || ($found['type'] ?? null) === 'item') {
            self::fail('unknown', ['code' => mb_strimwidth($code, 0, 40, '…')]);
        }
        if ($target instanceof Asset) {
            if (! $target->isContainer()) {
                self::fail('office_not_a_case', ['code' => $target->code]);
            }
            self::pack($piece, $target, $by);

            return $target->code;
        }
        if ($piece->isContainer()) {
            self::move($piece, $target, $by);

            return $target->code;
        }
        if ($piece->status !== Asset::AVAILABLE || $piece->out_order_id) {
            self::fail('carrier_store_first', ['code' => $piece->code]);
        }
        if ($target instanceof Zone && $target->type !== 'storage') {
            self::fail('office_rack_only', ['code' => $piece->code, 'place' => Moves::label($target->code)]);
        }
        if ($target instanceof Position && ! in_array($target->use, ['rental', 'spare'], true)) {
            self::fail('not_rental_place', ['place' => $target->code]);
        }
        DB::transaction(function () use ($piece, $target, $by) {
            $from = $piece->location;
            $piece->update(['location' => $target->code, 'case_id' => null]);
            Move::create(['asset_id' => $piece->id, 'item_id' => $piece->item_id, 'from_code' => $from, 'to_code' => $target->code, 'kind' => 'move', 'user_id' => $by->id, 'note' => 'office']);
        });

        return $target->code;
    }
}
