<?php

namespace App\Models\Storage;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Goods in or out for a storage customer: colis and pallets (handling), the pieces, who signed. */
#[Fillable(['contract_id', 'kind', 'date', 'colis', 'pallets', 'pieces', 'signed_name', 'note', 'user_id'])]
class StorageMove extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date', 'pieces' => 'array', 'colis' => 'integer', 'pallets' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
