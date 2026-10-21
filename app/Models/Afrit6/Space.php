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

    /** Desks and the private office: what can be rented per month. */
    public function scopeRentable(Builder $q): Builder
    {
        return $q->whereIn('kind', ['desk', 'office'])->where('active', true)->orderBy('sort');
    }

    public static function office(): ?self
    {
        return static::where('kind', 'office')->where('active', true)->orderBy('sort')->first();
    }

    /** Free for a morning, afternoon or whole day: no monthly rental, no clashing day booking. */
    public function isFreeForPart(Carbon|string $date, string $part, ?int $ignoreBookingId = null): bool
    {
        $d = Carbon::parse($date)->toDateString();

        return ! DeskRental::where('space_id', $this->id)->activeOn($d)->exists()
            && ! DeskBooking::live()->where('space_id', $this->id)->whereDate('date', $d)
                ->whereIn('part', DeskBooking::clashingParts($part))
                ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
                ->exists();
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
