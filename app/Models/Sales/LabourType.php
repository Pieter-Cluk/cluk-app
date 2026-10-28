<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A kind of labour (installation, technician…) with its rate per hour or per day, excl. VAT. */
#[Fillable(['names', 'unit', 'price_cents', 'active', 'position'])]
class LabourType extends Model
{
    public const UNITS = ['hour', 'day'];

    protected function casts(): array
    {
        return ['names' => 'array', 'price_cents' => 'integer', 'active' => 'boolean', 'position' => 'integer'];
    }

    /** Active and with a rate: can go on a quote. */
    public function scopeOffered(Builder $q): Builder
    {
        return $q->where('active', true)->whereNotNull('price_cents')->orderBy('position')->orderBy('id');
    }

    public function name(?string $locale = null): string
    {
        $n = $this->names ?? [];

        return (string) (($n[$locale ?? app()->getLocale()] ?? null) ?: ($n['en'] ?? null) ?: (reset($n) ?: '—'));
    }
}
