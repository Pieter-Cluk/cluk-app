<?php

namespace App\Models\Rental;

use App\Models\Equipment\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'order_line_id', 'item_id', 'name', 'qty', 'last_price_cents', 'price_cents', 'factor', 'discount_bp', 'amount_cents', 'received_qty', 'back_qty'])]
class SubhireLine extends Model
{
    protected function casts(): array
    {
        return ['qty' => 'integer', 'last_price_cents' => 'integer', 'price_cents' => 'integer', 'factor' => 'decimal:2', 'discount_bp' => 'integer', 'amount_cents' => 'integer', 'received_qty' => 'integer', 'back_qty' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SubhireRequest::class, 'request_id');
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** The price this partner charged last time for the same item (per piece per day). */
    public static function lastPrice(int $supplierId, ?int $itemId): ?int
    {
        if (! $itemId) {
            return null;
        }

        return static::query()->where('item_id', $itemId)->whereNotNull('price_cents')
            ->whereHas('request', fn ($q) => $q->where('supplier_id', $supplierId)->where('status', 'accepted'))
            ->latest('id')->value('price_cents');
    }
}
