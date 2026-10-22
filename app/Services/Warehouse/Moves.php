<?php

namespace App\Services\Warehouse;

use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\OrderLine;
use App\Models\Rental\SubhireRequest;
use App\Models\User;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\Check;
use App\Models\Warehouse\Move;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use App\Services\Finance\PreInvoices;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every move is a scan: scan the piece, then where it goes. The route of the zones
 * decides what is allowed; the status of the piece follows where it is.
 */
class Moves
{
    /** Where a piece is that is out on a job. */
    public const OUT = 'OUT';

    /**
     * What a scanned text is: a piece, a zone or a position. Labels hold the full
     * address (…/a/LT-0142 or …/l/CLEAN); typed codes work too.
     *
     * @return array{type: string, model: Asset|Zone|Position}|null
     */
    public static function resolve(string $raw): ?array
    {
        $raw = trim($raw);
        if (preg_match('#/(a|l)/([A-Za-z0-9\-]+)/?$#', $raw, $m)) {
            $raw = $m[2];
        }
        $code = strtoupper($raw);
        if ($code === '') {
            return null;
        }
        if ($a = Asset::with('item')->where('code', $code)->first()) {
            return ['type' => 'asset', 'model' => $a];
        }
        if ($z = Zone::where('code', $code)->where('active', true)->first()) {
            return ['type' => 'zone', 'model' => $z];
        }
        if ($p = Position::where('code', $code)->where('active', true)->first()) {
            return ['type' => 'position', 'model' => $p];
        }

        return null;
    }

    /** The zone a location code belongs to: positions are in the storage zone. */
    public static function zoneOf(?string $code): ?Zone
    {
        if (! $code || $code === self::OUT) {
            return null;
        }
        if ($z = Zone::byCode($code)) {
            return $z;
        }

        return Position::byCode($code) ? Zone::where('type', 'storage')->orderBy('sort')->first() : null;
    }

    /** The name of a location for people: zone name or position code. */
    public static function label(?string $code): string
    {
        if (! $code) {
            return __('warehouse.nowhere');
        }
        if ($code === self::OUT) {
            return __('warehouse.out');
        }

        return Zone::byCode($code)?->label() ?? $code;
    }

    /** Is this move allowed? Throws with the reason for the crew when not. */
    public static function check(Asset $asset, Zone|Position $to): void
    {
        $fail = fn (string $key, array $r = []) => throw ValidationException::withMessages(['code' => __('warehouse.errors.'.$key, $r + ['code' => $asset->code])]);
        $toCode = $to->code;
        $toZone = $to instanceof Zone ? $to : Zone::where('type', 'storage')->orderBy('sort')->first();
        $from = self::zoneOf($asset->location);

        if ($asset->status === Asset::RETIRED) {
            $fail('retired');
        }
        if ($asset->location === $toCode) {
            $fail('already', ['place' => self::label($toCode)]);
        }
        if ($to instanceof Position && ! in_array($to->use, ['rental', 'spare'], true)) {
            $fail('not_rental_place', ['place' => $toCode]);
        }
        if ($toZone?->type === 'dispatch') {
            $fail('use_pick_list');
        }
        if ($asset->status === Asset::OUT || $asset->location === self::OUT) {
            if ($toZone?->type !== 'inbound') {
                $fail('back_via_inbound');
            }

            return;
        }
        if (! $from) {
            return; // first time on the route: anywhere
        }
        if ($from->type === 'cleaning' && ! Check::where('asset_id', $asset->id)->whereNull('finished_at')->whereNotNull('cleaned_at')->exists()) {
            $fail('clean_first');
        }
        if ($from->type === 'bench') {
            $check = Check::where('asset_id', $asset->id)->latest('id')->first();
            if (! $check?->finished_at) {
                $fail('check_first');
            }
            if ($check->result === 'repair' && $toZone?->type !== 'repair') {
                $fail('broken_to_repair');
            }
        }
        if ($from->type === 'repair' && ! $asset->repair_released_at) {
            $fail('not_released');
        }
        $next = $from->next ?? [];
        $ok = $to instanceof Position ? in_array(Zone::RACKS, $next, true) || in_array($toZone?->code, $next, true) : in_array($toCode, $next, true);
        if ($from->type === 'storage' && $to instanceof Position) {
            $ok = true; // re-shelving
        }
        if (! $ok) {
            $names = collect($next)->map(fn ($c) => $c === Zone::RACKS ? __('warehouse.zone_names.RACKS') : self::label($c))->implode(', ');
            $fail('route', ['from' => $from->label(), 'next' => $names ?: '—']);
        }
        if ($to instanceof Zone && ! $to->takes($asset->item)) {
            $right = Zone::where('type', $to->type)->where('active', true)->get()->first(fn (Zone $z) => $z->takes($asset->item) && ! empty($z->categories));
            $fail('wrong_category', ['place' => $to->label(), 'right' => $right?->label() ?? '—']);
        }
    }

