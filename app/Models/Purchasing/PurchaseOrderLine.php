<?php

namespace App\Models\Purchasing;

use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuoteLine;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_id', 'sales_item_id', 'sales_quote_line_id', 'name', 'qty', 'unit_cents', 'amount_cents', 'delivery_date', 'purpose', 'received_qty'])]
class PurchaseOrderLine extends Model
{
    protected function casts(): array
    {
        return ['qty' => 'integer', 'unit_cents' => 'integer', 'amount_cents' => 'integer', 'received_qty' => 'integer', 'delivery_date' => 'date'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesItem::class, 'sales_item_id');
    }

    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(SalesQuoteLine::class, 'sales_quote_line_id');
    }
}
