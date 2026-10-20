<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A VAT rate with its validity. A government change is a new row with the
 * same code from a later date; the old row ends the day before. Rates are
 * never hard-coded: documents ask for the rate of a code on a date.
 */
#[Fillable(['name', 'rate_bp', 'code', 'zone', 'valid_from', 'valid_until'])]
class VatRate extends Model
{
    public const ZONES = ['be', 'eu', 'outside'];

    protected function casts(): array
    {
        return ['rate_bp' => 'integer', 'valid_from' => 'date', 'valid_until' => 'date'];
    }

    public function scopeValidOn(Builder $query, Carbon|string|null $date = null): Builder
    {
        $day = Carbon::parse($date ?? today())->toDateString();

        return $query->whereDate('valid_from', '<=', $day)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $day));
    }

    /** The rate for an OkiOki code on a day (today by default). */
    public static function forCode(string $code, Carbon|string|null $date = null): ?self
    {
        return static::query()->where('code', $code)->validOn($date)->orderByDesc('valid_from')->first();
    }

    /**
     * Codes that can be chosen today, one rate per code.
     *
     * @return Collection<int, self>
     */
    public static function activeChoices(): Collection
    {
        return static::query()->validOn()->orderByDesc('rate_bp')->orderBy('code')->get()->unique('code')->values();
    }

    public function status(?Carbon $today = null): string
    {
        $today = ($today ?? today())->copy()->startOfDay();

        return match (true) {
            $this->valid_from->gt($today) => 'planned',
            $this->valid_until !== null && $this->valid_until->lt($today) => 'ended',
            default => 'active',
        };
    }

    /** "21%", "5.5%" */
    public function percent(): string
    {
        $value = $this->rate_bp / 100;

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }

    /** Does this rate's period overlap another rate with the same code? */
    public function overlapsAnother(): bool
    {
        return static::query()
            ->where('code', $this->code)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->id))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $this->valid_from->toDateString()))
            ->when($this->valid_until, fn ($q) => $q->whereDate('valid_from', '<=', $this->valid_until->toDateString()))
            ->exists();
    }
}
