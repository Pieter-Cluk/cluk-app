<?php

namespace App\Models\Equipment;

use App\Models\Rental\Order;
use App\Models\Warehouse\Move;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** One physical piece of a serial item, with its own code and QR label. */
#[Fillable([
    'item_id', 'code', 'serial_number', 'status', 'location', 'out_order_id', 'repair_released_at', 'purchase_date', 'purchase_price_cents',
    'supplier', 'warranty_until', 'note', 'retired_reason', 'retired_at', 'label_printed_at', 'case_id',
])]
class Asset extends Model
{
    protected $table = 'equipment_assets';

    public const AVAILABLE = 'available';

    public const OUT = 'out';

    public const REPAIR = 'repair';

    /** Back from a job and on its way through cleaning and the check: not rentable yet. */
    public const CHECK = 'check';

    public const RETIRED = 'retired';

    /** A QR label printed in advance; the piece is not found yet and does not count. The first scan puts it in stock. */
    public const LABEL = 'label';

    public const STATUSES = [self::AVAILABLE, self::OUT, self::CHECK, self::REPAIR, self::RETIRED];

    public const RETIRE_REASONS = ['sold', 'lost', 'stolen', 'broken'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'warranty_until' => 'date', 'retired_at' => 'datetime',
            'label_printed_at' => 'datetime', 'purchase_price_cents' => 'integer', 'repair_released_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function moves(): HasMany
    {
        return $this->hasMany(Move::class)->latest('id');
    }

    public function outOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'out_order_id');
    }

    /** The case (light dolly, storage box, flight case…) this piece is in, if any. */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'case_id');
    }

    /** The pieces in this case. */
    public function packed(): HasMany
    {
        return $this->hasMany(Asset::class, 'case_id')->orderBy('code');
    }

    /** A piece of a case item can hold other pieces. */
    public function isContainer(): bool
    {
        return $this->item?->kind === 'case';
    }

    /**
     * Next free code for an item. Follows the item's own codes when it has some
     * (DOLLY-06 → DOLLY-07), else its prefix: LT-0001, LT-0002, …
     */
    public static function nextCode(Item $item): string
    {
        $last = $item->exists ? DB::table('equipment_assets')->where('item_id', $item->id)->orderByDesc('id')->value('code') : null;
        if ($last && preg_match('/^(.*?\D)(\d+)$/', $last, $m)) {
            $prefix = $m[1];
            $width = strlen($m[2]);
        } else {
            $prefix = $item->codePrefix().'-';
            $width = 4;
        }
        $max = DB::table('equipment_assets')->where('code', 'like', $prefix.'%')->pluck('code')
            ->map(fn ($c) => preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $c, $mm) ? (int) $mm[1] : 0)->max() ?? 0;

        return $prefix.str_pad((string) ($max + 1), $width, '0', STR_PAD_LEFT);
    }

    /** Straight-line book value over 5 years. */
    public function bookValueCents(): ?int
    {
        if (! $this->purchase_price_cents || ! $this->purchase_date) {
            return null;
        }
        $years = $this->purchase_date->diffInDays(today()) / 365.25;

        return (int) max(0, round($this->purchase_price_cents * (1 - $years / 5)));
    }
}
