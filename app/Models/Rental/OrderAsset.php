<?php

namespace App\Models\Rental;

use App\Models\Equipment\Asset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A serial-numbered piece booked for an order line. */
#[Fillable(['order_id', 'order_line_id', 'asset_id'])]
class OrderAsset extends Model
{
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }
}
