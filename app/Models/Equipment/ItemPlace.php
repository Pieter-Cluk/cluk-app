<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How many of a counted item lie on one rack place. */
#[Fillable(['item_id', 'position_code', 'qty'])]
class ItemPlace extends Model
{
    protected $table = 'equipment_item_places';

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
