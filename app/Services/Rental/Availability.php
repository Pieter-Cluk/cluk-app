<?php

namespace App\Services\Rental;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\Sales\Quote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How many of an item are free in a period: what we own (not in repair or
 * retired) minus what is held in an overlapping period by
 *  - open orders: every piece not covered by sub-hire, and
 *  - quotes without an order yet: sent ones during their option time.
 * Packages count their contents. Pieces rented from partners never use
 * our own stock.
 */
class Availability
{
    /** @var array<int, int> reserved qty per item id */
    private array $reserved = [];

    /** @var array<int, true> serial assets booked on other orders in this period */
    private array $bookedAssets = [];

    public function __construct(private Carbon $from, private Carbon $until, private ?string $exceptQuoteNumber = null, private ?int $exceptOrderId = null)
    {
        $quotes = Quote::holding()->whereDoesntHave('order')
            ->where('out_from', '<', $until)->where('out_until', '>', $from)
            ->when($exceptQuoteNumber, fn ($q) => $q->where('number', '!=', $exceptQuoteNumber))
            ->with('lines.item.contents')->get();
        foreach ($quotes as $quote) {
            foreach ($quote->lines as $line) {
                if ($line->kind === 'item' && $line->item) {
                    $this->add($line->item, (int) ceil((float) $line->qty));
                }
            }
        }

        $orders = Order::open()->overlapping($from, $until)
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId))
            ->with(['lines.item.contents', 'lines.subhireLines.request', 'lines.assets'])->get();
        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                if ($line->needsStock()) {
                    $this->add($line->item, $line->ownNeeded());
                }
            }
        }

        OrderAsset::query()->whereIn('order_id', $orders->pluck('id'))->pluck('asset_id')
            ->each(fn ($id) => $this->bookedAssets[$id] = true);
    }

    private function add(Item $item, int $qty): void
    {
        if ($item->kind === 'package') {
            foreach ($item->contents as $c) {
                $this->add($c, $qty * (int) $c->pivot->qty);
            }

            return;
        }
        $this->reserved[$item->id] = ($this->reserved[$item->id] ?? 0) + $qty;
    }

    public function owned(Item $item): int
    {
        if ($item->kind === 'service') {
            return PHP_INT_MAX;
        }

        return $item->serial
            ? $item->assets()->whereNotIn('status', [Asset::RETIRED, Asset::REPAIR])->count()
            : (int) $item->stock_qty;
    }

    public function reserved(Item $item): int
    {
        return $this->reserved[$item->id] ?? 0;
    }

    /** Free qty in the period (null = not limited, e.g. services). */
    public function free(Item $item): ?int
    {
        if ($item->kind === 'service') {
            return null;
        }
        if ($item->kind === 'package') {
            $min = null;
            foreach ($item->contents as $c) {
                $f = $this->free($c);
                if ($f === null) {
                    continue;
                }
                $n = intdiv(max(0, $f), max(1, (int) $c->pivot->qty));
                $min = $min === null ? $n : min($min, $n);
            }

            return $min ?? 0;
        }

        return $this->owned($item) - $this->reserved($item);
    }

    /**
     * Serial pieces of an item not booked on another order in this period,
     * best first: in the warehouse now, then by code.
     *
     * @return Collection<int, Asset>
     */
    public function freeAssets(Item $item): Collection
    {
        return $item->assets()->whereNotIn('status', [Asset::RETIRED, Asset::REPAIR])->orderByRaw("status = 'available' desc")->orderBy('code')->get()
            ->reject(fn (Asset $a) => isset($this->bookedAssets[$a->id]))->values();
    }
}
