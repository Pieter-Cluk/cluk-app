<?php

namespace App\Models\Storage;

use App\Models\Warehouse\Position;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shelf or pallet place held for a storage customer from a date (until a date). */
#[Fillable(['contract_id', 'position_id', 'kind', 'from', 'until'])]
class StoragePlace extends Model
{
    protected function casts(): array
    {
        return ['from' => 'date', 'until' => 'date'];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(StorageContract::class, 'contract_id');
    }
}
