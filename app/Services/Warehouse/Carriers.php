<?php

namespace App\Services\Warehouse;

use App\Models\Equipment\Asset;
use App\Models\Rental\Order;
use App\Models\User;
use App\Models\Warehouse\Carrier;
use App\Models\Warehouse\Move;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Light dollies and storage boxes: put pieces in, move the whole dolly with one
 * scan, pick it for an order (it goes with the customer at no charge) and scan
 * it back in at the return. A piece moved or picked on its own leaves its dolly.
 */
class Carriers
{
    private static function fail(string $key, array $r = []): never
    {
        throw ValidationException::withMessages(['code' => __('warehouse.errors.'.$key, $r)]);
    }

    /** Put a piece in a dolly or box. It takes the place of the dolly (when the dolly has one). */
    public static function pack(Asset $asset, Carrier $carrier, User $by): void
    {
        $r = ['code' => $asset->code, 'carrier' => $carrier->code];
        if ($carrier->out_order_id) {
            self::fail('carrier_out', $r + ['order' => $carrier->outOrder?->number ?? '—']);
        }
        if ($asset->carrier_id === $carrier->id) {
            self::fail('carrier_already', $r);
        }
        if ($asset->status === Asset::RETIRED) {
            self::fail('retired', $r);
        }
        if ($asset->status !== Asset::AVAILABLE || $asset->out_order_id) {
            self::fail('carrier_store_first', $r);
        }
        DB::transaction(function () use ($asset, $carrier, $by) {
            $from = $asset->location;
            $asset->update(['carrier_id' => $carrier->id, 'location' => $carrier->location ?? $asset->location]);
            Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $from, 'to_code' => $carrier->code, 'kind' => 'pack', 'user_id' => $by->id, 'carrier_id' => $carrier->id]);
        });
    }

    /** Take a piece out of its dolly or box (it stays where the dolly is). */
    public static function unpack(Asset $asset, User $by): void
    {
        $carrier = $asset->carrier;
        if (! $carrier) {
            return;
        }
        if ($carrier->out_order_id) {
            self::fail('carrier_out', ['carrier' => $carrier->code, 'order' => $carrier->outOrder?->number ?? '—']);
        }
        $asset->update(['carrier_id' => null]);
        Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $carrier->code, 'to_code' => $asset->location, 'kind' => 'unpack', 'user_id' => $by->id, 'carrier_id' => $carrier->id]);
    }

    /** Move a dolly or box with everything in it to a rack place (or a storage zone). Returns the number of pieces moved. */
    public static function move(Carrier $carrier, Zone|Position $to, User $by): int
    {
        $r = ['carrier' => $carrier->code, 'place' => Moves::label($to->code)];
        if ($carrier->out_order_id) {
            self::fail('carrier_out', $r + ['order' => $carrier->outOrder?->number ?? '—']);
        }
        if ($to instanceof Zone && $to->type !== 'storage' && $to->type !== 'inbound') {
            self::fail('carrier_to_rack', $r);
        }
        if ($to instanceof Position && ! in_array($to->use, ['rental', 'spare'], true)) {
            self::fail('not_rental_place', ['place' => $to->code]);
        }
        $pieces = $carrier->assets()->with('item')->get()->reject(fn (Asset $a) => $a->location === $to->code);
        // Every piece must be allowed to go there; otherwise nothing moves and the crew sees why.
        foreach ($pieces as $a) {
            Moves::check($a, $to);
        }

        return DB::transaction(function () use ($carrier, $to, $by, $pieces) {
            foreach ($pieces as $a) {
                Moves::move($a, $to, $by, $carrier);
            }
            $carrier->update(['location' => $to->code]);

            return $pieces->count();
        });
    }

    /**
     * Pick a dolly or box for an order: every piece in it must be on the order; they are
     * picked together and the dolly goes with the order (no charge). Returns the number of pieces picked.
     */
    public static function pick(Order $order, Carrier $carrier, User $by): int
    {
        $r = ['carrier' => $carrier->code, 'order' => $order->number];
        if ($carrier->out_order_id === $order->id) {
            self::fail('carrier_picked_already', $r);
        }
        if ($carrier->out_order_id) {
            self::fail('carrier_out', $r + ['order' => $carrier->outOrder?->number ?? '—']);
        }
        $pieces = $carrier->assets()->get();
        $onOrder = $order->assets()->pluck('asset_id')->all();
        $strangers = $pieces->reject(fn (Asset $a) => in_array($a->id, $onOrder, true));
        if ($strangers->isNotEmpty()) {
            self::fail('carrier_not_on_order', $r + ['codes' => $strangers->pluck('code')->implode(', ')]);
        }
        $dispatch = Zone::where('type', 'dispatch')->orderBy('sort')->firstOrFail();

        return DB::transaction(function () use ($order, $carrier, $by, $pieces, $dispatch) {
            $n = 0;
            foreach ($pieces as $a) {
                if ($a->location === $dispatch->code && $a->out_order_id === $order->id) {
                    continue; // picked before it went in the dolly
                }
                Moves::pick($order, $a, $by, $carrier);
                $n++;
            }
            $carrier->update(['location' => $dispatch->code, 'out_order_id' => $order->id]);

            return $n;
        });
    }

    /**
     * Back from a job: the dolly or box comes in through Inbound, empty. What was in it is not
     * trusted: every piece is scanned in on its own and goes in the dolly scanned before it
     * (pieces may change dolly; what counts is that every piece is back).
     */
    public static function returnIn(Order $order, Carrier $carrier, User $by): void
    {
        if ($carrier->out_order_id !== $order->id) {
            self::fail('carrier_not_out_with', ['carrier' => $carrier->code, 'order' => $order->number]);
        }
        $inbound = Zone::where('type', 'inbound')->where('active', true)->orderBy('sort')->firstOrFail();
        DB::transaction(function () use ($carrier, $inbound) {
            Asset::where('carrier_id', $carrier->id)->update(['carrier_id' => null]);
            $carrier->update(['location' => $inbound->code, 'out_order_id' => null]);
        });
    }
}
