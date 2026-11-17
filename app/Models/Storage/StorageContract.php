<?php

namespace App\Models\Storage;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** A customer or supplier stores equipment at CLUK: places per day, handling in and out, checks (Afrit 6 prices). */
#[Fillable(['company_id', 'status', 'start_date', 'end_date', 'note', 'created_by_id'])]
class StorageContract extends Model
{
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(StoragePlace::class, 'contract_id')->orderBy('from');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoredItem::class, 'contract_id')->orderBy('name');
    }

    public function pieces(): HasManyThrough
    {
        return $this->hasManyThrough(StoredPiece::class, StoredItem::class, 'contract_id', 'stored_item_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(StorageMove::class, 'contract_id')->orderByDesc('date')->orderByDesc('id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(StorageStatement::class, 'contract_id')->orderByDesc('period_start');
    }

    public function code(): string
    {
        return 'ST-C'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
