<?php

namespace App\Models\Warehouse;

use App\Models\Equipment\Asset;
use App\Models\Rental\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cleaning and the bench check of one piece after a job. */
#[Fillable(['asset_id', 'order_id', 'dirt', 'cleaned_at', 'cleaned_by_id', 'tests', 'missing', 'damages', 'photos', 'reset', 'result', 'finished_at', 'checked_by_id'])]
class Check extends Model
{
    protected $table = 'warehouse_checks';

    protected function casts(): array
    {
        return [
            'tests' => 'array', 'missing' => 'array', 'damages' => 'array', 'photos' => 'array', 'reset' => 'boolean',
            'cleaned_at' => 'datetime', 'finished_at' => 'datetime', 'dirt' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cleanedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleaned_by_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /** The open check of a piece (one per round through the route). */
    public static function openFor(Asset $asset): self
    {
        return static::where('asset_id', $asset->id)->whereNull('finished_at')->latest('id')->first()
            ?? static::create(['asset_id' => $asset->id, 'order_id' => $asset->out_order_id ?? Move::where('asset_id', $asset->id)->where('kind', 'return')->latest('id')->value('order_id')]);
    }
}
