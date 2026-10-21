<?php

namespace App\Models\Afrit6;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** A desk or the meeting room. */
#[Fillable(['code', 'kind', 'floor', 'zone', 'sort', 'active'])]
class Space extends Model
{
    protected $table = 'afrit6_spaces';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(DeskRental::class, 'space_id');
    }

    public function scopeDesks(Builder $q): Builder
    {
        return $q->where('kind', 'desk')->where('active', true)->orderBy('sort');
    }

    public static function room(): ?self
    {
        return static::where('kind', 'room')->where('active', true)->orderBy('sort')->first();
    }

    /** Is the desk free for the whole period from $from until $until (null = open-ended)? */
    public function isFree(Carbon|string $from, Carbon|string|null $until = null, ?int $ignoreRentalId = null): bool
    {
        return ! DeskRental::overlapping($this->id, $from, $until)
            ->when($ignoreRentalId, fn ($q) => $q->whereKeyNot($ignoreRentalId))
            ->exists();
    }
}
