<?php

namespace App\Services\Rental;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Sales\Quote;
use Illuminate\Support\Carbon;

/**
 * How many of an item are free in a period: what we own (not in repair or
 * retired) minus what accepted quotes and quotes within their option time
 * hold in an overlapping period. Packages count their contents.
 * Order allocation (step 4) will replace quotes by orders as the source.
 */
class Availability
{
    /** @var array<int, int> reserved qty per item id */
    private array $reserved;

    public function __construct(private Carbon $from, private Carbon $until, private ?string $exceptNumber = null)
    {
        $this->reserved = [];
        $quotes = Quote::holding()
            ->where('out_from', '<', $until)->where('out_until', '>', $from)
            ->when($exceptNumber, fn ($q) => $q->where('number', '!=', $exceptNumber))
            ->with('lines.item.contents')->get();
        foreach ($quotes as $quote) {
            foreach ($quote->lines as $line) {
                if ($line->kind !== 'item' || ! $line->item) {
                    continue;
                }
                $this->add($line->item, (int) ceil((float) $line->qty));
            }
        }
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
}
