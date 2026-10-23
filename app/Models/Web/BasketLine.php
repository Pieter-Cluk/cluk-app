<?php

namespace App\Models\Web;

use App\Models\Equipment\Item;
use App\Models\ProductSales\SalesItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['basket_id', 'item_id', 'sales_item_id', 'qty'])]
class BasketLine extends Model
{
    protected $table = 'customer_basket_lines';

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function salesItem(): BelongsTo
    {
        return $this->belongsTo(SalesItem::class);
    }
}
