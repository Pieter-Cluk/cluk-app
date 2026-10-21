<?php

namespace App\Models\Afrit6;

use App\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A desk booked for a morning (am), an afternoon (pm) or a whole day. */
#[Fillable(['member_id', 'space_id', 'date', 'part', 'price_cents', 'cancelled_at'])]
class DeskBooking extends Model
{
    protected $table = 'afrit6_desk_bookings';

    public const PARTS = ['am', 'pm', 'day'];

    protected function casts(): array
    {
        return ['date' => 'date', 'price_cents' => 'integer', 'cancelled_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('cancelled_at');
    }

    /** The parts that clash with $part: a whole day clashes with everything. */
    public static function clashingParts(string $part): array
    {
        return $part === 'day' ? self::PARTS : [$part, 'day'];
    }

    public static function price(string $part): int
    {
        return (int) config($part === 'day' ? 'afrit6.prices.desk_day' : 'afrit6.prices.desk_half_day');
    }

    public static function startsAt(Carbon $date, string $part): Carbon
    {
        return Carbon::parse($date->toDateString().' '.config('afrit6.desk_parts.'.$part.'.0'));
    }

    public function hasStarted(): bool
    {
        return self::startsAt($this->date, $this->part)->lte(now());
    }

    public function hours(): string
    {
        return implode('–', config('afrit6.desk_parts.'.$this->part));
    }
}
