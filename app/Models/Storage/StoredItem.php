<?php

namespace App\Models\Storage;

use App\Models\Equipment\Item;
use App\Models\Warehouse\CheckProcedure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A kind of equipment a storage customer keeps here (theirs, never our fleet). */
#[Fillable(['contract_id', 'name', 'their_number', 'same_as_item_id', 'check_procedure_id'])]
class StoredItem extends Model
{
    public function contract(): BelongsTo
    {
        return $this->belongsTo(StorageContract::class, 'contract_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(StoredPiece::class)->orderBy('code');
    }

    public function sameAs(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'same_as_item_id');
    }

    public function checkProcedure(): BelongsTo
    {
        return $this->belongsTo(CheckProcedure::class, 'check_procedure_id');
    }
}
