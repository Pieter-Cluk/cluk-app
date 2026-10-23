<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How many of a counted item are in a case (dolly, box…). */
#[Fillable(['case_id', 'item_id', 'qty'])]
class CaseItem extends Model
{
    protected $table = 'equipment_case_items';

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'case_id');
    }
}
