<?php

namespace App\Models\Storage;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One piece of a storage customer, with our label (ST-00001). */
#[Fillable(['stored_item_id', 'code', 'serial_number', 'position_code', 'status'])]
class StoredPiece extends Model
{
    public function item(): BelongsTo
    {
        return $this->belongsTo(StoredItem::class, 'stored_item_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(StorageCheck::class)->latest('id');
    }

    public static function nextCode(): string
    {
        $last = (int) substr((string) self::orderByDesc('id')->value('code'), 3);

        return 'ST-'.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
