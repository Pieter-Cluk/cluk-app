<?php

namespace App\Models\Warehouse;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A cost for the customer found at the check (cleaning, missing part, damage), approved by the office. */
#[Fillable(['order_id', 'asset_id', 'check_id', 'item_id', 'kind', 'label', 'qty', 'amount_cents', 'original_cents', 'status', 'decided_by_id', 'decided_at'])]
class Charge extends Model
{
    protected $table = 'warehouse_charges';

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'original_cents' => 'integer', 'qty' => 'integer', 'decided_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}
