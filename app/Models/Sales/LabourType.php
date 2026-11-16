<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A kind of labour (installation, technician…) with its rate per hour or per day, excl. VAT. */
#[Fillable(['names', 'unit', 'used_for', 'price_cents', 'active', 'position'])]
class LabourType extends Model
{
    public const UNITS = ['hour', 'day'];

    /** Which quotes offer it: rental (and Cluk Factory) quotes, sales quotes (repair, maintenance) or both. */
    public const USED_FOR = ['rental', 'sales', 'both'];

    protected function casts(): array
    {
        return ['names' => 'array', 'price_cents' => 'integer', 'active' => 'boolean', 'position' => 'integer'];
    }

    /** Active and with a rate: can go on a quote. */
    public function scopeOffered(Builder $q): Builder
    {
        return $q->where('active', true)->whereNotNull('price_cents')->orderBy('position')->orderBy('id');
    }

    /** Offered on rental quotes or on sales quotes. */
    public function scopeFor(Builder $q, string $module): Builder
    {
        return $q->offered()->whereIn('used_for', [$module, 'both']);
    }

    public function name(?string $locale = null): string
    {
        $n = $this->names ?? [];

        return (string) (($n[$locale ?? app()->getLocale()] ?? null) ?: ($n['en'] ?? null) ?: (reset($n) ?: '—'));
    }
}