    /** Move a piece after a scan. Returns what the phone should open next (clean, check) or null. */
    public static function move(Asset $asset, Zone|Position $to, User $by): ?string
    {
        self::check($asset, $to);
        $toZone = $to instanceof Zone ? $to : Zone::where('type', 'storage')->orderBy('sort')->first();

        return DB::transaction(function () use ($asset, $to, $toZone, $by) {
            $from = $asset->location;
            $kind = 'move';
            $orderId = null;
            $data = ['location' => $to->code];
            if ($asset->status === Asset::OUT || $from === self::OUT) {
                $kind = 'return';
                $orderId = $asset->out_order_id;
                $data += ['status' => Asset::CHECK, 'out_order_id' => null];
            }
            $next = null;
            match ($toZone?->type) {
                'cleaning' => $next = 'clean',
                'bench' => $next = 'check',
                'repair' => $data += ['status' => Asset::REPAIR, 'repair_released_at' => null],
                'storage' => $data += ['status' => Asset::AVAILABLE],
                default => $data += in_array($asset->status, [Asset::REPAIR, Asset::OUT], true) || $kind === 'return' ? ['status' => Asset::CHECK] : [],
            };
            $asset->update($data);
            Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $from, 'to_code' => $to->code, 'kind' => $kind, 'order_id' => $orderId, 'user_id' => $by->id]);
            if ($toZone?->type === 'cleaning') {
                Check::openFor($asset);
            }
            if ($orderId) {
                self::maybeReturned(Order::find($orderId));
            }

            return $next;
        });
    }

    // ------------------------------------------------------------------ picking

    /** Own pieces of an order: serial lines with their assets, counted lines with a quantity. */
    public static function pickLines(Order $order)
    {
        return $order->lines()->with(['item', 'assets.asset', 'subhireLines.request.supplier'])->get()
            ->filter(fn (OrderLine $l) => $l->needsStock() && ($l->ownAllocated() > 0 || $l->rentedQty() > 0))->values();
    }

    /** Sub-hire for this order that the partner has not delivered yet (counted and photographed on the phone). */
    public static function subhireWaiting(Order $order)
    {
        return SubhireRequest::with('supplier')->where('order_id', $order->id)->where('status', 'accepted')->whereNull('received_at')->get();
    }

    public static function isPicked(Order $order): bool
    {
        if (self::subhireWaiting($order)->isNotEmpty()) {
            return false;
        }
        foreach (self::pickLines($order) as $l) {
            if ($l->bySerial()) {
                if ($l->assets->contains(fn ($oa) => ! ($oa->asset->location === 'DISPATCH' && $oa->asset->out_order_id === $order->id))) {
                    return false;
                }
            } elseif ($l->picked_qty < $l->ownAllocated()) {
                return false;
            }
        }

        return true;
    }

    public static function pick(Order $order, Asset $asset, User $by): void
    {
        $fail = fn (string $key) => throw ValidationException::withMessages(['code' => __('warehouse.errors.'.$key, ['code' => $asset->code, 'order' => $order->number])]);
        if (! $order->assets()->where('asset_id', $asset->id)->exists()) {
            $fail('not_on_order');
        }
        if ($asset->location === 'DISPATCH' && $asset->out_order_id === $order->id) {
            $fail('picked_already');
        }
        if ($asset->status !== Asset::AVAILABLE) {
            $fail('not_rentable');
        }
        $dispatch = Zone::where('type', 'dispatch')->orderBy('sort')->firstOrFail();
        $from = $asset->location;
        $asset->update(['location' => $dispatch->code, 'out_order_id' => $order->id]);
        Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $from, 'to_code' => $dispatch->code, 'kind' => 'pick', 'order_id' => $order->id, 'user_id' => $by->id]);
    }

    public static function count(OrderLine $line, string $field, int $delta, User $by): void
    {
        $max = $field === 'picked_qty' ? $line->ownAllocated() : $line->picked_qty;
        $new = max(0, min($max, $line->{$field} + $delta));
        if ($new === $line->{$field}) {
            return;
        }
        $old = $line->{$field};
        $line->update([$field => $new]);
        Move::create(['item_id' => $line->item_id, 'qty' => $new - $old, 'kind' => $field === 'picked_qty' ? 'pick' : 'return',
            'to_code' => $field === 'picked_qty' ? 'DISPATCH' : 'INBOUND', 'order_id' => $line->order_id, 'user_id' => $by->id]);
    }

    /** Everything picked goes onto the truck: the pieces are out with this order. */
    public static function load(Order $order, User $by): void
    {
        if (! self::isPicked($order)) {
            throw ValidationException::withMessages(['code' => __('warehouse.errors.not_all_picked')]);
        }
        DB::transaction(function () use ($order, $by) {
            foreach ($order->assets()->with('asset')->get() as $oa) {
                $oa->asset->update(['status' => Asset::OUT, 'location' => self::OUT, 'out_order_id' => $order->id]);
                Move::create(['asset_id' => $oa->asset_id, 'item_id' => $oa->asset->item_id, 'from_code' => 'DISPATCH', 'to_code' => self::OUT, 'kind' => 'load', 'order_id' => $order->id, 'user_id' => $by->id]);
            }
            $order->update(['loaded_at' => now(), 'loaded_by_id' => $by->id]);
            AuditLog::record('order.loaded', $order, [], $by);
        });
    }

    // ------------------------------------------------------------------ returns

    /** Are all own pieces back (serial scanned into Inbound, counted ones counted)? */
    public static function allBack(Order $order): bool
    {
        if (! $order->loaded_at) {
            return false;
        }
        if (Asset::where('out_order_id', $order->id)->where('status', Asset::OUT)->exists()) {
            return false;
        }

        return self::pickLines($order)->reject(fn ($l) => $l->bySerial())->every(fn ($l) => $l->returned_qty >= $l->picked_qty);
    }

    public static function maybeReturned(?Order $order): void
    {
        if ($order && ! $order->returned_at && self::allBack($order)) {
            $order->update(['returned_at' => now()]);
            PreInvoices::make('rental', $order, $order->handedBy);
        }
    }

    /**
     * Close the return: counted pieces that did not come back are written off the
     * stock and become charges for the office to approve.
     */
    public static function closeReturn(Order $order, User $by): int
    {
        if (Asset::where('out_order_id', $order->id)->where('status', Asset::OUT)->exists()) {
            throw ValidationException::withMessages(['code' => __('warehouse.errors.serial_still_out')]);
        }

        return DB::transaction(function () use ($order, $by) {
            $n = 0;
            foreach (self::pickLines($order)->reject(fn ($l) => $l->bySerial()) as $l) {
                $short = $l->picked_qty - $l->returned_qty;
                if ($short <= 0) {
                    continue;
                }
                self::writeOff($l->item, $short, $order, $by, 'return');
                Charge::create(['order_id' => $order->id, 'item_id' => $l->item_id, 'kind' => 'missing', 'qty' => $short,
                    'label' => __('warehouse.charge.missing', ['name' => $l->item->name, 'number' => $l->item->number], $order->language),
                    'amount_cents' => $l->item->catalog_price_cents ? $l->item->catalog_price_cents * $short : null,
                    'original_cents' => $l->item->catalog_price_cents ? $l->item->catalog_price_cents * $short : null]);
                $l->update(['returned_qty' => $l->picked_qty]);
                $n++;
            }
            $order->update(['returned_at' => $order->returned_at ?? now()]);
            AuditLog::record('order.returned', $order, ['missing_lines' => $n], $by);
            PreInvoices::make('rental', $order, $order->handedBy ?? $by);

            return $n;
        });
    }

    /** Counted stock goes down: lost by the customer. */
    public static function writeOff(Item $item, int $qty, ?Order $order, User $by, string $where): void
    {
        if ($item->serial) {
            return;
        }
        $item->update(['stock_qty' => max(0, $item->stock_qty - $qty)]);
        Move::create(['item_id' => $item->id, 'qty' => -$qty, 'kind' => 'lost', 'order_id' => $order?->id, 'user_id' => $by->id, 'note' => $where]);
    }

    /** Someone with the right lets a repaired piece back on the route (to Pre-check). */
    public static function releaseRepair(Asset $asset, User $by): void
    {
        $asset->update(['repair_released_at' => now()]);
        Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'from_code' => $asset->location, 'to_code' => $asset->location, 'kind' => 'release', 'user_id' => $by->id]);
        AuditLog::record('warehouse.released', $asset, ['code' => $asset->code], $by);
    }
}
