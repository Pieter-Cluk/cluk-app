<?php

namespace App\Models\Afrit6;

use App\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A fixed desk for a member. ends_on is the last day (inclusive); null = until further notice. */
#[Fillable(['member_id', 'space_id', 'starts_on', 'ends_on', 'monthly_price_cents', 'created_by_id'])]
class DeskRental extends Model
{
    protected $table = 'afrit6_desk_rentals';

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'monthly_price_cents' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** Rentals of one desk that overlap [from, until]. */
    public static function overlapping(int $spaceId, Carbon|string $from, Carbon|string|null $until = null): Builder
    {
        $from = Carbon::parse($from)->toDateString();

        return static::query()
            ->where('space_id', $spaceId)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $from))
            ->when($until !== null, fn ($q) => $q->where('starts_on', '<=', Carbon::parse($until)->toDateString()));
    }

    public function scopeActiveOn(Builder $q, Carbon|string $day): Builder
    {
        $d = Carbon::parse($day)->toDateString();

        return $q->where('starts_on', '<=', $d)->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $d));
    }

    /** Active today or starting later. */
    public function scopeCurrent(Builder $q): Builder
    {
        $today = today()->toDateString();

        return $q->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $today));
    }

    public function isActiveOn(Carbon $day): bool
    {
        return $this->starts_on->lte($day) && ($this->ends_on === null || $this->ends_on->gte($day));
    }
}
