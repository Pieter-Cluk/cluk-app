<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** One physical piece of a serial item, with its own code and QR label. */
#[Fillable([
    'item_id', 'code', 'serial_number', 'status', 'location', 'purchase_date', 'purchase_price_cents',
    'supplier', 'warranty_until', 'note', 'retired_reason', 'retired_at', 'label_printed_at',
])]
class Asset extends Model
{
    protected $table = 'equipment_assets';

    public const AVAILABLE = 'available';

    public const OUT = 'out';

    public const REPAIR = 'repair';

    public const RETIRED = 'retired';

    public const STATUSES = [self::AVAILABLE, self::OUT, self::REPAIR, self::RETIRED];

    public const RETIRE_REASONS = ['sold', 'lost', 'stolen', 'broken'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'warranty_until' => 'date', 'retired_at' => 'datetime',
            'label_printed_at' => 'datetime', 'purchase_price_cents' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Next free code for an item's prefix: LT-0001, LT-0002, … */
    public static function nextCode(Item $item): string
    {
        $prefix = $item->codePrefix().'-';
        $last = DB::table('equipment_assets')->where('code', 'like', $prefix.'%')->pluck('code')
            ->map(fn ($c) => (int) substr($c, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
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
