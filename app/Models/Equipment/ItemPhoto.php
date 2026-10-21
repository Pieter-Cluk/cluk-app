<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One of an item's pictures; the first one is the main picture. */
#[Fillable(['item_id', 'path', 'position'])]
class ItemPhoto extends Model
{
    protected $table = 'equipment_item_photos';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
