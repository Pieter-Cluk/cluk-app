<?php

namespace App\Models\Sales;

use App\Models\Equipment\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line on a quote: an item from the catalogue, a free charge (transport…), or a text line. */
#[Fillable(['quote_id', 'position', 'kind', 'item_id', 'labour_type_id', 'number', 'name', 'qty', 'unit_price_cents', 'factor', 'discount_bp', 'amount_cents'])]
class QuoteLine extends Model
{
    public const KINDS = ['item', 'charge', 'text'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'factor' => 'decimal:2', 'unit_price_cents' => 'integer', 'discount_bp' => 'integer', 'amount_cents' => 'integer', 'position' => 'integer'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function labourType(): BelongsTo
    {
        return $this->belongsTo(LabourType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Does the rental factor apply? Only to equipment priced per day. */
    public function usesFactor(): bool
    {
        return $this->kind === 'item' && (! $this->item || in_array($this->item->price_unit ?? 'day', config('rental.factor_units'), true));
    }

    public function effectiveFactor(float $default): float
    {
        if (! $this->usesFactor()) {
            return $this->factor !== null ? (float) $this->factor : 1.0;
        }

        return $this->factor !== null ? (float) $this->factor : $default;
    }

    public function computeAmount(float $defaultFactor): int
    {
        if ($this->kind === 'text') {
            return 0;
        }

        return (int) round((float) $this->qty * $this->unit_price_cents * $this->effectiveFactor($defaultFactor) * (1 - $this->discount_bp / 10000));
    }

    /** Qty as shown: 12 instead of 12.00. */
    public function qtyLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->qty, 2, '.', ''), '0'), '.');
    }
}
