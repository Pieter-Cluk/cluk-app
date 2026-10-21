<?php

namespace App\Models\ProductSales;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sales_quote_id', 'sales_item_id', 'kind', 'name', 'description', 'qty', 'list_cents', 'discount_bp', 'unit_cents', 'cost_cents', 'amount_cents', 'from_stock', 'delivery_date', 'position'])]
class SalesQuoteLine extends Model
{
    protected function casts(): array
    {
        return ['qty' => 'integer', 'list_cents' => 'integer', 'discount_bp' => 'integer', 'unit_cents' => 'integer', 'cost_cents' => 'integer',
            'amount_cents' => 'integer', 'from_stock' => 'integer', 'delivery_date' => 'date'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class, 'sales_quote_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesItem::class, 'sales_item_id');
    }

    public function marginBp(): int
    {
        return $this->unit_cents ? (int) round(($this->unit_cents - $this->cost_cents) / $this->unit_cents * 10000) : ($this->cost_cents ? -10000 : 0);
    }

    public function toBuy(): int
    {
        return max(0, $this->qty - $this->from_stock);
    }
}
