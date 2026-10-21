<?php

namespace App\Models\Warehouse;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One scan that moved something: the movement log. */
#[Fillable(['asset_id', 'item_id', 'qty', 'from_code', 'to_code', 'kind', 'order_id', 'user_id', 'note'])]
class Move extends Model
{
    protected $table = 'warehouse_moves';

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
