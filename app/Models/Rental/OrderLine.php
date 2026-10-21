<?php

namespace App\Models\Rental;

use App\Models\Equipment\Item;
use App\Services\Rental\Availability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A line of a rental order, covered by own stock (serial numbers or a count) and/or sub-hire. */
#[Fillable(['order_id', 'position', 'kind', 'item_id', 'number', 'name', 'qty', 'unit_price_cents', 'factor', 'discount_bp', 'amount_cents', 'own_qty'])]
class OrderLine extends Model
{
    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'factor' => 'decimal:2', 'unit_price_cents' => 'integer', 'discount_bp' => 'integer', 'amount_cents' => 'integer', 'own_qty' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(OrderAsset::class);
    }

    public function subhireLines(): HasMany
    {
        return $this->hasMany(SubhireLine::class);
    }

    /** Equipment that has to come from somewhere (not services, charges or text). */
    public function needsStock(): bool
    {
        return $this->kind === 'item' && $this->item && $this->item->kind !== 'service';
    }

    public function pieces(): int
    {
        return (int) ceil((float) $this->qty);
    }

    /** Is this line tracked by serial number (one asset per piece)? */
    public function bySerial(): bool
    {
        return $this->item && $this->item->kind !== 'package' && (bool) $this->item->serial;
    }

    /** Pieces asked from partners and not refused (draft, sent or accepted). */
    public function rentQty(): int
    {
        return (int) $this->subhireLines->filter(fn ($s) => in_array($s->request->status, ['draft', 'sent', 'accepted'], true))->sum('qty');
    }

    public function rentedQty(): int
    {
        return (int) $this->subhireLines->filter(fn ($s) => $s->request->status === 'accepted')->sum('qty');
    }

    /** Pieces we have to give from our own stock. */
    public function ownNeeded(): int
    {
        return max(0, $this->pieces() - $this->rentQty());
    }

    public function ownAllocated(): int
    {
        return $this->bySerial() ? $this->assets->count() : (int) $this->own_qty;
    }

    /** Pieces we cannot give from our own stock and no partner was asked for yet. */
    public function shortWithoutPartner(Order $order, ?Availability $avail = null): int
    {
        if (! $this->needsStock()) {
            return 0;
        }
        $avail ??= new Availability($order->out_from, $order->out_until, null, $order->id);
        $free = (int) $avail->free($this->item);

        return max(0, $this->ownNeeded() - $free);
    }

    public function isCovered(): bool
    {
        return ! $this->needsStock() || $this->ownAllocated() + $this->rentedQty() >= $this->pieces();
    }

    public function qtyLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->qty, 2, '.', ''), '0'), '.');
    }
}
